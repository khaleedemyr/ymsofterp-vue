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

echo "=== A) Card 3837229 (19 Nov jump) + neighbors ===\n";
$around = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween('id', [3830890, 3837240])
    ->orderBy('id')
    ->get();
foreach ($around as $c) {
    echo sprintf(
        "id=%d date=%s created=%s ref=%s#%s in_s=%s out_s=%s saldo_s=%s/%s desc=%s\n",
        $c->id, $c->date, $c->created_at ?? '-', $c->reference_type, $c->reference_id,
        $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, $c->saldo_qty_medium,
        substr((string)$c->description, 0, 80)
    );
}

echo "\n=== B) ANY cards for item on 19 Nov across ALL warehouses ===\n";
$allWh = DB::table('outlet_food_inventory_cards as c')
    ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
    ->leftJoin('warehouse_outlets as wo', 'c.warehouse_outlet_id', '=', 'wo.id')
    ->where('fi.item_id', $itemId)
    ->where('c.id_outlet', $outletId)
    ->whereDate('c.date', '2026-11-19')
    ->orderBy('c.id')
    ->select('c.*', 'wo.name as wh_name')
    ->get();
foreach ($allWh as $c) {
    echo sprintf(
        "id=%d wh=%s(%s) ref=%s#%s in=%s out=%s saldo_s=%s created=%s\n",
        $c->id, $c->wh_name, $c->warehouse_outlet_id, $c->reference_type, $c->reference_id,
        $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, $c->created_at ?? '-'
    );
}

echo "\n=== C) stock_cut_logs / variances around 19 & 25 Nov ===\n";
foreach (['stock_cut_logs', 'stock_cut_variances', 'outlet_stock_cut_logs'] as $table) {
    if (!Schema::hasTable($table)) {
        echo "no table {$table}\n";
        continue;
    }
    $cols = Schema::getColumnListing($table);
    echo "table {$table} cols=" . implode(',', $cols) . "\n";
}

if (Schema::hasTable('stock_cut_logs')) {
    $q = DB::table('stock_cut_logs')->where('outlet_id', $outletId);
    // flexible column names
    $cols = Schema::getColumnListing('stock_cut_logs');
    if (in_array('item_id', $cols)) $q->where('item_id', $itemId);
    if (in_array('inventory_item_id', $cols)) $q->where('inventory_item_id', $invItemId);
    if (in_array('warehouse_outlet_id', $cols)) $q->where('warehouse_outlet_id', $whId);
    $rows = $q->where(function ($qq) use ($cols) {
        if (in_array('cut_date', $cols)) {
            $qq->whereBetween('cut_date', ['2026-11-17', '2026-11-27']);
        } elseif (in_array('date', $cols)) {
            $qq->whereBetween('date', ['2026-11-17', '2026-11-27']);
        } else {
            $qq->whereBetween('created_at', ['2026-11-17', '2026-11-28']);
        }
    })->orderBy('id')->get();
    echo "stock_cut_logs rows=" . $rows->count() . "\n";
    foreach ($rows as $r) {
        echo json_encode($r) . "\n";
    }
}

if (Schema::hasTable('stock_cut_variances')) {
    $cols = Schema::getColumnListing('stock_cut_variances');
    $q = DB::table('stock_cut_variances')->where('outlet_id', $outletId);
    if (in_array('item_id', $cols)) $q->where('item_id', $itemId);
    if (in_array('warehouse_outlet_id', $cols)) $q->where('warehouse_outlet_id', $whId);
    $rows = $q->where(function ($qq) use ($cols) {
        if (in_array('sale_date', $cols)) {
            $qq->whereBetween('sale_date', ['2026-11-17', '2026-11-27']);
        } elseif (in_array('cut_date', $cols)) {
            $qq->whereBetween('cut_date', ['2026-11-17', '2026-11-27']);
        } else {
            $qq->whereBetween('created_at', ['2026-11-17', '2026-11-28']);
        }
    })->orderBy('id')->get();
    echo "stock_cut_variances rows=" . $rows->count() . "\n";
    foreach ($rows as $r) {
        echo json_encode($r) . "\n";
    }
}

echo "\n=== D) Card description shortfall details 25 Nov ===\n";
$c25 = DB::table('outlet_food_inventory_cards')->where('id', 3914569)->first();
echo json_encode($c25, JSON_PRETTY_PRINT) . "\n";

echo "\n=== E) Reconstruct expected saldo chain Nov 17-26 ===\n";
$cards = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-17', '2026-11-26'])
    ->orderBy('date')->orderBy('id')
    ->get();
$running = null;
foreach ($cards as $c) {
    if ($running === null) {
        // back-calc opening from first card
        $running = (float)$c->saldo_qty_small - (float)$c->in_qty_small + (float)$c->out_qty_small;
        echo "implied_open_before_first={$running}\n";
    }
    $running = $running + (float)$c->in_qty_small - (float)$c->out_qty_small;
    $diff = (float)$c->saldo_qty_small - $running;
    echo sprintf(
        "id=%d %s in=%s out=%s expected_saldo=%s stored_saldo=%s diff=%s\n",
        $c->id, substr((string)$c->date,0,10), $c->in_qty_small, $c->out_qty_small,
        $running, $c->saldo_qty_small, $diff
    );
    // after detecting jump, reset running to stored to continue chain analysis
    if (abs($diff) > 0.01) {
        echo "  -> RESET running to stored due to jump\n";
        $running = (float)$c->saldo_qty_small;
    }
}

echo "\n=== F) Check related portion items / whole beef cards same days Kitchen ===\n";
$relatedIds = [52925, 52985, 53019, 53026]; // whole, 250, 1000, 200
foreach ($relatedIds as $rid) {
    $rows = DB::table('outlet_food_inventory_cards as c')
        ->join('outlet_food_inventory_items as fi', 'c.inventory_item_id', '=', 'fi.id')
        ->join('items as i', 'fi.item_id', '=', 'i.id')
        ->where('fi.item_id', $rid)
        ->where('c.id_outlet', $outletId)
        ->where('c.warehouse_outlet_id', $whId)
        ->whereIn(DB::raw('DATE(c.date)'), ['2026-11-19', '2026-11-25', '2026-11-26'])
        ->orderBy('c.date')->orderBy('c.id')
        ->select('c.id','c.date','c.reference_type','c.reference_id','c.in_qty_small','c.out_qty_small','c.saldo_qty_small','c.saldo_qty_medium','i.name','c.description')
        ->get();
    if ($rows->isEmpty()) continue;
    echo "-- item {$rid} --\n";
    foreach ($rows as $r) {
        echo sprintf(
            "  %s id=%d ref=%s#%s in=%s out=%s saldo=%s/%s %s\n",
            substr((string)$r->date,0,10), $r->id, $r->reference_type, $r->reference_id,
            $r->in_qty_small, $r->out_qty_small, $r->saldo_qty_small, $r->saldo_qty_medium,
            substr((string)$r->description,0,50)
        );
    }
}

echo "\n=== G) BOM / menu engineering for 25 Nov (order sales of menus using item 52984) ===\n";
// Find menu items that BOM to 52984
$bomTables = [];
foreach (['item_bom', 'item_boms', 'menu_boms', 'recipe_items'] as $t) {
    if (Schema::hasTable($t)) $bomTables[] = $t;
}
echo "bom tables: " . implode(',', $bomTables) . "\n";
