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

echo "=== 1) Cards BETWEEN receive 3830900 and cut 3837229 by created_at (any warehouse / maybe wrong inv) ===\n";
$between = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('created_at', '>', '2026-11-19 11:00:46')
    ->where('created_at', '<', '2026-11-19 22:36:03')
    ->orderBy('created_at')
    ->get();
echo "between_count=" . $between->count() . "\n";
foreach ($between as $c) {
    echo json_encode($c) . "\n";
}

echo "\n=== 2) ALL card types for inv 5080 outlet 18 on 19 Nov by id range wider ===\n";
$wide = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where(function ($q) {
        $q->whereDate('date', '2026-11-19')
          ->orWhereBetween('created_at', ['2026-11-19 00:00:00', '2026-11-19 23:59:59']);
    })
    ->orderBy('id')
    ->get();
foreach ($wide as $c) {
    echo sprintf(
        "id=%d wh=%s date=%s created=%s ref=%s#%s in=%s out=%s saldo=%s desc=%s\n",
        $c->id, $c->warehouse_outlet_id, $c->date, $c->created_at, $c->reference_type, $c->reference_id,
        $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, substr((string)$c->description,0,70)
    );
}

echo "\n=== 3) Internal transfers / waste / adjustments on 19 Nov involving item ===\n";
foreach ([
    'outlet_food_inventory_cards' => null,
] as $t => $_) {
    $refs = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->whereDate('date', '>=', '2026-11-18')
        ->whereDate('date', '<=', '2026-11-20')
        ->whereNotIn('reference_type', ['serial_receive', 'order_items'])
        ->orderBy('id')
        ->get();
    echo "non-sr/order cards 18-20 Nov: " . $refs->count() . "\n";
    foreach ($refs as $c) {
        echo sprintf(
            "id=%d date=%s wh=%s ref=%s#%s in=%s out=%s saldo=%s %s\n",
            $c->id, $c->date, $c->warehouse_outlet_id, $c->reference_type, $c->reference_id,
            $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, substr((string)$c->description,0,60)
        );
    }
}

echo "\n=== 4) Check if serial receive wrote saldo from LAST CARD vs STOCK ===\n";
// Last card before receive
$before = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->where('id', '<', 3830900)
    ->orderByDesc('id')
    ->first();
echo "last card before receive:\n";
echo sprintf(
    "id=%d date=%s saldo=%s/%s created=%s\n",
    $before->id, $before->date, $before->saldo_qty_small, $before->saldo_qty_medium, $before->created_at
);
echo "receive card saldo should be last+1500 = " . ((float)$before->saldo_qty_small + 1500) . " stored=" . 4350 . "\n";

echo "\n=== 5) stock_cut_detail for log 12032 item 52984 ===\n";
foreach (['stock_cut_details', 'stock_cut_log_details', 'outlet_stock_cut_details'] as $t) {
    if (Schema::hasTable($t)) {
        echo "has {$t}: " . implode(',', Schema::getColumnListing($t)) . "\n";
    }
}

// find detail table used
$detailTable = null;
foreach (['stock_cut_details', 'stock_cut_log_details'] as $t) {
    if (Schema::hasTable($t)) { $detailTable = $t; break; }
}
if ($detailTable) {
    $cols = Schema::getColumnListing($detailTable);
    $q = DB::table($detailTable);
    if (in_array('stock_cut_log_id', $cols)) $q->where('stock_cut_log_id', 12032);
    if (in_array('item_id', $cols)) $q->where('item_id', $itemId);
    elseif (in_array('inventory_item_id', $cols)) $q->where('inventory_item_id', $invItemId);
    $rows = $q->get();
    echo "details for cut 12032:\n";
    foreach ($rows as $r) echo json_encode($r) . "\n";

    // also 25 Nov
    $q2 = DB::table($detailTable);
    if (in_array('stock_cut_log_id', $cols)) $q2->where('stock_cut_log_id', 12308);
    if (in_array('item_id', $cols)) $q2->where('item_id', $itemId);
    elseif (in_array('inventory_item_id', $cols)) $q2->where('inventory_item_id', $invItemId);
    echo "details for cut 12308 (25 Nov):\n";
    foreach ($q2->get() as $r) echo json_encode($r) . "\n";
}

