<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST FRYALL IMPORT ===\n\n";

// 1. Cek harga Fryall saat ini
$fryall = Item::find(52360);
if ($fryall) {
    echo "=== FRYALL CURRENT STATUS ===\n";
    echo "ID: {$fryall->id}\n";
    echo "Name: {$fryall->name}\n";
    echo "SKU: {$fryall->sku}\n";
    
    $currentPrice = ItemPrice::where('item_id', $fryall->id)->first();
    if ($currentPrice) {
        echo "Current Price: {$currentPrice->price}\n";
        echo "Price Type: {$currentPrice->availability_price_type}\n";
        echo "Last Updated: {$currentPrice->updated_at}\n";
    } else {
        echo "No price found!\n";
    }
    
    // 2. Simulasi update manual
    echo "\n=== SIMULATING MANUAL UPDATE ===\n";
    $newPrice = 424873.12;
    $oldPrice = $currentPrice ? $currentPrice->price : 0;
    
    echo "Old Price: {$oldPrice}\n";
    echo "New Price: {$newPrice}\n";
    echo "Price Change: " . ($newPrice - $oldPrice) . "\n";
    
    if ($newPrice != $oldPrice) {
        // Update price
        if ($currentPrice) {
            $currentPrice->update(['price' => $newPrice]);
            echo "Price updated successfully!\n";
        } else {
            // Create new price
            ItemPrice::create([
                'item_id' => $fryall->id,
                'price' => $newPrice,
                'availability_price_type' => 'all',
                'region_id' => null,
                'outlet_id' => null
            ]);
            echo "New price created successfully!\n";
        }
        
        // Log activity
        DB::table('activity_logs')->insert([
            'user_id' => 1, // Assuming admin user
            'activity_type' => 'update',
            'module' => 'item_prices',
            'description' => "Manual update harga item: {$fryall->name} dari Rp " . number_format($oldPrice) . " ke Rp " . number_format($newPrice),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Manual Update Script',
            'old_data' => json_encode(['price' => $oldPrice]),
            'new_data' => json_encode(['price' => $newPrice]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        echo "Activity logged successfully!\n";
    } else {
        echo "No price change needed.\n";
    }
    
    // 3. Verify update
    echo "\n=== VERIFYING UPDATE ===\n";
    $updatedPrice = ItemPrice::where('item_id', $fryall->id)->first();
    if ($updatedPrice) {
        echo "Updated Price: {$updatedPrice->price}\n";
        echo "Updated At: {$updatedPrice->updated_at}\n";
    }
    
} else {
    echo "Fryall item not found!\n";
}

// 4. Cek item lain yang mungkin memiliki masalah serupa
echo "\n=== SIMILAR ITEMS TO CHECK ===\n";
$similarItems = Item::where('sku', 'like', 'GC-20250620-605%')
    ->orWhere('name', 'like', '%Fry%')
    ->limit(5)
    ->get();

foreach ($similarItems as $item) {
    $price = ItemPrice::where('item_id', $item->id)->first();
    $priceValue = $price ? $price->price : 'NO PRICE';
    echo "Item: {$item->name} | SKU: {$item->sku} | Price: {$priceValue}\n";
}

echo "\n=== TEST COMPLETED ===\n"; 