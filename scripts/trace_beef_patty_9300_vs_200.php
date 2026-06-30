<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$itemId = (int) DB::table('items')->where('name', 'Beef Patty')->value('id');

echo "=== Beef Patty FO lines: price 9300 vs 200 (Jun 2026) ===\n\n";

foreach ([9300, 200] as $price) {
    $rows = DB::table('food_floor_order_items as ffoi')
        ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
        ->where('ffoi.item_id', $itemId)
        ->where('ffoi.price', $price)
        ->where('ffo.tanggal', '>=', '2026-06-01')
        ->select('ffo.tanggal', 'ffo.order_number', 'ffoi.qty', 'ffoi.unit', 'ffoi.subtotal', 'ffoi.updated_at')
        ->orderByDesc('ffo.tanggal')
        ->limit(3)
        ->get();

    echo "Price {$price} — sample:\n";
    foreach ($rows as $r) {
        echo "  {$r->tanggal} {$r->order_number} qty={$r->qty} {$r->unit} subtotal={$r->subtotal} updated={$r->updated_at}\n";
    }
    echo "\n";
}

$item = DB::table('items')->where('id', $itemId)->first();
$units = DB::table('units')->whereIn('id', [$item->small_unit_id, $item->medium_unit_id, $item->large_unit_id])->pluck('name', 'id');
echo "Units: small={$units[$item->small_unit_id]} medium={$units[$item->medium_unit_id]} large={$units[$item->large_unit_id]}\n";
echo "medium_conversion_qty={$item->medium_conversion_qty}\n";
echo "item_prices (all): " . DB::table('item_prices')->where('item_id', $itemId)->where('availability_price_type', 'all')->value('price') . "\n";