echo "\n=== 6) Scan laravel logs for Stock sebelum dipotong item 5080 on 19 Nov ===\n";
$logDir = storage_path('logs');
$files = glob($logDir . '/laravel*.log');
rsort($files);
$found = 0;
foreach (array_slice($files, 0, 15) as $file) {
    $fh = fopen($file, 'r');
    if (!$fh) continue;
    $buf = '';
    while (!feof($fh)) {
        $line = fgets($fh);
        if ($line === false) break;
        if (strpos($line, '2026-11-19') === false && strpos($buf, '2026-11-19') === false) {
            // keep scanning; logs may be multiline JSON
        }
        if (strpos($line, 'inventory_item_id') !== false && strpos($line, '5080') !== false) {
            echo basename($file) . ": " . substr($line, 0, 500) . "\n";
            $found++;
            if ($found > 30) break 2;
        }
        if (strpos($line, 'Stock sebelum dipotong') !== false || strpos($line, 'Stock cut result') !== false) {
            // peek next few lines maybe
        }
    }
    fclose($fh);
}
echo "log_hits={$found}\n";

echo "\n=== 7) Check audit / activity for stock id of this item ===\n";
$stock = DB::table('outlet_food_inventory_stocks')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->first();
echo "stock_row=" . json_encode($stock) . "\n";

// Look for portioning / production that consumes 150gr from whole
echo "\n=== 8) Production / portion IN of 52984 on 19 Nov anywhere ===\n";
$prod = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->whereDate('date', '2026-11-19')
    ->get();
echo "already listed. Checking WHOLE beef OUT on 19 Nov that might correlate:\n";
$wholeInv = DB::table('outlet_food_inventory_items')->where('item_id', 52925)->value('id');
echo "whole inv_item_id={$wholeInv}\n";
if ($wholeInv) {
    $w = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $wholeInv)
        ->where('id_outlet', $outletId)
        ->whereBetween(DB::raw('DATE(date)'), ['2026-11-18','2026-11-20'])
        ->orderBy('id')
        ->get(['id','date','created_at','reference_type','reference_id','warehouse_outlet_id','in_qty_small','out_qty_small','saldo_qty_small','description']);
    foreach ($w as $c) {
        echo sprintf(
            "id=%d %s created=%s wh=%s ref=%s in=%s out=%s saldo=%s %s\n",
            $c->id, $c->date, $c->created_at, $c->warehouse_outlet_id, $c->reference_type,
            $c->in_qty_small, $c->out_qty_small, $c->saldo_qty_small, substr((string)$c->description,0,50)
        );
    }
}

echo "\n=== 9) Was stock cut run TWICE on 19 Nov for food? ===\n";
$cuts = DB::table('stock_cut_logs')
    ->where('outlet_id', $outletId)
    ->where('tanggal', '2026-11-19')
    ->get();
foreach ($cuts as $c) echo json_encode($c) . "\n";

// Check if any deleted cards or soft deletes
if (Schema::hasColumn('outlet_food_inventory_cards', 'deleted_at')) {
    $del = DB::table('outlet_food_inventory_cards')
        ->where('inventory_item_id', $invItemId)
        ->where('id_outlet', $outletId)
        ->whereNotNull('deleted_at')
        ->whereDate('date', '2026-11-19')
        ->get();
    echo "soft-deleted cards 19 Nov: " . $del->count() . "\n";
} else {
    echo "no deleted_at on cards\n";
}

echo "\n=== 10) Value math on jump card ===\n";
// After receive: saldo_value 1870497.72 for 4350g → cost ~430
// After cut: out 1200 * 430 = 516000 ≈ value_out 515999.40
// saldo_value 838499.03 for 1950g → 838499/1950 ≈ 430
// If correctly 3150 after cut: value would be ~1354500
// Jump value drop: 1870497 - 515999 = 1354498 expected saldo_value, but stored 838499
// Extra value drop: 1354498 - 838499 = 515999 ≈ another 1200g at cost
echo "value confirms another ~1200g disappeared from stock without card\n";

echo "\n=== 11) Search cards with out=1200 same day other outlets? ===\n";
$same = DB::table('outlet_food_inventory_cards')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('out_qty_small', 1200)
    ->whereBetween(DB::raw('DATE(date)'), ['2026-11-18','2026-11-20'])
    ->get(['id','date','created_at','warehouse_outlet_id','reference_type','stock_cut_log_id','out_qty_small','saldo_qty_small','description']);
foreach ($same as $c) echo json_encode($c) . "\n";
