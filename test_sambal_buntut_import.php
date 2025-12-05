<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST SAMBAL BUNTUT IMPORT ===\n\n";

// Test data berdasarkan data yang diupload user
$testData = [
    [
        'item_id' => '54157 SR-20250620-4744',
        'sku' => 'SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Jakarta-Tangerang'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'sku' => 'SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Bandung Prime'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'sku' => 'SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Bandung Reguler'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'sku' => 'SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 all',
        'region_outlet' => 'All'
    ]
];

echo "=== TESTING PARSING LOGIC ===\n";

// Simulasi logic parsing yang diperbaiki
function parsePriceData($row) {
    $newPrice = null;
    $priceType = 'all';
    $regionOutlet = 'All';
    
    // Cek berbagai kemungkinan nama kolom untuk new price
    $newPriceRaw = null;
    if (!empty($row['new_price'])) {
        $newPriceRaw = $row['new_price'];
    } elseif (!empty($row['new_price_kosongkan_jika_tidak_diupdate'])) {
        $newPriceRaw = $row['new_price_kosongkan_jika_tidak_diupdate'];
    }
    
    // Parse new price dan price type dari format "14152.94 region" atau "14152.94 all"
    if ($newPriceRaw) {
        $parts = explode(' ', trim($newPriceRaw));
        if (count($parts) >= 2) {
            $newPrice = $parts[0];
            $priceType = $parts[1];
            
            // Jika ada region/outlet di kolom terpisah, gunakan itu
            if (!empty($row['region_outlet'])) {
                $regionOutlet = $row['region_outlet'];
            }
        } else {
            $newPrice = $newPriceRaw;
        }
    }
    
    return [
        'new_price' => $newPrice,
        'price_type' => $priceType,
        'region_outlet' => $regionOutlet,
        'new_price_raw' => $newPriceRaw
    ];
}

foreach ($testData as $index => $row) {
    echo "Row " . ($index + 1) . ":\n";
    echo "  Item: {$row['item_name']}\n";
    echo "  New Price Raw: {$row['new_price']}\n";
    echo "  Region/Outlet: {$row['region_outlet']}\n";
    
    $parsed = parsePriceData($row);
    
    echo "  Parsed Result:\n";
    echo "    - New Price: {$parsed['new_price']}\n";
    echo "    - Price Type: {$parsed['price_type']}\n";
    echo "    - Region/Outlet: {$parsed['region_outlet']}\n";
    echo "\n";
}

echo "=== TESTING DATABASE LOOKUP ===\n";

// Test pencarian item di database
try {
    $item = DB::table('items')
        ->where('name', 'Sambal Buntut')
        ->where('status', 'active')
        ->first();
    
    if ($item) {
        echo "✅ Item 'Sambal Buntut' ditemukan:\n";
        echo "  - ID: {$item->id}\n";
        echo "  - SKU: {$item->sku}\n";
        echo "  - Name: {$item->name}\n";
        
        // Cek harga yang ada
        $prices = DB::table('item_prices')
            ->where('item_id', $item->id)
            ->get();
        
        echo "  - Current prices:\n";
        foreach ($prices as $price) {
            echo "    * Price: {$price->price}, Type: {$price->availability_price_type}\n";
        }
    } else {
        echo "❌ Item 'Sambal Buntut' tidak ditemukan\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTING REGION LOOKUP ===\n";

// Test pencarian region di database
$regions = ['Jakarta-Tangerang', 'Bandung Prime', 'Bandung Reguler'];

foreach ($regions as $regionName) {
    try {
        $region = DB::table('regions')
            ->where('name', $regionName)
            ->first();
        
        if ($region) {
            echo "✅ Region '{$regionName}' ditemukan (ID: {$region->id})\n";
        } else {
            echo "❌ Region '{$regionName}' tidak ditemukan\n";
        }
    } catch (Exception $e) {
        echo "❌ Error mencari region '{$regionName}': " . $e->getMessage() . "\n";
    }
}

echo "\n=== TESTING PRICE UPDATE SIMULATION ===\n";

// Simulasi update price untuk setiap row
foreach ($testData as $index => $row) {
    $parsed = parsePriceData($row);
    
    echo "Row " . ($index + 1) . " - Update simulation:\n";
    echo "  Item: {$row['item_name']}\n";
    echo "  New Price: {$parsed['new_price']}\n";
    echo "  Price Type: {$parsed['price_type']}\n";
    echo "  Region/Outlet: {$parsed['region_outlet']}\n";
    
    // Simulasi query update
    if ($parsed['price_type'] === 'region' && $parsed['region_outlet'] !== 'All') {
        echo "  -> Will update region-specific price\n";
    } elseif ($parsed['price_type'] === 'all') {
        echo "  -> Will update general price (all regions)\n";
    } else {
        echo "  -> Will update outlet-specific price\n";
    }
    echo "\n";
}

echo "=== TEST COMPLETED ===\n"; 