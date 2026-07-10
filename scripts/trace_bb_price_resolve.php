<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 54667;

echo "=== item_prices rows ===\n";
foreach (DB::table('item_prices')->where('item_id', $itemId)->orderBy('id')->get() as $r) {
    echo "id={$r->id} type={$r->availability_price_type} region={$r->region_id} outlet={$r->outlet_id} price={$r->price} mode=" . ($r->pricing_mode ?? '-') . " updated={$r->updated_at}\n";
}

echo "\n=== resolve prices outlet 18 region 1 ===\n";
echo 'Pack: ' . FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Pack', 1, '18') . "\n";
echo 'Recipe: ' . FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Recipe', 1, '18') . "\n";
echo 'medium only: ' . FloorOrderItemPriceResolver::resolveMediumUnitPrice($itemId, 1, '18') . "\n";

echo "\n=== item master unit cols ===\n";
$item = DB::table('items')->where('id', $itemId)->first();
echo "items.unit column: " . ($item->unit ?? 'null') . "\n";
echo "medium_conversion_qty: {$item->medium_conversion_qty}\n";
foreach (['small_unit_id', 'medium_unit_id', 'large_unit_id'] as $col) {
    $uid = $item->$col;
    $uname = DB::table('units')->where('id', $uid)->value('name');
    echo "{$col}={$uid} ({$uname})\n";
}

$tier = FloorOrderItemPriceResolver::detectUnitTier($item, 'Pack');
echo "detectUnitTier(Pack)={$tier}\n";

// Simulate what API by-fo-schedule returns
$itemModel = \App\Models\Item::with(['category', 'mediumUnit', 'smallUnit', 'largeUnit'])->find($itemId);
$roundedPrice = FloorOrderItemPriceResolver::resolveMediumUnitPrice($itemId, 1, '18', $itemModel);
$arr = array_merge($itemModel->toArray(), ['price' => $roundedPrice]);
echo "\nAPI tab item fields:\n";
echo "  unit_medium_name: " . ($arr['unit_medium_name'] ?? ($itemModel->mediumUnit->name ?? '-')) . "\n";
echo "  unit (from toArray): " . ($arr['unit'] ?? 'n/a') . "\n";
echo "  price from API: {$roundedPrice}\n";

// What if frontend accidentally used wrong unit
foreach (['Pack', 'Recipe', 'recipe', '', '-', null] as $u) {
    echo "resolveLineUnitPrice unit=" . json_encode($u) . ' => ' . FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, $u, 1, '18') . "\n";
}
