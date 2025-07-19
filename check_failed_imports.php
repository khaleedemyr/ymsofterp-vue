<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECK FAILED IMPORTS AND RECOMMENDATIONS ===\n\n";

// 1. Cek item yang tidak memiliki price (yang mungkin gagal diimport)
echo "=== ITEMS WITHOUT PRICES (NEED ATTENTION) ===\n";
$itemsWithoutPrices = Item::where('status', 'active')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id');
    })
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

echo "Total items without prices: " . $itemsWithoutPrices->count() . "\n\n";

foreach ($itemsWithoutPrices as $item) {
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Created: {$item->created_at}\n";
}

// 2. Cek item dengan harga 0 (mungkin error)
echo "\n=== ITEMS WITH ZERO PRICE (POSSIBLE ERRORS) ===\n";
$itemsWithZeroPrice = ItemPrice::with('item')
    ->where('price', 0)
    ->where('updated_at', '>=', now()->subDays(7))
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

foreach ($itemsWithZeroPrice as $price) {
    echo "Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type} | Updated: {$price->updated_at}\n";
}

// 3. Cek item yang baru dibuat (kemungkinan belum di-set harga)
echo "\n=== RECENTLY CREATED ITEMS (MIGHT NEED PRICING) ===\n";
$recentItems = Item::where('status', 'active')
    ->where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($recentItems as $item) {
    $hasPrice = ItemPrice::where('item_id', $item->id)->exists();
    $priceStatus = $hasPrice ? 'HAS PRICE' : 'NO PRICE';
    echo "Item: {$item->name} | Created: {$item->created_at} | Status: {$priceStatus}\n";
}

// 4. Rekomendasi untuk import berikutnya
echo "\n=== RECOMMENDATIONS FOR NEXT IMPORT ===\n";
echo "1. Pastikan semua item aktif memiliki harga\n";
echo "2. Periksa item dengan harga 0 apakah memang gratis atau error\n";
echo "3. Set harga untuk item yang baru dibuat\n";
echo "4. Gunakan template yang sudah diupdate dengan harga yang benar\n\n";

// 5. Generate list item yang perlu di-set harga
echo "=== ITEMS THAT NEED PRICING (FOR NEXT IMPORT) ===\n";
$itemsNeedingPricing = Item::where('status', 'active')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id');
    })
    ->select('id', 'sku', 'name', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

echo "Total items needing pricing: " . $itemsNeedingPricing->count() . "\n";
echo "Sample items for next import:\n";
foreach ($itemsNeedingPricing->take(10) as $item) {
    echo "- {$item->name} (ID: {$item->id}, SKU: {$item->sku})\n";
}

// 6. Cek template export untuk memastikan semua item tercover
echo "\n=== TEMPLATE COVERAGE CHECK ===\n";
$totalActiveItems = Item::where('status', 'active')->count();
$itemsWithPrices = Item::where('status', 'active')
    ->whereExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id');
    })
    ->count();

echo "Total active items: {$totalActiveItems}\n";
echo "Items with prices: {$itemsWithPrices}\n";
echo "Items without prices: " . ($totalActiveItems - $itemsWithPrices) . "\n";
echo "Coverage: " . round(($itemsWithPrices / $totalActiveItems) * 100, 2) . "%\n";

echo "\n=== CHECK COMPLETED ===\n"; 