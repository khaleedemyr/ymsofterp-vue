<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST PRICE PARSING ===\n\n";

// Test data berdasarkan format yang diupload user
$testData = [
    [
        'item_id' => '54157 SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Jakarta-Tangerang'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Bandung Prime'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 region',
        'region_outlet' => 'Bandung Reguler'
    ],
    [
        'item_id' => '54157 SR-20250620-4744',
        'item_name' => 'Sambal Buntut',
        'new_price' => '14152.94 all',
        'region_outlet' => 'All'
    ]
];

echo "=== TESTING PRICE PARSING LOGIC ===\n";

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

echo "=== TESTING DIFFERENT FORMATS ===\n";

$formatTests = [
    '14152.94 region' => 'Should parse: price=14152.94, type=region',
    '14152.94 all' => 'Should parse: price=14152.94, type=all',
    '15000' => 'Should parse: price=15000, type=all (default)',
    '20000 outlet' => 'Should parse: price=20000, type=outlet',
    '25000.50 region' => 'Should parse: price=25000.50, type=region'
];

foreach ($formatTests as $input => $expected) {
    $testRow = ['new_price' => $input];
    $parsed = parsePriceData($testRow);
    
    echo "Input: '{$input}'\n";
    echo "Expected: {$expected}\n";
    echo "Result: price={$parsed['new_price']}, type={$parsed['price_type']}\n";
    echo "\n";
}

echo "=== TESTING REGION/OUTLET MAPPING ===\n";

// Test mapping region/outlet ke database
$regionTests = [
    'Jakarta-Tangerang' => 'Should find region in database',
    'Bandung Prime' => 'Should find region in database', 
    'Bandung Reguler' => 'Should find region in database',
    'All' => 'Should use null region_id',
    'Non-existent Region' => 'Should not find region'
];

foreach ($regionTests as $regionName => $description) {
    echo "Region: '{$regionName}' - {$description}\n";
    
    if ($regionName === 'All') {
        echo "  -> Will use null region_id (price for all regions)\n";
    } else {
        // Simulasi pencarian region di database
        echo "  -> Will search for region in regions table\n";
    }
    echo "\n";
}

echo "=== TEST COMPLETED ===\n"; 