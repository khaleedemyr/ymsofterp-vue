<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Stock opname SO-20261119-028 outlet 18 — tenderloin involved? ===\n";
$so = DB::table('outlet_stock_opnames')->where('id', 10554)->first();
if (!$so) {
    // try alternate
    foreach (['stock_opnames', 'outlet_food_stock_opnames'] as $t) {
        if (Schema::hasTable($t)) {
            $so = DB::table($t)->where('number', 'SO-20261119-028')->orWhere('id', 10554)->first();
            if ($so) { echo "found in {$t}\n"; break; }
        }
    }
}
echo "SO header: " . json_encode($so) . "\n";

if ($so) {
    $table = Schema::hasTable('outlet_stock_opname_items') ? 'outlet_stock_opname_items' : 'stock_opname_items';
    $items = DB::table($table)->where('stock_opname_id', $so->id)->where(function ($q) {
        $q->where('item_id', 52984);
        if (Schema::hasColumn($table, 'inventory_item_id')) {
            $q->orWhere('inventory_item_id', 5080);
        }
    })->get();
    echo "tenderloin SO lines: " . $items->count() . "\n";
    foreach ($items as $i) echo json_encode($i) . "\n";
}

echo "\n=== All stock_cut create/rollback for outlet 18 on 19 Nov (broader) ===\n";
$rows = DB::table('activity_logs')
    ->where('module', 'stock_cut')
    ->whereBetween('created_at', ['2026-11-19 00:00:00', '2026-11-20 02:00:00'])
    ->orderBy('id')
    ->get();
foreach ($rows as $r) {
    $od = $r->old_data ?? '';
    $desc = $r->description ?? '';
    if (strpos($desc, 'outlet 18') !== false || strpos($od, '"outlet_id": 18') !== false || strpos($od, '"outlet_id":18') !== false || preg_match('/outlet 18\b/', $desc)) {
        echo "{$r->created_at} {$r->activity_type} {$r->description} | {$r->old_data}\n";
    }
}

echo "DONE\n";
