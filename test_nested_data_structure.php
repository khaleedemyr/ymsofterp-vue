<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST NESTED DATA STRUCTURE ===\n\n";

// Simulasi struktur data yang masuk dari Excel (berdasarkan log error)
$testRow = [
    'items' => (object) [
        'item_id' => 54776,
        'sku' => 'PS-20250719-1680',
        'item_name' => 'Burnt Cheesecake',
        'category' => 'Perishable',
        'current_price' => 300000,
        'new_price_kosongkan_jika_tidak_diupdate' => null,
        'price_type' => 'region',
        'regionoutlet' => 'Bandung Reguler',
        'validation_jangan_diubah' => 'dc9a86ba026a4243c3d6a96c589034e1'
    ],
    'escapeWhenCastingToString' => false
];

echo "=== TEST DATA STRUCTURE ===\n";
echo "Row type: " . gettype($testRow) . "\n";
echo "Has items property: " . (isset($testRow['items']) ? 'YES' : 'NO') . "\n";
echo "Items type: " . (isset($testRow['items']) ? gettype($testRow['items']) : 'NONE') . "\n";
echo "Items class: " . (isset($testRow['items']) && is_object($testRow['items']) ? get_class($testRow['items']) : 'NONE') . "\n";

if (isset($testRow['items']) && is_object($testRow['items'])) {
    echo "Items properties:\n";
    foreach ((array) $testRow['items'] as $key => $value) {
        echo "  - {$key}: " . (is_null($value) ? 'NULL' : $value) . "\n";
    }
}

echo "\n=== TESTING GETROWVALUE METHOD ===\n";

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
        // Check if data is nested in 'items' property (from Excel import)
        if (isset($row['items']) && is_object($row['items'])) {
            $items = $row['items'];
            if (property_exists($items, $key)) {
                return $items->$key ?? $default;
            }
        }
        
        return $row[$key] ?? $default;
    }
    
    return $default;
}

// Test dengan berbagai field
$testFields = [
    'item_id',
    'sku', 
    'item_name',
    'category',
    'current_price',
    'new_price_kosongkan_jika_tidak_diupdate',
    'price_type',
    'regionoutlet',
    'validation_jangan_diubah',
    'non_existent_field'
];

foreach ($testFields as $field) {
    $value = testGetRowValue($testRow, $field, 'DEFAULT');
    echo "getRowValue('{$field}'): " . (is_null($value) ? 'NULL' : $value) . "\n";
}

echo "\n=== TESTING REQUIRED FIELDS VALIDATION ===\n";

$requiredFields = ['item_id', 'sku', 'item_name'];
$missingFields = [];

foreach ($requiredFields as $field) {
    $value = testGetRowValue($testRow, $field);
    if (empty($value)) {
        $missingFields[] = $field;
    }
    echo "Required field '{$field}': " . (empty($value) ? 'MISSING' : 'FOUND') . "\n";
}

if (!empty($missingFields)) {
    echo "❌ Missing required fields: " . implode(', ', $missingFields) . "\n";
} else {
    echo "✅ All required fields found\n";
}

echo "\n=== TESTING PRICE EXTRACTION ===\n";

// Test price extraction logic
$newPriceRaw = testGetRowValue($testRow, 'new_price_kosongkan_jika_tidak_diupdate');
$priceType = testGetRowValue($testRow, 'price_type');
$regionOutlet = testGetRowValue($testRow, 'regionoutlet');

echo "New Price Raw: " . (is_null($newPriceRaw) ? 'NULL' : $newPriceRaw) . "\n";
echo "Price Type: " . (is_null($priceType) ? 'NULL' : $priceType) . "\n";
echo "Region/Outlet: " . (is_null($regionOutlet) ? 'NULL' : $regionOutlet) . "\n";

if (empty($newPriceRaw)) {
    echo "✅ This row will be skipped (no price change)\n";
} else {
    echo "✅ This row will be processed for price update\n";
}

echo "\n=== TESTING DIFFERENT DATA STRUCTURES ===\n";

// Test dengan berbagai struktur data
$testCases = [
    'normal_array' => [
        'item_id' => 123,
        'item_name' => 'Test Item',
        'new_price' => '15000'
    ],
    'nested_items' => [
        'items' => (object) [
            'item_id' => 456,
            'item_name' => 'Nested Item',
            'new_price' => '20000'
        ]
    ],
    'mixed_structure' => [
        'items' => (object) [
            'item_id' => 789,
            'item_name' => 'Mixed Item'
        ],
        'extra_field' => 'some value'
    ]
];

foreach ($testCases as $type => $data) {
    echo "Testing {$type}:\n";
    echo "  - item_id: " . testGetRowValue($data, 'item_id', 'NOT_FOUND') . "\n";
    echo "  - item_name: " . testGetRowValue($data, 'item_name', 'NOT_FOUND') . "\n";
    echo "  - new_price: " . testGetRowValue($data, 'new_price', 'NOT_FOUND') . "\n";
    echo "  - extra_field: " . testGetRowValue($data, 'extra_field', 'NOT_FOUND') . "\n\n";
}

echo "=== TEST COMPLETED ===\n"; 