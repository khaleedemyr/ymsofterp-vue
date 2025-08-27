<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Set up Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST COST CONVERSION LOGIC (COMPLETE) ===\n\n";

// Test data dari log sebelumnya
$item_mac_cost = 47204.10; // Cost dari outlet rejection item (per kilogram)
$rejection_unit_name = 'Kilogram'; // Unit dari outlet rejection item
$small_unit_name = 'Gram'; // Small unit dari item master
$medium_unit_name = 'Kilogram'; // Medium unit dari item master
$large_unit_name = 'Kilogram'; // Large unit dari item master
$small_conv = 1000; // Small conversion qty (1 kg = 1000 gram)
$medium_conv = 1; // Medium conversion qty
$qty_received = 10; // Qty received dalam kilogram

echo "Input Data:\n";
echo "Item MAC Cost: {$item_mac_cost} (per {$rejection_unit_name})\n";
echo "Qty Received: {$qty_received} {$rejection_unit_name}\n";
echo "Small Unit: {$small_unit_name}\n";
echo "Medium Unit: {$medium_unit_name}\n";
echo "Large Unit: {$large_unit_name}\n";
echo "Small Conversion: {$small_conv}\n";
echo "Medium Conversion: {$medium_conv}\n\n";

// 1. Konversi cost ke small unit
$item_mac_cost_small_unit = $item_mac_cost;

if ($rejection_unit_name !== $small_unit_name) {
    if ($rejection_unit_name === $medium_unit_name) {
        // Convert from medium to small unit
        $item_mac_cost_small_unit = $item_mac_cost / $small_conv;
        echo "1. COST CONVERSION:\n";
        echo "Converting from MEDIUM ({$medium_unit_name}) to SMALL ({$small_unit_name}) unit:\n";
        echo "Original Cost: {$item_mac_cost} per {$rejection_unit_name}\n";
        echo "Small Conversion: {$small_conv}\n";
        echo "Converted Cost: {$item_mac_cost_small_unit} per {$small_unit_name}\n\n";
    } elseif ($rejection_unit_name === $large_unit_name) {
        // Convert from large to small unit
        $item_mac_cost_small_unit = $item_mac_cost / ($small_conv * $medium_conv);
        echo "1. COST CONVERSION:\n";
        echo "Converting from LARGE ({$large_unit_name}) to SMALL ({$small_unit_name}) unit:\n";
        echo "Original Cost: {$item_mac_cost} per {$rejection_unit_name}\n";
        echo "Small Conversion: {$small_conv}\n";
        echo "Medium Conversion: {$medium_conv}\n";
        echo "Converted Cost: {$item_mac_cost_small_unit} per {$small_unit_name}\n\n";
    }
} else {
    echo "1. COST CONVERSION:\n";
    echo "Unit already in SMALL unit, no conversion needed\n\n";
}

// 2. Konversi qty ke small unit
$qty_small = $qty_received * $small_conv; // 10 kg * 1000 = 10000 gram
$qty_medium = $qty_received; // 10 kg
$qty_large = $qty_received; // 10 kg

echo "2. QUANTITY CONVERSION:\n";
echo "Qty Received: {$qty_received} {$rejection_unit_name}\n";
echo "Qty Small: {$qty_small} {$small_unit_name}\n";
echo "Qty Medium: {$qty_medium} {$medium_unit_name}\n";
echo "Qty Large: {$qty_large} {$large_unit_name}\n\n";

// 3. MAC calculation
$qty_lama = 427864.51; // Existing stock qty small (gram)
$nilai_lama = 20668994.39; // Existing stock value
$qty_baru = $qty_small; // New qty small (10000 gram)
$nilai_baru = $qty_baru * $item_mac_cost_small_unit; // 10000 * 47.2041
$total_qty = $qty_lama + $qty_baru;
$total_nilai = $nilai_lama + $nilai_baru;
$mac = $total_qty > 0 ? $total_nilai / $total_qty : $item_mac_cost_small_unit;

echo "3. MAC CALCULATION:\n";
echo "Qty Lama: {$qty_lama} {$small_unit_name}\n";
echo "Nilai Lama: {$nilai_lama}\n";
echo "Qty Baru: {$qty_baru} {$small_unit_name}\n";
echo "Nilai Baru: {$nilai_baru} (qty_baru * cost_small_unit)\n";
echo "Total Qty: {$total_qty} {$small_unit_name}\n";
echo "Total Nilai: {$total_nilai}\n";
echo "MAC (per {$small_unit_name}): {$mac}\n\n";

// 4. Cost calculations untuk stock dan stock card
$cost_per_small = $mac;
$cost_per_medium = $mac * $small_conv;
$cost_per_large = $mac * $small_conv * $medium_conv;
$value_in = $qty_small * $mac;

