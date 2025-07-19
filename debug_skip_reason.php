<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG SKIP REASON ===\n\n";

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
    }
    
    // 2. Simulasi data dari template
    echo "\n=== TEMPLATE DATA SIMULATION ===\n";
    $templateData = [
        'item_id' => 52360,
        'sku' => 'GC-20250620-605', // SKU di template
        'item_name' => 'Fryall',
        'current_price' => 432880, // Harga di template
        'new_price' => 424873.12, // Harga baru di template
        'price_type' => 'all',
        'region_outlet' => 'All'
    ];
    
    echo "Template Current Price: {$templateData['current_price']}\n";
    echo "Template New Price: {$templateData['new_price']}\n";
    echo "Database Current Price: " . ($currentPrice ? $currentPrice->price : 'NO PRICE') . "\n";
    
    // 3. Cek logika skip
    echo "\n=== SKIP LOGIC ANALYSIS ===\n";
    
    // Cek apakah new_price kosong
    $newPriceEmpty = empty($templateData['new_price']) || $templateData['new_price'] === '' || $templateData['new_price'] === null;
    echo "New Price Empty: " . ($newPriceEmpty ? 'YES' : 'NO') . "\n";
    
    // Cek apakah harga sama
    $priceSame = $templateData['new_price'] == $templateData['current_price'];
    echo "Price Same: " . ($priceSame ? 'YES' : 'NO') . "\n";
    
    // Cek apakah harga sama dengan database
    $dbPriceSame = $templateData['new_price'] == ($currentPrice ? $currentPrice->price : 0);
    echo "Price Same as DB: " . ($dbPriceSame ? 'YES' : 'NO') . "\n";
    
    // 4. Cek hash validation
    echo "\n=== HASH VALIDATION ===\n";
    $expectedHash = md5($templateData['item_id'] . $templateData['sku'] . $templateData['item_name']);
    echo "Expected Hash: {$expectedHash}\n";
    
    // 5. Cek item lookup
    echo "\n=== ITEM LOOKUP TEST ===\n";
    
    // Test 1: Lookup dengan SKU yang sama
    $itemBySku = Item::where('id', $templateData['item_id'])
        ->where('sku', $templateData['sku'])
        ->where('name', $templateData['item_name'])
        ->where('status', 'active')
        ->first();
    echo "Item found by exact SKU: " . ($itemBySku ? 'YES' : 'NO') . "\n";
    
    // Test 2: Fallback lookup (ID + nama saja)
    $itemByFallback = Item::where('id', $templateData['item_id'])
        ->where('name', $templateData['item_name'])
        ->where('status', 'active')
        ->first();
    echo "Item found by fallback: " . ($itemByFallback ? 'YES' : 'NO') . "\n";
    
    if ($itemByFallback) {
        echo "Fallback Item SKU: {$itemByFallback->sku}\n";
    }
    
    // 6. Simulasi logika import
    echo "\n=== IMPORT LOGIC SIMULATION ===\n";
    
    if ($newPriceEmpty) {
        echo "RESULT: Skipped (no price change) - new_price empty\n";
    } elseif ($priceSame) {
        echo "RESULT: Skipped (same price) - template current = template new\n";
    } elseif ($dbPriceSame) {
        echo "RESULT: Skipped (same price) - template new = database current\n";
    } elseif (!$itemByFallback) {
        echo "RESULT: Error - item not found\n";
    } else {
        echo "RESULT: Should be updated\n";
        echo "Price change: " . ($templateData['new_price'] - ($currentPrice ? $currentPrice->price : 0)) . "\n";
    }
    
} else {
    echo "Fryall item not found!\n";
}

// 7. Cek item lain yang mungkin memiliki masalah serupa
echo "\n=== OTHER ITEMS TO CHECK ===\n";
$otherItems = ['Garam Kapal', 'Garam Refina'];
foreach ($otherItems as $itemName) {
    $item = Item::where('name', $itemName)->first();
    if ($item) {
        $price = ItemPrice::where('item_id', $item->id)->first();
        $priceValue = $price ? $price->price : 'NO PRICE';
        echo "Item: {$item->name} | ID: {$item->id} | SKU: {$item->sku} | Price: {$priceValue}\n";
    }
}

echo "\n=== DEBUG COMPLETED ===\n"; 