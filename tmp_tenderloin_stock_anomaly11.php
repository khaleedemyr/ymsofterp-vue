<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Rollback activity for outlet 18 on 19 Nov ===\n";
$rows = DB::table('activity_logs')
    ->where('module', 'stock_cut')
    ->whereBetween('created_at', ['2026-11-19 00:00:00', '2026-11-20 00:00:00'])
    ->where(function ($q) {
        $q->where('description', 'like', '%outlet 18%')
          ->orWhere('old_data', 'like', '%"outlet_id": 18%')
          ->orWhere('old_data', 'like', '%"outlet_id":18%');
    })
    ->orderBy('id')
    ->get();

foreach ($rows as $r) {
    echo "{$r->created_at} | {$r->activity_type} | {$r->description}\n";
    echo "  old={$r->old_data}\n";
    echo "  new={$r->new_data}\n\n";
}

echo "=== Does log 12015 still exist? details? cards? ===\n";
echo "log=" . json_encode(DB::table('stock_cut_logs')->where('id', 12015)->first()) . "\n";
echo "details=" . DB::table('stock_cut_details')->where('stock_cut_log_id', 12015)->count() . "\n";
echo "cards=" . DB::table('outlet_food_inventory_cards')->where('stock_cut_log_id', 12015)->count() . "\n";

echo "\n=== All stock_cut activity outlet 18 Nov 19 ===\n";
$rows2 = DB::table('activity_logs')
    ->where('module', 'stock_cut')
    ->whereBetween('created_at', ['2026-11-19 00:00:00', '2026-11-20 00:00:00'])
    ->where(function ($q) {
        $q->where('description', 'like', '%18%')
          ->orWhere('old_data', 'like', '%18%');
    })
    ->orderBy('id')
    ->get(['id','created_at','activity_type','description','old_data','new_data','user_id']);
foreach ($rows2 as $r) {
    echo "{$r->id} {$r->created_at} user={$r->user_id} {$r->activity_type} :: {$r->description}\n";
}

echo "\n=== Stock cut logs id around 12015-12032 ===\n";
$logs = DB::table('stock_cut_logs')
    ->where('outlet_id', 18)
    ->where('tanggal', '2026-11-19')
    ->get();
echo "current logs for date:\n";
foreach ($logs as $l) echo json_encode($l) . "\n";

// Check id gap - 12015 was deleted
$neighbors = DB::table('stock_cut_logs')->whereBetween('id', [12010, 12035])->orderBy('id')->get(['id','outlet_id','tanggal','type_filter','created_at','status']);
foreach ($neighbors as $l) echo json_encode($l) . "\n";

echo "\n=== Order items stock_cut flags: any still 0 for food 19 Nov after final cut? ===\n";
$qr = 'SH010';
$foodItemIds = DB::table('items')->whereIn('type', ['Food Asian', 'Food Western', 'Food'])->pluck('id');
$remaining = DB::table('order_items')
    ->where('kode_outlet', $qr)
    ->whereDate('created_at', '2026-11-19')
    ->whereIn('item_id', $foodItemIds)
    ->where('stock_cut', 0)
    ->count();
$cut = DB::table('order_items')
    ->where('kode_outlet', $qr)
    ->whereDate('created_at', '2026-11-19')
    ->whereIn('item_id', $foodItemIds)
    ->where('stock_cut', 1)
    ->count();
echo "food order_items stock_cut=0: {$remaining}, =1: {$cut}\n";

echo "\n=== Timing: when was 12015 rolled back vs beverages 12013 ===\n";
echo "beverages log 12013: " . json_encode(DB::table('stock_cut_logs')->where('id', 12013)->first()) . "\n";

echo "DONE\n";
