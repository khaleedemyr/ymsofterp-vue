<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 18;
$whId = 41;
$invItemId = 5080;

echo "=== Waste card 3835043 full row + how saldo was derived ===\n";
$w = DB::table('outlet_food_inventory_cards')->where('id', 3835043)->first();
echo json_encode($w, JSON_PRETTY_PRINT) . "\n";

echo "\n=== Internal use waste controller pattern: last card vs stock for header 57781 ===\n";
$header = DB::table('outlet_internal_use_waste_headers')->where('id', 57781)->first();
echo "header=" . json_encode($header) . "\n";

// Check mushroom: before waste card, last card saldo 9760. Waste out 150. stored 7410.
// Implied stock before waste = 7410+150 = 7560. Drift before waste = 7560-9760 = -2200.

echo "\n=== Tenderloin: was there ANY stock writer between receive and cut? Check activity_logs ===\n";
if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
    $rows = DB::table('activity_logs')
        ->whereBetween('created_at', ['2026-11-19 11:00:00', '2026-11-19 22:36:00'])
        ->where(function ($q) {
            $q->where('module', 'like', '%stock%')
              ->orWhere('module', 'like', '%inventory%')
              ->orWhere('description', 'like', '%52984%')
              ->orWhere('description', 'like', '%Tenderloin%')
              ->orWhere('old_data', 'like', '%5080%')
              ->orWhere('new_data', 'like', '%5080%')
              ->orWhere('old_data', 'like', '%23315%')
              ->orWhere('new_data', 'like', '%23315%');
        })
        ->orderBy('id')
        ->limit(50)
        ->get();
    echo "hits=" . $rows->count() . "\n";
    foreach ($rows as $r) {
        echo substr(json_encode($r), 0, 450) . "\n\n";
    }
}

echo "\n=== Check outlet_food_inventory_cost_histories for non-receive types 19 Nov item ===\n";
$ch = DB::table('outlet_food_inventory_cost_histories')
    ->where('inventory_item_id', $invItemId)
    ->where('id_outlet', $outletId)
    ->where('warehouse_outlet_id', $whId)
    ->whereDate('date', '2026-11-19')
    ->get();
foreach ($ch as $r) echo json_encode($r) . "\n";

echo "\n=== Compare waste JUMP size to same-day stock cut need for drifted items ===\n";
// Already know they match. Document tenderloin uniquely had no waste.

echo "\n=== Serial receive processInventory: does it lock stock? Race with parallel receive? ===\n";
// Only one receive for tenderloin that day.

echo "\n=== Check if Nov 18 stock cut detail after matches stock that receive would have seen ===\n";
$d18 = DB::table('stock_cut_details')->where('id', 1365387)->first();
echo "Nov18 after=" . $d18->stock_after_small . "\n";

echo "\n=== Hypothesis: internal waste for OTHER items at 22:08 - could a bug touch wrong inventory_item? ===\n";
$wasteDetails = DB::table('outlet_internal_use_waste_details')
    ->where('header_id', 57781)
    ->get();
echo "waste 57781 lines=" . $wasteDetails->count() . "\n";
foreach ($wasteDetails as $d) {
    if ((int)$d->item_id === 52984 || (float)($d->qty ?? $d->qty_small ?? 0) == 1200 || abs((float)($d->qty ?? 0)*150 - 1200) < 0.1) {
        echo "SUSPICIOUS: " . json_encode($d) . "\n";
    }
}
// show if tenderloin in any waste 19 Nov
$w19 = DB::table('outlet_internal_use_waste_details as d')
    ->join('outlet_internal_use_waste_headers as h', 'h.id', '=', 'd.header_id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.date', '2026-11-19')
    ->where('d.item_id', 52984)
    ->get();
echo "tenderloin in waste 19 Nov: " . $w19->count() . "\n";
foreach ($w19 as $r) echo json_encode($r) . "\n";

echo "\nDONE\n";
