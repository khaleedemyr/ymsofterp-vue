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

echo "=== A) Serial receive 22872 item qty/unit conversion check ===\n";
$item = DB::table('items')->where('id', $itemId)->first();
$sri = DB::table('outlet_serial_receive_items')->where('header_id', 22872)->where('item_id', $itemId)->first();
echo "unit_id on receive item={$sri->unit_id} qty={$sri->qty}\n";
echo "item small_unit={$item->small_unit_id} med={$item->medium_unit_id} large={$item->large_unit_id} sc={$item->small_conversion_qty}\n";
echo "expected qty_small = qty_medium * sc = " . ((float)$sri->qty * (float)$item->small_conversion_qty) . "\n";

echo "\n=== B) Was stock cut 12032 re-run? Check multiple details / cards for same log ===\n";
$cardsForLog = DB::table('outlet_food_inventory_cards')
    ->where('stock_cut_log_id', 12032)
    ->where('inventory_item_id', $invItemId)
    ->get();
echo "cards for log 12032 item: " . $cardsForLog->count() . "\n";
$detailsForLog = DB::table('stock_cut_details')
    ->where('stock_cut_log_id', 12032)
    ->where('item_id', $itemId)
    ->get();
echo "details for log 12032 item: " . $detailsForLog->count() . "\n";
foreach ($detailsForLog as $d) echo json_encode($d) . "\n";

echo "\n=== C) stock_cut_logs history for 19 Nov food - updated_at vs created ===\n";
$log = DB::table('stock_cut_logs')->where('id', 12032)->first();
echo json_encode($log) . "\n";

echo "\n=== D) Any inventory_serial_movements OUT for this item 19 Nov ===\n";
if (Schema::hasTable('inventory_serial_movements')) {
    $m = DB::table('inventory_serial_movements')
        ->where('item_id', $itemId)
        ->where(function ($q) use ($outletId) {
            if (Schema::hasColumn('inventory_serial_movements', 'outlet_id')) {
                $q->where('outlet_id', $outletId);
            }
        })
        ->whereBetween('moved_at', ['2026-11-18 23:00:00', '2026-11-19 22:36:03'])
        ->orderBy('id')
        ->get();
    echo "movements=" . $m->count() . "\n";
    foreach ($m as $row) echo json_encode($row) . "\n";
}

echo "\n=== E) Reconstruct: after 18 Nov cut stock=2850. What could yield 3150 at 19 cut? ===\n";
echo "Path1: stock stayed 2850, receive +1500 => 4350 (but cut saw 3150) IMPLIES -1200 after receive\n";
echo "Path2: stock reduced -1200 before receive to 1650, receive +1500 => 3150 (cut matches) IMPLIES silent -1200 after 18 cut before receive\n";
echo "Path3: receive stock update used wrong qty (+300?) 2850+300=3150 — check if unit confused\n";

// Check if any OTHER process writes to outlet_food_inventory_stocks without cards
// Look at updated_at patterns - we can't see history. Check activity_log if exists.
foreach (['activity_log', 'audits', 'inventory_audits', 'stock_audits'] as $t) {
    if (Schema::hasTable($t)) {
        echo "has table {$t}\n";
        $cols = Schema::getColumnListing($t);
        echo "  cols: " . implode(',', array_slice($cols, 0, 20)) . "\n";
    }
}

echo "\n=== F) Check barcode/portion production tables ===\n";
foreach (['outlet_productions', 'outlet_production_items', 'food_productions', 'item_conversions', 'outlet_item_conversions', 'warehouse_productions'] as $t) {
    if (Schema::hasTable($t)) echo "has {$t}\n";
}

echo "\n=== G) Cards with created_at between 18 cut and 19 cut for THIS stock row's item - verify none ===\n";
$gap = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('created_at', '>', '2026-11-18 23:07:58')
    ->where('created_at', '<', '2026-11-19 22:36:03')
    ->orderBy('created_at')
    ->get(['id','date','created_at','reference_type','reference_id','in_qty_small','out_qty_small','saldo_qty_small']);
echo "gap cards=" . $gap->count() . "\n";
foreach ($gap as $c) echo json_encode($c) . "\n";

echo "\n=== H) Compare receive card saldo_value math vs stock-based ===\n";
$recv = DB::table('outlet_food_inventory_cards')->where('id', 3830900)->first();
echo "receive in=1500 saldo=4350 value_in={$recv->value_in} saldo_value={$recv->saldo_value}\n";
echo "implied prior value = saldo_value - value_in = " . ((float)$recv->saldo_value - (float)$recv->value_in) . "\n";
$prev = DB::table('outlet_food_inventory_cards')->where('id', 3826624)->first();
echo "prev card saldo_value={$prev->saldo_value} saldo={$prev->saldo_qty_small}\n";

echo "\n=== I) Sales/engineering breakdown 19 Nov for BOM material 52984 ===\n";
// Try to mirror stock cut: order_items on 2026-11-19 for outlet 18 food menus with this BOM
$bomParents = DB::table('item_bom')
    ->where('material_item_id', $itemId)
    ->where(function ($q) {
        $q->whereNull('stock_cut')->orWhere('stock_cut', 0);
    })
    ->get();
$parentIds = $bomParents->pluck('item_id')->all();
$bomMap = [];
foreach ($bomParents as $b) {
    $bomMap[$b->item_id] = (float)$b->qty;
}
echo "parent menus: " . implode(',', $parentIds) . "\n";

// Find POS order table
$orderTable = null;
foreach (['order_items', 'pos_order_items', 'outlet_order_items', 'transactions_items'] as $t) {
    if (Schema::hasTable($t)) { echo "has {$t}\n"; }
}

// Stock cut likely uses a specific table - search in controller
echo "\n=== J) MAC at receive: if stock qty_lama were 2850 vs 1650 ===\n";
// From cost history mac=429.9995 after receive - nearly 430, not discriminative

echo "\n=== K) Check serial_receive item updated_at 2026-11-24 — was qty edited later? ===\n";
echo json_encode($sri) . "\n";
// Check if there are multiple serial receive cards for same header (reprocess)
$srCards = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'serial_receive')
    ->where('reference_id', 22872)
    ->where('inventory_item_id', $invItemId)
    ->get();
echo "cards for serial_receive 22872: " . $srCards->count() . "\n";
foreach ($srCards as $c) echo json_encode($c) . "\n";

// Rollback cards?
$rb = DB::table('outlet_food_inventory_cards')
    ->where('reference_type', 'serial_receive_rollback')
    ->where('reference_id', 22872)
    ->get();
echo "rollback cards: " . $rb->count() . "\n";

echo "\n=== L) Direct SQL: any OTHER warehouse stock rows for this inv item outlet 18 ===\n";
$stocks = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->get();
foreach ($stocks as $s) echo json_encode($s) . "\n";

echo "\n=== M) Hypothesis: stock cut for FOOD on 19 Nov processed item twice in loop before aggregating? ===\n";
// detail qty_small is theoretical full BOM = 1200, and only one detail row. Aggregation OK.
// But did stock get decremented elsewhere in same request for modifiers?
$modDetails = DB::table('stock_cut_details')
    ->where('stock_cut_log_id', 12032)
    ->where('item_id', $itemId)
    ->get();
echo "only one detail. Checking if modifiers also use same material in same cut with separate warehouse keys...\n";

echo "\nDONE\n";
