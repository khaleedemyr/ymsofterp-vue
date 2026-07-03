<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$from = '2026-06-01';
$to = '2026-06-30';

function serialExpr(string $itemAlias = 'it'): string
{
    $costSmall = 'COALESCE(si.cost_small, 0)';
    $smallConv = "COALESCE({$itemAlias}.small_conversion_qty, 1)";
    $mediumConv = "COALESCE({$itemAlias}.medium_conversion_qty, 1)";

    return "(CASE
        WHEN si.unit_id = {$itemAlias}.large_unit_id THEN {$costSmall} * {$smallConv} * {$mediumConv}
        WHEN si.unit_id = {$itemAlias}.medium_unit_id THEN {$costSmall} * {$smallConv}
        ELSE {$costSmall}
    END)";
}

foreach (['Kacang Tanah', 'Vetcin Powder'] as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    $itemId = (int) $item->id;
    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $mediumUnit);

    $priceRow = DB::table('item_prices')
        ->where('item_id', $itemId)
        ->where('availability_price_type', 'all')
        ->orderByDesc('id')
        ->first();
    $targetCostSmall = $priceRow && (float) $priceRow->price > 0
        ? round((float) $priceRow->price / max((float) $item->small_conversion_qty, 1), 4)
        : 0;

    echo "=== {$name} (id={$itemId}) ===\n";
    echo "item_prices large={$priceRow->price} pricing_mode=" . ($priceRow->pricing_mode ?? '-') . "\n";
    echo "expected medium={$expected} target cost_small={$targetCostSmall}\n";

    // Global aggregate (all outlets) like report pivot
    $food = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->leftJoin('food_floor_order_items as fo', function ($join) {
            $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->where('i.item_id', $itemId)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->selectRaw('SUM(i.received_qty) as qty, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal')
        ->first();

    $expr = serialExpr();
    $serial = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->selectRaw("SUM(si.qty) as qty, SUM(si.qty * {$expr}) as subtotal")
        ->first();

    $fq = (float) ($food->qty ?? 0);
    $sq = (float) ($serial->qty ?? 0);
    $tq = $fq + $sq;
    $report = $tq > 0 ? ((float) $food->subtotal + (float) $serial->subtotal) / $tq : 0;
    echo "GLOBAL report={$report} selisih=" . ($report - $expected) . " (food_qty={$fq} serial_qty={$sq})\n";

    // Serial cost_small distribution
    $serialPrices = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'it.id', '=', 'si.item_id')
        ->join('units as u', 'u.id', '=', 'si.unit_id')
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->selectRaw("si.cost_small, u.name as unit, COUNT(*) as cnt, SUM(si.qty) as qty_sum")
        ->groupBy('si.cost_small', 'u.name')
        ->orderByDesc('qty_sum')
        ->get();
    echo "Serial cost_small dist:\n";
    foreach ($serialPrices as $sp) {
        $eff = DB::selectOne("SELECT {$expr} as eff FROM outlet_serial_receive_items si JOIN items it ON it.id=si.item_id WHERE si.item_id=? AND si.cost_small=? LIMIT 1", [$itemId, $sp->cost_small]);
        $bad = abs((float) $sp->cost_small - $targetCostSmall) > 0.0001 ? ' BAD' : '';
        echo "  cost_small={$sp->cost_small} unit={$sp->unit} cnt={$sp->cnt} qty={$sp->qty_sum} eff_price=" . ($eff->eff ?? '?') . "{$bad}\n";
    }

    // Outlets still with selisih > 100
    $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();
    echo "Outlets with selisih > 100:\n";
    foreach ($outlets as $outlet) {
        $f = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.outlet_id', $outlet->id_outlet)
            ->where('i.item_id', $itemId)
            ->whereBetween('gr.receive_date', [$from, $to])
            ->whereNull('gr.deleted_at')
            ->selectRaw('SUM(i.received_qty) as qty, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal')
            ->first();
        $s = DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->where('h.outlet_id', $outlet->id_outlet)
            ->where('si.item_id', $itemId)
            ->whereBetween('h.receive_date', [$from, $to])
            ->whereNull('h.deleted_at')
            ->selectRaw("SUM(si.qty) as qty, SUM(si.qty * {$expr}) as subtotal")
            ->first();
        $oq = (float) ($f->qty ?? 0) + (float) ($s->qty ?? 0);
        if ($oq <= 0) {
            continue;
        }
        $op = ((float) ($f->subtotal ?? 0) + (float) ($s->subtotal ?? 0)) / $oq;
        $sel = $op - $expected;
        if (abs($sel) > 100) {
            echo "  [{$outlet->nama_outlet}] report=" . round($op) . " selisih=" . round($sel) . " food=" . ($f->qty ?? 0) . " serial=" . ($s->qty ?? 0) . "\n";
        }
    }
    echo "\n";
}
