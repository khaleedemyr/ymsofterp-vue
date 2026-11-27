<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outletId = 18;
$whId = 41;
$itemId = 52984;
$invItemId = 5080;

echo "=== A) Tenderloin exact drift at 12032 (fixed prev-card query) ===\n";
$detail = DB::table('stock_cut_details')->where('id', 1368926)->first();
$recv = DB::table('outlet_food_inventory_cards')->where('id', 3830900)->first();
$cutCard = DB::table('outlet_food_inventory_cards')->where('id', 3837229)->first();
$prev = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('id', '<', $cutCard->id)
    ->orderByDesc('id')
    ->first();
echo "prev_card_id={$prev->id} ref={$prev->reference_type} saldo={$prev->saldo_qty_small}\n";
echo "stock_before={$detail->stock_before_small} drift=" . ((float)$detail->stock_before_small - (float)$prev->saldo_qty_small) . "\n";
echo "need={$detail->qty_small} exact_one_day_bom=" . (abs((float)$detail->stock_before_small - (float)$prev->saldo_qty_small + (float)$detail->qty_small) < 0.01 ? 'Y' : 'N') . "\n";
echo "note: drift==-need means silent loss equals exactly one full cut of this material\n";

echo "\n=== B) Nov18 cut detail vs receive implied prior ===\n";
$d18 = DB::table('stock_cut_details')->where('id', 1365387)->first();
if (!$d18) {
    $d18 = DB::table('stock_cut_details as d')
        ->join('stock_cut_logs as l', 'l.id', '=', 'd.stock_cut_log_id')
        ->where('d.item_id', $itemId)
        ->where('d.warehouse_outlet_id', $whId)
        ->where('l.outlet_id', $outletId)
        ->where('l.tanggal', '2026-11-18')
        ->select('d.*', 'l.id as log_id', 'l.created_at as cut_at')
        ->first();
}
echo "nov18 detail: " . json_encode($d18) . "\n";
$c18 = DB::table('outlet_food_inventory_cards')->where('id', 3826624)->first();
echo "nov18 card after={$c18->saldo_qty_small} out={$c18->out_qty_small}\n";
echo "receive in={$recv->in_qty_small} saldo={$recv->saldo_qty_small} implied_stock_before_receive_if_card_math=" . ((float)$recv->saldo_qty_small - (float)$recv->in_qty_small) . "\n";
echo "if stock at receive was already 1650: after receive stock=3150 (=cut stock_before). card still shows 4350\n";

echo "\n=== C) Cost history on 19 Nov for receive — qty_lama / mac clues ===\n";
$ch = DB::table('outlet_food_inventory_cost_histories')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween('date', ['2026-11-18', '2026-11-20'])
    ->orderBy('id')
    ->get();
foreach ($ch as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== D) Waste header 57781 timing vs cut 12015 window ===\n";
if (Schema::hasTable('outlet_internal_use_waste_headers')) {
    $h = DB::table('outlet_internal_use_waste_headers')->where('id', 57781)->first();
    echo json_encode($h) . "\n";
    $wc = DB::table('outlet_food_inventory_cards')
        ->where('reference_type', 'outlet_internal_use_waste')
        ->where('reference_id', 57781)
        ->orderBy('id')
        ->limit(3)
        ->get(['id', 'created_at', 'inventory_item_id', 'out_qty_small', 'saldo_qty_small']);
    foreach ($wc as $c) {
        echo json_encode($c) . "\n";
    }
}

echo "\n=== E) Count physical cards on surviving cut 12032 ===\n";
$card12032 = DB::table('outlet_food_inventory_cards')
    ->where('stock_cut_log_id', 12032)
    ->where('out_qty_small', '>', 0)
    ->count();
$det12032 = DB::table('stock_cut_details')->where('stock_cut_log_id', 12032)->count();
echo "12032 cards_out>0={$card12032} details={$det12032} rollback12015_cards_affected=83\n";

echo "\n=== F) Activity around 22:00-22:40 user 724 (who rolled back + recut) ===\n";
$rows = DB::table('activity_logs')
    ->where('user_id', 724)
    ->whereBetween('created_at', ['2026-11-19 21:55:00', '2026-11-19 22:45:00'])
    ->orderBy('id')
    ->get(['id', 'created_at', 'module', 'activity_type', 'description']);
