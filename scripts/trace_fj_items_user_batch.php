<?php

declare(strict_types=1);

/**
 * Trace selisih Rekap FJ vs item_prices untuk batch item user.
 *
 * Usage:
 *   php scripts/trace_fj_items_user_batch.php
 *   php scripts/trace_fj_items_user_batch.php --outlet="Justus Steakhouse SMB"
 *   php scripts/trace_fj_items_user_batch.php --from=2026-06-01 --to=2026-06-30
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletFilter = null;
$from = '2026-06-01';
$to = '2026-06-30';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--outlet=')) {
        $outletFilter = substr($arg, 9);
    }
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

$itemNames = [
    'Artisan Tea Ceylon Green Tea',
    'Artisan Tea Ginger & Mint',
    'Butter Salted',
    'Cup 12 Logo SH',
    'Gas Whipped',
    'Kacang Arab',
    'Kacang Tanah',
    'Kecap Asin Angsa',
    'Salad Oil',
    'Vetcin Powder',
];

function serialEffectivePriceSql(string $itemAlias = 'it'): string
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

echo "=== Trace batch item Rekap FJ vs item_prices ===\n";
echo "Periode: {$from} .. {$to}\n";
echo 'Outlet: ' . ($outletFilter ?: 'ALL') . "\n\n";

$auditor = new FloorOrderPriceAuditor();
$itemIds = [];

foreach ($itemNames as $name) {
    $item = DB::table('items')->where('name', $name)->first();
    if (! $item) {
        echo "{$name}: NOT FOUND\n\n";
        continue;
    }
    $itemId = (int) $item->id;
    $itemIds[] = $itemId;

    $mediumUnit = DB::table('units')->where('id', $item->medium_unit_id)->value('name') ?? '-';
    $largeUnit = DB::table('units')->where('id', $item->large_unit_id)->value('name') ?? '-';
    $smallUnit = DB::table('units')->where('id', $item->small_unit_id)->value('name') ?? '-';

    $priceRow = FloorOrderItemPriceResolver::resolvePriceRow($itemId, null, null);
    $priceLarge = FloorOrderItemPriceResolver::resolvePriceLarge($itemId, $priceRow);
    $expectedMedium = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $mediumUnit);
    $expectedLarge = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $largeUnit);
    $expectedSmall = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $smallUnit);

    echo "=== {$name} (id={$itemId}) ===\n";
    echo "conv: medium={$item->medium_conversion_qty} small={$item->small_conversion_qty}\n";
    echo "units: large={$largeUnit} medium={$mediumUnit} small={$smallUnit}\n";
    echo 'item_prices large: ' . number_format($priceLarge, 2) . "\n";
    echo 'expected medium: ' . number_format($expectedMedium, 0) . ' | large: ' . number_format($expectedLarge, 0) . ' | small: ' . number_format($expectedSmall, 2) . "\n";

    $foPrices = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->whereBetween('ffo.tanggal', [$from, $to])
        ->selectRaw('ffoi.unit, ffoi.price, COUNT(*) as cnt')
        ->groupBy('ffoi.unit', 'ffoi.price')
        ->orderByDesc('cnt')
        ->limit(8)
        ->get();
    echo "FO price dist (Jun period):\n";
    foreach ($foPrices as $fp) {
        $tier = FloorOrderItemPriceResolver::detectUnitTier($item, (string) $fp->unit);
        echo "  unit={$fp->unit} tier={$tier} price={$fp->price} cnt={$fp->cnt}\n";
    }

    $outletQuery = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet');
    if ($outletFilter) {
        $outletQuery->where('nama_outlet', $outletFilter);
    }
    $outlets = $outletQuery->get();

    foreach ($outlets as $outlet) {
        $food = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->where('gr.outlet_id', $outlet->id_outlet)
            ->where('i.item_id', $itemId)
            ->whereBetween('gr.receive_date', [$from, $to])
            ->whereNull('gr.deleted_at')
            ->selectRaw('SUM(i.received_qty) as qty, AVG(COALESCE(fo.price,0)) as avg_price')
            ->first();

        $serialAvg = null;
        if (Schema::hasTable('outlet_serial_receive_headers')) {
            $expr = serialEffectivePriceSql();
            $serialAvg = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->where('h.outlet_id', $outlet->id_outlet)
                ->where('si.item_id', $itemId)
                ->whereBetween('h.receive_date', [$from, $to])
                ->whereNull('h.deleted_at')
                ->selectRaw("SUM(si.qty) as qty, AVG({$expr}) as avg_price")
                ->first();
        }

        $foodQty = (float) ($food->qty ?? 0);
        $serialQty = (float) ($serialAvg->qty ?? 0);
        if ($foodQty + $serialQty <= 0) {
            continue;
        }

        $foodSub = $foodQty * (float) ($food->avg_price ?? 0);
        $serialSub = $serialQty * (float) ($serialAvg->avg_price ?? 0);
        $totalQty = $foodQty + $serialQty;
        $reportPrice = ($foodSub + $serialSub) / $totalQty;
        $selisih = $reportPrice - $expectedMedium;

        if (abs($selisih) > 100) {
            echo "  [{$outlet->nama_outlet}] report=" . number_format($reportPrice, 0)
                . ' expected=' . number_format($expectedMedium, 0)
                . ' selisih=' . number_format($selisih, 0)
                . " (food_qty={$foodQty} serial_qty={$serialQty})\n";
        }
    }

    echo "\n";
}

echo "=== Auditor FO mismatches (all statuses) ===\n";
$scan = $auditor->scan($from, $to, true, $itemIds);
echo 'scanned=' . $scan['rows_scanned'] . ' mismatches=' . count($scan['mismatches']) . "\n";
foreach ($scan['summary_by_item'] as $s) {
    echo "  {$s['item_name']}: {$s['mismatch_rows']} rows | current=" . json_encode($s['current_prices']) . " expected=" . json_encode($s['expected_prices']) . "\n";
}
