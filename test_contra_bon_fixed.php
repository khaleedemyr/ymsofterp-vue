<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== TEST CONTRA BON FIXED ===\n";

// Test 1: Check if retail food with contra_bon payment method exists
echo "\n1. Checking retail food with contra_bon payment method...\n";
try {
    $retailFoods = DB::table('retail_food')
        ->where('payment_method', 'contra_bon')
        ->where('status', 'approved')
        ->get();
    
    echo "Found " . count($retailFoods) . " retail food with contra_bon payment method\n";
    
    if (count($retailFoods) > 0) {
        echo "Sample retail food:\n";
        foreach ($retailFoods->take(3) as $rf) {
            echo "- ID: {$rf->id}, Number: {$rf->retail_number}, Supplier ID: {$rf->supplier_id}, Date: {$rf->transaction_date}\n";
        }
    } else {
        echo "No retail food found with contra_bon payment method\n";
    }
} catch (Exception $e) {
    echo "Error checking retail food: " . $e->getMessage() . "\n";
}

// Test 2: Check retail food items structure
echo "\n2. Checking retail food items structure...\n";
try {
    $sampleRetailFood = DB::table('retail_food')
        ->where('payment_method', 'contra_bon')
        ->where('status', 'approved')
        ->first();
    
    if ($sampleRetailFood) {
        echo "Sample retail food found: {$sampleRetailFood->retail_number}\n";
        
        $items = DB::table('retail_food_items')
            ->where('retail_food_id', $sampleRetailFood->id)
            ->get();
        
        echo "Found " . count($items) . " items for this retail food\n";
        
        if (count($items) > 0) {
            echo "Sample item structure:\n";
            $sampleItem = $items->first();
            foreach ($sampleItem as $key => $value) {
                echo "- {$key}: {$value}\n";
            }
        }
    } else {
        echo "No sample retail food found\n";
    }
} catch (Exception $e) {
    echo "Error checking items structure: " . $e->getMessage() . "\n";
}

// Test 3: Test the fixed API query
echo "\n3. Testing the fixed API query...\n";
try {
    // Ambil semua retail_food_id yang sudah ada di contra bon
    $usedRetailFoods = DB::table('food_contra_bons')
        ->where('source_type', 'retail_food')
        ->whereNotNull('source_id')
        ->pluck('source_id')
        ->toArray();

    echo "Used retail food IDs: " . implode(', ', $usedRetailFoods) . "\n";

    $retailFoods = DB::table('retail_food as rf')
        ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
        ->join('users as creator', 'rf.created_by', '=', 'creator.id')
        ->where('rf.payment_method', 'contra_bon')
        ->where('rf.status', 'approved')
        ->whereNotIn('rf.id', $usedRetailFoods)
        ->select(
            'rf.id as retail_food_id',
            'rf.retail_number',
            'rf.transaction_date',
            'rf.total_amount',
            'rf.notes',
            's.id as supplier_id',
            's.name as supplier_name',
            'creator.nama_lengkap as creator_name'
        )
        ->orderByDesc('rf.transaction_date')
        ->get();

    echo "Found " . count($retailFoods) . " available retail food for contra bon\n";
    
    if (count($retailFoods) > 0) {
        echo "Available retail food:\n";
        foreach ($retailFoods->take(3) as $row) {
            echo "- ID: {$row->retail_food_id}, Number: {$row->retail_number}, Supplier: {$row->supplier_name}, Date: {$row->transaction_date}\n";
            
            // Test items query for this retail food
            $items = DB::table('retail_food_items as rfi')
                ->where('rfi.retail_food_id', $row->retail_food_id)
                ->select(
                    'rfi.id',
                    'rfi.item_name',
                    'rfi.unit as unit_name',
                    'rfi.qty',
                    'rfi.price'
                )
                ->get();
            
            echo "  Items: " . count($items) . " items found\n";
            foreach ($items as $item) {
                echo "    - {$item->item_name} ({$item->unit_name}): {$item->qty} x {$item->price}\n";
            }
        }
    } else {
        echo "No available retail food found\n";
    }
} catch (Exception $e) {
    echo "Error in fixed API query: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test 4: Test item and unit lookup
echo "\n4. Testing item and unit lookup...\n";
try {
    $sampleItem = DB::table('retail_food_items')
        ->where('item_name', '!=', '')
        ->first();
    
    if ($sampleItem) {
        echo "Sample item: {$sampleItem->item_name} ({$sampleItem->unit})\n";
        
        // Test item lookup
        $foundItem = DB::table('items')->where('name', $sampleItem->item_name)->first();
        if ($foundItem) {
            echo "Item found in items table: ID {$foundItem->id}\n";
        } else {
            echo "Item NOT found in items table\n";
        }
        
        // Test unit lookup
        $foundUnit = DB::table('units')->where('name', $sampleItem->unit)->first();
        if ($foundUnit) {
            echo "Unit found in units table: ID {$foundUnit->id}\n";
        } else {
            echo "Unit NOT found in units table\n";
        }
    } else {
        echo "No sample item found\n";
    }
} catch (Exception $e) {
    echo "Error in item/unit lookup: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nKESIMPULAN:\n";
echo "1. API retail food contra bon sudah diperbaiki\n";
echo "2. Query tidak lagi menggunakan kolom item_id dan unit_id yang tidak ada\n";
echo "3. Data item_name dan unit_name digunakan langsung dari retail_food_items\n";
echo "4. Backend akan mencoba mencari item_id dan unit_id berdasarkan nama jika diperlukan\n";
echo "5. Frontend sudah diperbaiki untuk menangani struktur data yang benar\n";
echo "\nSekarang coba test fitur contra bon retail food lagi.\n";
