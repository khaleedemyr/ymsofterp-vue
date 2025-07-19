<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG FRYALL IMPORT ===\n\n";

// 1. Cek item Fryall
$fryall = Item::where('id', 52360)
    ->orWhere('name', 'Fryall')
    ->orWhere('sku', 'GC-20250620-605')
    ->first();

if ($fryall) {
    echo "=== FRYALL ITEM INFO ===\n";
    echo "ID: {$fryall->id}\n";
    echo "Name: {$fryall->name}\n";
    echo "SKU: {$fryall->sku}\n";
    echo "Status: {$fryall->status}\n";
    echo "Created: {$fryall->created_at}\n";
    echo "Updated: {$fryall->updated_at}\n\n";

    // 2. Cek prices untuk Fryall
    echo "=== FRYALL PRICES ===\n";
    $fryallPrices = ItemPrice::where('item_id', $fryall->id)->get();
    
    if ($fryallPrices->count() > 0) {
        foreach ($fryallPrices as $price) {
            echo "Price ID: {$price->id}\n";
            echo "Price: {$price->price}\n";
            echo "Type: {$price->availability_price_type}\n";
            echo "Region ID: {$price->region_id}\n";
            echo "Outlet ID: {$price->outlet_id}\n";
            echo "Created: {$price->created_at}\n";
            echo "Updated: {$price->updated_at}\n";
            echo "---\n";
        }
    } else {
        echo "No prices found for Fryall!\n";
    }

    // 3. Cek apakah ada update hari ini
    echo "\n=== FRYALL PRICE UPDATES TODAY ===\n";
    $todayUpdates = ItemPrice::where('item_id', $fryall->id)
        ->whereDate('updated_at', now()->format('Y-m-d'))
        ->get();
    
    if ($todayUpdates->count() > 0) {
        foreach ($todayUpdates as $update) {
            echo "Updated today: Price {$update->price} at {$update->updated_at}\n";
        }
    } else {
        echo "No price updates for Fryall today\n";
    }

    // 4. Simulasi validasi hash
    echo "\n=== HASH VALIDATION SIMULATION ===\n";
    $itemId = $fryall->id;
    $sku = $fryall->sku;
    $itemName = $fryall->name;
    $expectedHash = md5($itemId . $sku . $itemName);
    echo "Item ID: {$itemId}\n";
    echo "SKU: {$sku}\n";
    echo "Item Name: {$itemName}\n";
    echo "Expected Hash: {$expectedHash}\n";

    // 5. Cek activity log untuk Fryall
    echo "\n=== FRYALL ACTIVITY LOGS ===\n";
    $fryallLogs = DB::table('activity_logs')
        ->where('description', 'like', '%Fryall%')
        ->orWhere('description', 'like', '%52360%')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    foreach ($fryallLogs as $log) {
        echo "Time: {$log->created_at} | Activity: {$log->description}\n";
    }

} else {
    echo "Fryall item not found!\n";
}

// 6. Cek item lain yang mungkin memiliki masalah serupa
echo "\n=== SIMILAR ITEMS CHECK ===\n";
$similarItems = Item::where('name', 'like', '%Fry%')
    ->orWhere('sku', 'like', '%GC-20250620%')
    ->limit(5)
    ->get();

foreach ($similarItems as $item) {
    $hasPrice = ItemPrice::where('item_id', $item->id)->exists();
    $priceStatus = $hasPrice ? 'HAS PRICE' : 'NO PRICE';
    echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Status: {$priceStatus}\n";
}

echo "\n=== DEBUG COMPLETED ===\n"; 