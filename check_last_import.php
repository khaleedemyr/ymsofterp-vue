<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECK LAST IMPORT DETAILS ===\n\n";

// 1. Cek activity log terakhir untuk price updates
echo "=== LAST PRICE UPDATE ACTIVITY LOGS ===\n";
$lastLogs = DB::table('activity_logs')
    ->where('module', 'item_prices')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($lastLogs as $log) {
    echo "Time: {$log->created_at} | User: {$log->user_id} | Activity: {$log->description}\n";
}

// 2. Cek item prices yang diupdate dalam 24 jam terakhir
echo "\n=== ITEM PRICES UPDATED IN LAST 24 HOURS ===\n";
$recentUpdates = ItemPrice::with('item')
    ->where('updated_at', '>=', now()->subHours(24))
    ->orderBy('updated_at', 'desc')
    ->limit(20)
    ->get();

foreach ($recentUpdates as $price) {
    echo "Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type} | Updated: {$price->updated_at}\n";
}

// 3. Cek item yang tidak memiliki price (yang mungkin gagal diimport)
echo "\n=== ITEMS WITHOUT PRICES (POSSIBLE FAILED IMPORTS) ===\n";
$itemsWithoutPrices = Item::where('status', 'active')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('item_prices')
              ->whereRaw('item_prices.item_id = items.id');
    })
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($itemsWithoutPrices as $item) {
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Created: {$item->created_at}\n";
}

// 4. Cek item yang memiliki price 0 (mungkin error)
echo "\n=== ITEMS WITH ZERO PRICE ===\n";
$itemsWithZeroPrice = ItemPrice::with('item')
    ->where('price', 0)
    ->where('updated_at', '>=', now()->subHours(24))
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

foreach ($itemsWithZeroPrice as $price) {
    echo "Item: {$price->item->name} | Price: {$price->price} | Type: {$price->availability_price_type} | Updated: {$price->updated_at}\n";
}

// 5. Cek item yang memiliki multiple prices untuk region yang sama
echo "\n=== ITEMS WITH DUPLICATE REGION PRICES ===\n";
$duplicateRegionPrices = DB::table('item_prices')
    ->select('item_id', 'region_id', DB::raw('COUNT(*) as count'))
    ->whereNotNull('region_id')
    ->groupBy('item_id', 'region_id')
    ->having('count', '>', 1)
    ->limit(5)
    ->get();

foreach ($duplicateRegionPrices as $duplicate) {
    $itemName = Item::find($duplicate->item_id)->name;
    echo "Item: {$itemName} | Region: {$duplicate->region_id} | Count: {$duplicate->count}\n";
}

// 6. Cek log Laravel untuk error import
echo "\n=== CHECKING LARAVEL LOG FOR IMPORT ERRORS ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -100); // Ambil 100 baris terakhir
    
    foreach ($recentLines as $line) {
        if (strpos($line, 'PriceUpdateImport') !== false || 
            strpos($line, 'importPriceUpdate') !== false ||
            strpos($line, 'ERROR') !== false) {
            echo trim($line) . "\n";
        }
    }
}

echo "\n=== CHECK COMPLETED ===\n"; 