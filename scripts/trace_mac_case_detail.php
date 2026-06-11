<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 22;

// Full anomaly count by module (SQL-based spike detection)
echo "=== SQL module breakdown (spike >=50% or mac<0) ===\n";
$rows = DB::select("
    SELECT h.reference_type, COUNT(*) as cnt
    FROM outlet_food_inventory_cost_histories h
    WHERE h.id_outlet = ?
      AND (
        h.mac < 0 OR h.new_cost < 0
        OR h.mac > 10000000
      )
    GROUP BY h.reference_type
    ORDER BY cnt DESC
", [$outletId]);
foreach ($rows as $r) {
    echo "  {$r->reference_type}: {$r->cnt}\n";
}

// Case: Adjustment 6747 Lettuce
echo "\n=== Case OSA 6747 (Lettuce) ===\n";
$adj = DB::table('outlet_food_inventory_adjustments')->where('id', 6747)->first();
echo json_encode($adj, JSON_PRETTY_PRINT) . "\n";
$items = DB::table('outlet_food_inventory_adjustment_items')->where('adjustment_id', 6747)->get();
echo "Items: " . json_encode($items, JSON_PRETTY_PRINT) . "\n";

$hist = DB::table('outlet_food_inventory_cost_histories')->where('reference_type', 'outlet_stock_adjustment')->where('reference_id', 6747)->get();
echo "Cost hist: " . json_encode($hist, JSON_PRETTY_PRINT) . "\n";

// Stock value vs last_cost mismatch
echo "\n=== Stocks where value/qty != last_cost_small (>|10% diff|) ===\n";
$stocks = DB::select("
    SELECT s.id_outlet, wo.name as wh, i.name as item,
           s.qty_small, s.value, s.last_cost_small,
           CASE WHEN s.qty_small > 0 THEN s.value / s.qty_small ELSE 0 END as implied_mac,
           ABS(CASE WHEN s.qty_small > 0 THEN s.value / s.qty_small ELSE 0 END - s.last_cost_small) as diff
    FROM outlet_food_inventory_stocks s
    JOIN warehouse_outlets wo ON wo.id = s.warehouse_outlet_id
    JOIN outlet_food_inventory_items ofii ON ofii.id = s.inventory_item_id
    JOIN items i ON i.id = ofii.item_id
    WHERE s.id_outlet = ?
      AND s.qty_small > 0
      AND s.last_cost_small > 0
    HAVING diff > s.last_cost_small * 0.1
    ORDER BY diff DESC
    LIMIT 15
", [$outletId]);
foreach ($stocks as $s) {
    echo "  {$s->wh} | {$s->item} | qty={$s->qty_small} value={$s->value} last_cost={$s->last_cost_small} implied={$s->implied_mac}\n";
}

// Serial receive Hands Glove header 260
echo "\n=== Case GSR-20260605-0035 (header 260) ===\n";
$items260 = DB::table('outlet_serial_receive_items')->where('header_id', 260)->get();
foreach ($items260 as $it) {
    echo "  {$it->item_id} qty={$it->qty} cost_small={$it->cost_small} wh={$it->warehouse_outlet_id}\n";
}

// Chain: IWT then adjustment Sauce Tomat
echo "\n=== Sauce Tomat MAC chain (Service vs Kitchen) ===\n";
$item = DB::table('items')->where('name', 'like', '%Sauce Tomat Botol%')->first();
if ($item) {
    $ofii = DB::table('outlet_food_inventory_items')->where('item_id', $item->id)->value('id');
    $hists = DB::table('outlet_food_inventory_cost_histories as h')
        ->join('warehouse_outlets as wo', 'wo.id', '=', 'h.warehouse_outlet_id')
        ->where('h.id_outlet', $outletId)
        ->where('h.inventory_item_id', $ofii)
        ->where('h.date', '>=', '2026-05-28')
        ->orderBy('h.date')->orderBy('h.id')
        ->limit(20)
        ->get(['h.date', 'wo.name', 'h.reference_type', 'h.reference_id', 'h.mac', 'h.new_cost', 'h.old_cost']);
    foreach ($hists as $h) {
        echo "  {$h->date} {$h->name} {$h->reference_type}#{$h->reference_id} mac={$h->mac} new={$h->new_cost}\n";
    }
}