foreach ($rows as $r) {
    echo "{$r->created_at} {$r->module} {$r->activity_type} :: {$r->description}\n";
}

echo "\n=== G) Any inventory card id gap near tenderloin receive→cut suggesting deleted cards? ===\n";
// Cards for THIS item are only 3830900 then 3837229 — gap of ~6300 cards is normal system-wide.
// Check if ANY card with stock_cut_log_id=12015 remains anywhere
$left = DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12015)->count();
echo "remaining cards with log 12015: {$left}\n";
$leftDet = DB::table('stock_cut_details')->where('stock_cut_log_id', 12015)->count();
echo "remaining details log 12015: {$leftDet}\n";

echo "\n=== H) Transfers outlet 18 on 19 Nov involving item 52984 ===\n";
if (Schema::hasTable('outlet_transfers')) {
    $detailTable = null;
    foreach (['outlet_transfer_items', 'outlet_transfer_details', 'outlet_transfers_details'] as $t) {
        if (Schema::hasTable($t)) {
            $detailTable = $t;
            echo "detail table={$t} cols=" . implode(',', Schema::getColumnListing($t)) . "\n";
            break;
        }
    }
    if ($detailTable) {
        $itemCol = in_array('item_id', Schema::getColumnListing($detailTable), true) ? 'item_id' : null;
        if ($itemCol) {
            $xf = DB::table($detailTable . ' as d')
                ->join('outlet_transfers as h', 'h.id', '=', 'd.transfer_id')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.transfer_date', '2026-11-19')
                ->where('d.' . $itemCol, $itemId)
                ->get();
            echo "transfer lines: " . $xf->count() . "\n";
            foreach ($xf as $r) {
                echo json_encode($r) . "\n";
            }
        }
    }
}

echo "\n=== I) Stock opname WH42 vs Kitchen WH41 — tenderloin line? ===\n";
$soTables = [];
foreach (['outlet_stock_opname_items', 'stock_opname_items', 'outlet_stock_opname_details'] as $t) {
    if (Schema::hasTable($t)) {
        $soTables[] = $t;
        echo "has {$t} cols=" . implode(',', array_slice(Schema::getColumnListing($t), 0, 20)) . "\n";
    }
}
foreach ($soTables as $t) {
    $cols = Schema::getColumnListing($t);
    $opnameFk = null;
    foreach (['stock_opname_id', 'outlet_stock_opname_id', 'opname_id'] as $c) {
        if (in_array($c, $cols, true)) {
            $opnameFk = $c;
            break;
        }
    }
    if (!$opnameFk) {
        continue;
    }
    $q = DB::table($t)->where($opnameFk, 10554);
    if (in_array('item_id', $cols, true)) {
        $q->where(function ($qq) use ($cols) {
            $qq->where('item_id', 52984);
            if (in_array('inventory_item_id', $cols, true)) {
                $qq->orWhere('inventory_item_id', 5080);
            }
        });
    } elseif (in_array('inventory_item_id', $cols, true)) {
        $q->where('inventory_item_id', 5080);
    }
    $items = $q->get();
    echo "{$t} tenderloin lines: " . $items->count() . "\n";
    foreach ($items as $i) {
        echo json_encode($i) . "\n";
    }
}

echo "\n=== J) Search code paths that UPDATE stocks without inserting cards (sample) ===\n";
// Just report known: potongStockOrderItems updates stock then card; if interrupted after stock, silent loss.

echo "\n=== K) Was 1200g already missing at receive time? Infer from cost history mac ===\n";
// If receive wrote cost history with qty before
$chRecv = DB::table('outlet_food_inventory_cost_histories')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereDate('date', '2026-11-19')
    ->orderBy('id')
    ->get();
echo "cost hist rows 19 Nov: " . $chRecv->count() . "\n";
foreach ($chRecv as $r) {
    $cols = (array) $r;
    echo "keys=" . implode(',', array_keys($cols)) . "\n";
    echo json_encode($r) . "\n";
}

echo "\nDONE\n";
