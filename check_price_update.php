<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECK PRICE UPDATE RESULTS ===\n\n";

// 1. Cek total item prices
$totalPrices = ItemPrice::count();
echo "Total item prices in database: {$totalPrices}\n";

// 2. Cek item prices yang baru diupdate (hari ini)
$today = now()->format('Y-m-d');
$recentPrices = ItemPrice::whereDate('updated_at', $today)->count();
echo "Item prices updated today: {$recentPrices}\n";

// 3. Cek beberapa item prices terbaru
echo "\n=== RECENT PRICE UPDATES ===\n";
$recentPriceUpdates = ItemPrice::with('item')
    ->whereDate('updated_at', $today)
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

foreach ($recentPriceUpdates as $price) {
    echo "Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type} | Updated: {$price->updated_at}\n";
}

// 4. Cek item yang tidak memiliki price
echo "\n=== ITEMS WITHOUT PRICES ===\n";
$itemsWithoutPrices = Item::where('status', 'active')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id');
    })
    ->limit(10)
    ->get();

foreach ($itemsWithoutPrices as $item) {
    echo "Item without price: {$item->name} (ID: {$item->id})\n";
}

// 5. Cek activity log untuk price updates
echo "\n=== RECENT PRICE UPDATE ACTIVITY LOGS ===\n";
$recentLogs = DB::table('activity_logs')
    ->where('module', 'item_prices')
    ->whereDate('created_at', $today)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($recentLogs as $log) {
    echo "Activity: {$log->description} | User: {$log->user_id} | Time: {$log->created_at}\n";
}

// 6. Cek struktur tabel item_prices
echo "\n=== ITEM_PRICES TABLE STRUCTURE ===\n";
$columns = DB::select("DESCRIBE item_prices");
foreach ($columns as $column) {
    echo "Column: {$column->Field} | Type: {$column->Type} | Null: {$column->Null} | Key: {$column->Key}\n";
}

// 7. Cek sample data item_prices
echo "\n=== SAMPLE ITEM_PRICES DATA ===\n";
$samplePrices = ItemPrice::with('item')
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

foreach ($samplePrices as $price) {
    echo "ID: {$price->id} | Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type} | Region: {$price->region_id} | Outlet: {$price->outlet_id}\n";
}

// 8. Cek item yang memiliki multiple prices
echo "\n=== ITEMS WITH MULTIPLE PRICES ===\n";
$itemsWithMultiplePrices = DB::table('item_prices')
    ->select('item_id', DB::raw('COUNT(*) as price_count'))
    ->groupBy('item_id')
    ->having('price_count', '>', 1)
    ->limit(5)
    ->get();

foreach ($itemsWithMultiplePrices as $item) {
    $itemName = Item::find($item->item_id)->name;
    echo "Item: {$itemName} (ID: {$item->item_id}) has {$item->price_count} prices\n";
}

// 9. Cek item yang hanya memiliki price type 'all'
echo "\n=== ITEMS WITH ONLY 'ALL' PRICE TYPE ===\n";
$itemsWithAllPriceOnly = ItemPrice::with('item')
    ->where('availability_price_type', 'all')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices as ip2')
              ->whereRaw('ip2.item_id = item_prices.item_id')
              ->where('ip2.availability_price_type', '!=', 'all');
    })
    ->limit(5)
    ->get();

foreach ($itemsWithAllPriceOnly as $price) {
    echo "Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type}\n";
}

// 10. Cek total active items vs items with prices
echo "\n=== PRICE COVERAGE STATISTICS ===\n";
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
echo "Coverage: " . round(($itemsWithPrices / $totalActiveItems) * 100, 2) . "%\n";

echo "\n=== CHECK COMPLETED ===\n"; 