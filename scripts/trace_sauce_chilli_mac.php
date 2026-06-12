<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FoodGrLastPurchaseForItem;
use Illuminate\Support\Facades\DB;

$itemId = 52537;
$item = DB::table('items')->where('id', $itemId)->first();
echo "=== Item {$item->name} (id={$itemId}) ===\n";
$units = DB::table('units')->whereIn('id', [$item->small_unit_id, $item->medium_unit_id, $item->large_unit_id])->pluck('name', 'id');
echo 'small=' . ($units[$item->small_unit_id] ?? '-') . ' medium=' . ($units[$item->medium_unit_id] ?? '-') . ' large=' . ($units[$item->large_unit_id] ?? '-') . "\n";
echo "conv small={$item->small_conversion_qty} medium={$item->medium_conversion_qty}\n";

$gr = FoodGrLastPurchaseForItem::lastLine($itemId);
echo 'GR last: ' . json_encode($gr) . "\n";
if ($gr) {
    $expectedMacSmall = $gr['cost_small'];
    $expectedMacMedium = $gr['cost_medium'];
    $expectedMacLarge = $gr['cost_large'];
    echo "Expected MAC small (per ml): {$expectedMacSmall}\n";
    echo "Expected MAC medium (per bottle?): {$expectedMacMedium}\n";
    echo "Expected MAC large: {$expectedMacLarge}\n";
}

$inv = DB::table('outlet_food_inventory_items')->where('item_id', $itemId)->first();
if (! $inv) {
    echo "No inventory item\n";
    exit(0);
}

echo "\n=== Stocks with anomalous last_cost_small (> 1000) ===\n";
$stocks = DB::table('outlet_food_inventory_stocks as s')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 's.id_outlet')
    ->where('s.inventory_item_id', $inv->id)
    ->where('s.last_cost_small', '>', 1000)
    ->orderByDesc('s.last_cost_small')
    ->get(['s.*', 'o.nama_outlet']);

foreach ($stocks as $s) {
    $ratio = $gr && $gr['cost_small'] > 0 ? round($s->last_cost_small / $gr['cost_small'], 1) : '-';
    echo "outlet={$s->id_outlet} {$s->nama_outlet} wh={$s->warehouse_outlet_id} last_cost_small={$s->last_cost_small} ratio_vs_gr_small={$ratio}x qty_small={$s->qty_small}\n";
}

echo "\n=== Cost history MAC anomalies ===\n";
$hist = DB::table('outlet_food_inventory_cost_histories as h')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.id_outlet')
    ->where('h.inventory_item_id', $inv->id)
    ->where(function ($q) {
        $q->where('h.mac', '>', 1000)->orWhere('h.new_cost', '>', 1000);
    })
    ->orderByDesc('h.id')
    ->limit(20)
    ->get(['h.id', 'h.id_outlet', 'o.nama_outlet', 'h.mac', 'h.new_cost', 'h.old_cost', 'h.date', 'h.reference_type']);

foreach ($hist as $h) {
    echo "id={$h->id} outlet={$h->id_outlet} {$h->nama_outlet} mac={$h->mac} new_cost={$h->new_cost} date={$h->date} ref={$h->reference_type}\n";
}

// outlet 7 from draft 28583
echo "\n=== Outlet 7 detail ===\n";
$s7 = DB::table('outlet_food_inventory_stocks')->where('inventory_item_id', $inv->id)->where('id_outlet', 7)->first();
echo json_encode($s7) . "\n";