echo "4. COST CALCULATIONS:\n";
echo "Cost Per Small: {$cost_per_small} per {$small_unit_name}\n";
echo "Cost Per Medium: {$cost_per_medium} per {$medium_unit_name}\n";
echo "Cost Per Large: {$cost_per_large} per {$large_unit_name}\n";
echo "Value In: {$value_in} (qty_small * mac)\n\n";

// 5. Cek data aktual dari database
echo "=== VERIFIKASI DATA AKTUAL ===\n\n";

// Cek item master
$itemMaster = DB::table('items')->where('id', 53080)->first();
if ($itemMaster) {
    echo "Item Master:\n";
    echo "Name: {$itemMaster->name}\n";
    echo "Small Unit ID: {$itemMaster->small_unit_id}\n";
    echo "Medium Unit ID: {$itemMaster->medium_unit_id}\n";
    echo "Large Unit ID: {$itemMaster->large_unit_id}\n";
    echo "Small Conversion: {$itemMaster->small_conversion_qty}\n";
    echo "Medium Conversion: {$itemMaster->medium_conversion_qty}\n\n";
    
    // Cek unit names
    $smallUnit = DB::table('units')->where('id', $itemMaster->small_unit_id)->first();
    $mediumUnit = DB::table('units')->where('id', $itemMaster->medium_unit_id)->first();
    $largeUnit = DB::table('units')->where('id', $itemMaster->large_unit_id)->first();
    
    echo "Unit Names:\n";
    echo "Small Unit: {$smallUnit->name}\n";
    echo "Medium Unit: {$mediumUnit->name}\n";
    echo "Large Unit: {$largeUnit->name}\n\n";
}

// Cek outlet rejection item
$rejectionItem = DB::table('outlet_rejection_items')->where('id', 5)->first();
if ($rejectionItem) {
    echo "Outlet Rejection Item:\n";
    echo "Item ID: {$rejectionItem->item_id}\n";
    echo "Unit ID: {$rejectionItem->unit_id}\n";
    echo "MAC Cost: {$rejectionItem->mac_cost}\n";
    echo "Qty Received: {$rejectionItem->qty_received}\n\n";
    
    // Cek unit dari rejection item
    $rejectionUnit = DB::table('units')->where('id', $rejectionItem->unit_id)->first();
    if ($rejectionUnit) {
        echo "Rejection Item Unit: {$rejectionUnit->name}\n\n";
    }
}

// Cek stock terbaru
$latestStock = DB::table('food_inventory_stocks')
    ->where('inventory_item_id', 4771)
    ->where('warehouse_id', 1)
    ->first();

if ($latestStock) {
    echo "Latest Stock:\n";
    echo "Qty Small: {$latestStock->qty_small}\n";
    echo "Qty Medium: {$latestStock->qty_medium}\n";
    echo "Qty Large: {$latestStock->qty_large}\n";
    echo "Value: {$latestStock->value}\n";
    echo "Last Cost Small: {$latestStock->last_cost_small}\n";
    echo "Last Cost Medium: {$latestStock->last_cost_medium}\n";
    echo "Last Cost Large: {$latestStock->last_cost_large}\n\n";
}

// Cek stock card terbaru
$latestStockCard = DB::table('food_inventory_cards')
    ->where('reference_type', 'outlet_rejection')
    ->where('reference_id', 5)
    ->orderBy('created_at', 'desc')
    ->first();

if ($latestStockCard) {
    echo "Latest Stock Card:\n";
    echo "In Qty Small: {$latestStockCard->in_qty_small}\n";
    echo "In Qty Medium: {$latestStockCard->in_qty_medium}\n";
    echo "In Qty Large: {$latestStockCard->in_qty_large}\n";
    echo "Cost Per Small: {$latestStockCard->cost_per_small}\n";
    echo "Cost Per Medium: {$latestStockCard->cost_per_medium}\n";
    echo "Cost Per Large: {$latestStockCard->cost_per_large}\n";
    echo "Value In: {$latestStockCard->value_in}\n\n";
}

// Cek cost history terbaru
$latestCostHistory = DB::table('food_inventory_cost_histories')
    ->where('reference_type', 'outlet_rejection')
    ->where('reference_id', 5)
    ->orderBy('created_at', 'desc')
    ->first();

if ($latestCostHistory) {
    echo "Latest Cost History:\n";
    echo "Old Cost: {$latestCostHistory->old_cost}\n";
    echo "New Cost: {$latestCostHistory->new_cost}\n";
    echo "MAC: {$latestCostHistory->mac}\n";
    echo "Created At: {$latestCostHistory->created_at}\n\n";
}

echo "=== TEST SELESAI ===\n";
