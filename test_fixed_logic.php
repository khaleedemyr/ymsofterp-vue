<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\ItemPrice;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST FIXED LOGIC ===\n\n";

// Simulasi data Fryall dari template
$templateData = [
    'item_id' => 52360,
    'sku' => 'GC-20250620-605',
    'item_name' => 'Fryall',
    'current_price' => 432880, // Harga lama di template
    'new_price' => 424873.12, // Harga baru di template
    'price_type' => 'all',
    'region_outlet' => 'All'
];

echo "=== TEMPLATE DATA ===\n";
echo "Item ID: {$templateData['item_id']}\n";
echo "SKU: {$templateData['sku']}\n";
echo "Item Name: {$templateData['item_name']}\n";
echo "Template Current Price: {$templateData['current_price']}\n";
echo "Template New Price: {$templateData['new_price']}\n";
echo "Price Type: {$templateData['price_type']}\n";
echo "Region/Outlet: {$templateData['region_outlet']}\n\n";

// Cek item di database
$item = Item::where('id', $templateData['item_id'])
    ->where('name', $templateData['item_name'])
    ->where('status', 'active')
    ->first();

if ($item) {
    echo "=== ITEM FOUND ===\n";
    echo "Database SKU: {$item->sku}\n";
    
    // Ambil harga aktual dari database
    $dbPrice = ItemPrice::where('item_id', $item->id)
        ->where('availability_price_type', $templateData['price_type'])
        ->whereNull('region_id')
        ->whereNull('outlet_id')
        ->first();
    
    $actualCurrentPrice = $dbPrice ? (float)$dbPrice->price : 0;
    echo "Database Current Price: {$actualCurrentPrice}\n";
    
    // Logika perbandingan yang diperbaiki
    echo "\n=== COMPARISON LOGIC ===\n";
    echo "Template Current Price: {$templateData['current_price']}\n";
    echo "Database Current Price: {$actualCurrentPrice}\n";
    echo "Template New Price: {$templateData['new_price']}\n";
    
    // Cek apakah harga sama dengan database
    $priceSameAsDB = $templateData['new_price'] == $actualCurrentPrice;
    echo "New Price Same as DB: " . ($priceSameAsDB ? 'YES' : 'NO') . "\n";
    
    // Cek apakah harga sama dengan template
    $priceSameAsTemplate = $templateData['new_price'] == $templateData['current_price'];
    echo "New Price Same as Template: " . ($priceSameAsTemplate ? 'YES' : 'NO') . "\n";
    
    // Simulasi hasil
    echo "\n=== RESULT SIMULATION ===\n";
    if ($priceSameAsDB) {
        echo "RESULT: Skipped (same price) - new price sama dengan database\n";
        echo "REASON: Template tidak up-to-date, harga sudah terupdate sebelumnya\n";
    } elseif ($priceSameAsTemplate) {
        echo "RESULT: Skipped (same price) - new price sama dengan template current\n";
        echo "REASON: Tidak ada perubahan harga di template\n";
    } else {
        echo "RESULT: Should be updated\n";
        $priceChange = $templateData['new_price'] - $actualCurrentPrice;
        echo "Price change: {$priceChange}\n";
    }
    
    // Rekomendasi
    echo "\n=== RECOMMENDATION ===\n";
    if ($priceSameAsDB) {
        echo "Template perlu diupdate dengan harga database terbaru\n";
        echo "Atau gunakan template yang sudah up-to-date\n";
    } else {
        echo "Item siap untuk diupdate\n";
    }
    
} else {
    echo "Item not found!\n";
}

echo "\n=== TEST COMPLETED ===\n"; 