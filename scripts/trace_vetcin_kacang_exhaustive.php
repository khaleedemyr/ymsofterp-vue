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
    $medium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
    $expected = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, (string) $medium);
    $ip = DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->orderByDesc('id')->first();
    $targetCostSmall = round((float) $ip->price / max((float) $item->small_conversion_qty, 1), 4);
    $expr = serialExpr();

    echo "=== {$name} target cost_small={$targetCostSmall} expected={$expected} ===\n";

    // Any serial with wrong cost_small in June
    $bad = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'it.id', '=', 'si.item_id')
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->whereRaw('ABS(COALESCE(si.cost_small,0) - ?) > 0.0001', [$targetCostSmall])
        ->select('si.id', 'si.cost_small', 'si.qty', 'h.receive_date', 'o.nama_outlet', 'w.name as warehouse')
        ->get();
    echo "bad serial cost_small: {$bad->count()}\n";
    foreach ($bad->take(10) as $b) {
        echo "  si={$b->id} cost_small={$b->cost_small} qty={$b->qty} date={$b->receive_date} outlet={$b->nama_outlet} wh={$b->warehouse}\n";
    }

    // Serial effective price dist (any != expected)
    $dist = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'it.id', '=', 'si.item_id')
        ->where('si.item_id', $itemId)
        ->whereBetween('h.receive_date', [$from, $to])
        ->whereNull('h.deleted_at')
        ->selectRaw("ROUND({$expr}, 2) as eff_price, SUM(si.qty) as qty_sum, COUNT(*) as cnt")
        ->groupByRaw("ROUND({$expr}, 2)")
        ->orderByDesc('qty_sum')
        ->get();
    echo "serial effective price dist:\n";
    foreach ($dist as $d) {
        $flag = abs((float) $d->eff_price - $expected) > 1 ? ' ***' : '';
        echo "  eff={$d->eff_price} qty={$d->qty_sum} cnt={$d->cnt}{$flag}\n";
    }

    // FO linked to GR with wrong price
    $badFo = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
        ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
        ->join('food_floor_order_items as fo', function ($j) {
            $j->on('gri.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'gr.outlet_id')
        ->where('gri.item_id', $itemId)
        ->whereBetween('gr.receive_date', [$from, $to])
        ->whereNull('gr.deleted_at')
        ->whereRaw('ABS(fo.price - ?) > 1', [$expected])
        ->select('fo.id', 'fo.price', 'gr.receive_date', 'o.nama_outlet', 'gri.received_qty')
        ->get();
    echo "bad FO on GR join: {$badFo->count()}\n";
    foreach ($badFo->take(10) as $b) {
        echo "  fo={$b->id} price={$b->price} recv={$b->received_qty} date={$b->receive_date} outlet={$b->nama_outlet}\n";
    }

    // Per warehouse report price ALL outlets
    foreach (['Main Store', 'Main Kitchen'] as $wh) {
        $ctrl = new class {
            use \App\Http\Traits\ReportHelperTrait;
            public function agg(string $from, string $to, string $wh): array {
                $out = [];
                foreach (DB::table('tbl_data_outlet')->pluck('nama_outlet') as $outlet) {
                    $food = $this->rekapFjFetchFoodGrDetailRows($outlet, $from, $to, $wh);
                    $serial = $this->rekapFjFetchSerialGrDetailRows($outlet, $from, $to, $wh);
                    foreach ($this->rekapFjMergeFjDetailRows($food, $serial) as $row) {
                        $n = (string) $row->item_name;
                        $out[$n] = ($out[$n] ?? 0) + (float) $row->subtotal;
                        $out[$n . '_qty'] = ($out[$n . '_qty'] ?? 0) + (float) $row->received_qty;
                    }
                }
                return $out;
            }
        };
        $a = $ctrl->agg($from, $to, $wh);
        $qty = (float) ($a[$name . '_qty'] ?? 0);
        $sub = (float) ($a[$name] ?? 0);
        if ($qty > 0) {
            $rp = $sub / $qty;
            $sel = $rp - $expected;
            echo "[{$wh}] report=" . round($rp, 2) . " qty={$qty} selisih=" . round($sel, 2);
            if (abs($sel) > 100) {
                echo " ***";
            }
            echo "\n";
        }
    }
    echo "\n";
}
