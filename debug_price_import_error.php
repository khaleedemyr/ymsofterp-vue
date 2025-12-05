<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG PRICE IMPORT ERROR ===\n\n";

// Test 1: Cek struktur data yang diharapkan
echo "=== EXPECTED DATA STRUCTURE ===\n";
$expectedColumns = [
    'item_id',
    'sku', 
    'item_name',
    'current_price',
    'new_price',
    'price_type',
    'region_outlet',
    'validation_jangan_diubah'
];

echo "Expected columns:\n";
foreach ($expectedColumns as $col) {
    echo "  - {$col}\n";
}

// Test 2: Cek sample data dari template export
echo "\n=== SAMPLE TEMPLATE DATA ===\n";
try {
    // Ambil sample data dari item yang ada
    $sampleItems = DB::table('items')
        ->where('status', 'active')
        ->limit(3)
        ->get();
    
    foreach ($sampleItems as $item) {
        echo "Item: {$item->name} (ID: {$item->id}, SKU: {$item->sku})\n";
        
        // Ambil harga yang ada
        $prices = DB::table('item_prices')
            ->where('item_id', $item->id)
            ->get();
        
        foreach ($prices as $price) {
            echo "  - Price: {$price->price}, Type: {$price->availability_price_type}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Test method getRowValue dengan berbagai tipe data
echo "=== TEST GETROWVALUE METHOD ===\n";

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

// Test dengan berbagai tipe data
$testCases = [
    'null' => null,
    'empty_array' => [],
    'array_with_data' => ['item_name' => 'Test Item', 'item_id' => 123],
    'stdClass' => (object) ['item_name' => 'Test Item', 'item_id' => 123],
    'collection' => collect(['item_name' => 'Test Item', 'item_id' => 123])
];

foreach ($testCases as $type => $data) {
    echo "Testing {$type}:\n";
    echo "  Type: " . gettype($data) . "\n";
    if (is_object($data)) {
        echo "  Class: " . get_class($data) . "\n";
    }
    echo "  item_name: " . testGetRowValue($data, 'item_name', 'DEFAULT') . "\n";
    echo "  non_existent: " . testGetRowValue($data, 'non_existent', 'DEFAULT') . "\n";
    echo "\n";
}

// Test 4: Cek log error terbaru
echo "=== RECENT ERROR LOGS ===\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -20); // Ambil 20 baris terakhir
        
        echo "Recent log entries:\n";
        foreach ($recentLines as $line) {
            if (strpos($line, 'PriceUpdateImport') !== false) {
                echo trim($line) . "\n";
            }
        }
    } else {
        echo "Log file not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error reading log: " . $e->getMessage() . "\n";
}

// Test 5: Cek apakah ada masalah dengan Excel import
echo "\n=== EXCEL IMPORT TEST ===\n";
try {
    // Cek apakah ada file Excel yang sedang diproses
    $uploadDir = storage_path('app/public/uploads');
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*.xlsx');
        echo "Excel files in upload directory:\n";
        foreach ($files as $file) {
            echo "  - " . basename($file) . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETED ===\n"; 