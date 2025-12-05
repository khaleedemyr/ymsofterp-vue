<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PriceUpdateImport;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST PRICE IMPORT FIX ===\n\n";

// Test 1: Cek apakah ada item yang bisa diupdate
echo "=== AVAILABLE ITEMS FOR UPDATE ===\n";
try {
    $items = DB::table('items')
        ->where('status', 'active')
        ->limit(5)
        ->get();
    
    echo "Found " . $items->count() . " active items:\n";
    foreach ($items as $item) {
        echo "  - {$item->name} (ID: {$item->id}, SKU: {$item->sku})\n";
        
        // Cek harga yang ada
        $prices = DB::table('item_prices')
            ->where('item_id', $item->id)
            ->get();
        
        foreach ($prices as $price) {
            echo "    * Price: {$price->price}, Type: {$price->availability_price_type}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Buat data test untuk import
echo "\n=== CREATING TEST DATA ===\n";
try {
    // Ambil item pertama untuk test
    $testItem = DB::table('items')
        ->where('status', 'active')
        ->first();
    
    if ($testItem) {
        echo "Using test item: {$testItem->name}\n";
        
        // Buat data test
        $testData = [
            [
                'item_id' => $testItem->id,
                'sku' => $testItem->sku,
                'item_name' => $testItem->name,
                'current_price' => 10000,
                'new_price' => 12000,
                'price_type' => 'all',
                'region_outlet' => 'All',
                'validation_jangan_diubah' => md5($testItem->id . $testItem->sku . $testItem->name)
            ]
        ];
        
        echo "Test data created:\n";
        foreach ($testData as $row) {
            echo "  - Item: {$row['item_name']}, New Price: {$row['new_price']}\n";
        }
        
        // Test method getRowValue
        echo "\nTesting getRowValue method:\n";
        foreach ($testData as $index => $row) {
            echo "Row {$index}:\n";
            echo "  item_id: " . ($row['item_id'] ?? 'NOT_FOUND') . "\n";
            echo "  item_name: " . ($row['item_name'] ?? 'NOT_FOUND') . "\n";
            echo "  new_price: " . ($row['new_price'] ?? 'NOT_FOUND') . "\n";
        }
        
    } else {
        echo "❌ No active items found for testing\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Test dengan berbagai tipe data row
echo "\n=== TESTING DIFFERENT ROW TYPES ===\n";

// Simulasi method getRowValue yang diperbaiki
function testGetRowValue($row, $key, $default = null)
{
    // Handle null or invalid row
    if (empty($row) || (!is_object($row) && !is_array($row))) {
        return $default;
    }
    
    if (is_object($row)) {
        // Handle different object types
        if (method_exists($row, 'get')) {
            return $row->get($key, $default);
        } elseif (property_exists($row, $key)) {
            return $row->$key ?? $default;
        } else {
            return $default;
        }
    }
    
    if (is_array($row)) {
        return $row[$key] ?? $default;
    }
    
    return $default;
}

$testRowData = [
    'array' => [
        'item_id' => 123,
        'item_name' => 'Test Item',
        'new_price' => 15000
    ],
    'object' => (object) [
        'item_id' => 123,
        'item_name' => 'Test Item',
        'new_price' => 15000
    ],
    'collection' => collect([
        'item_id' => 123,
        'item_name' => 'Test Item',
        'new_price' => 15000
    ])
];

foreach ($testRowData as $type => $row) {
    echo "Testing {$type}:\n";
    echo "  Type: " . gettype($row) . "\n";
    if (is_object($row)) {
        echo "  Class: " . get_class($row) . "\n";
    }
    echo "  item_id: " . testGetRowValue($row, 'item_id', 'NOT_FOUND') . "\n";
    echo "  item_name: " . testGetRowValue($row, 'item_name', 'NOT_FOUND') . "\n";
    echo "  new_price: " . testGetRowValue($row, 'new_price', 'NOT_FOUND') . "\n";
    echo "  non_existent: " . testGetRowValue($row, 'non_existent', 'DEFAULT') . "\n";
    echo "\n";
}

// Test 4: Cek apakah ada masalah dengan Excel package
echo "=== EXCEL PACKAGE CHECK ===\n";
try {
    if (class_exists('Maatwebsite\Excel\Facades\Excel')) {
        echo "✅ Excel package is available\n";
    } else {
        echo "❌ Excel package not found\n";
    }
    
    if (class_exists('App\Imports\PriceUpdateImport')) {
        echo "✅ PriceUpdateImport class is available\n";
    } else {
        echo "❌ PriceUpdateImport class not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n"; 