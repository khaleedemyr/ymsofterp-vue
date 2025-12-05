<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== TEST CONTRA BON RETAIL FOOD ===\n";

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

// Test 2: Check if suppliers exist for retail food
echo "\n2. Checking suppliers for retail food...\n";
try {
    $suppliers = DB::table('retail_food as rf')
        ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
        ->where('rf.payment_method', 'contra_bon')
        ->where('rf.status', 'approved')
        ->select('rf.id as retail_food_id', 'rf.retail_number', 's.id as supplier_id', 's.name as supplier_name')
        ->get();
    
    echo "Found " . count($suppliers) . " retail food with valid suppliers\n";
    
    if (count($suppliers) > 0) {
        echo "Sample data:\n";
        foreach ($suppliers->take(3) as $row) {
            echo "- Retail Food: {$row->retail_number}, Supplier: {$row->supplier_name}\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking suppliers: " . $e->getMessage() . "\n";
}

// Test 3: Check if users exist for retail food creators
echo "\n3. Checking users for retail food creators...\n";
try {
    $creators = DB::table('retail_food as rf')
        ->join('users as creator', 'rf.created_by', '=', 'creator.id')
        ->where('rf.payment_method', 'contra_bon')
        ->where('rf.status', 'approved')
        ->select('rf.id as retail_food_id', 'rf.retail_number', 'creator.id as creator_id', 'creator.nama_lengkap as creator_name')
        ->get();
    
    echo "Found " . count($creators) . " retail food with valid creators\n";
    
    if (count($creators) > 0) {
        echo "Sample data:\n";
        foreach ($creators->take(3) as $row) {
            echo "- Retail Food: {$row->retail_number}, Creator: {$row->creator_name}\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking creators: " . $e->getMessage() . "\n";
}

// Test 4: Check if retail food items exist
echo "\n4. Checking retail food items...\n";
try {
    $items = DB::table('retail_food_items as rfi')
        ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
        ->where('rf.payment_method', 'contra_bon')
        ->where('rf.status', 'approved')
        ->select('rf.id as retail_food_id', 'rf.retail_number', 'rfi.id as item_id', 'rfi.item_id as master_item_id', 'rfi.unit_id')
        ->get();
    
    echo "Found " . count($items) . " retail food items\n";
    
    if (count($items) > 0) {
        echo "Sample data:\n";
        foreach ($items->take(3) as $row) {
            echo "- Retail Food: {$row->retail_number}, Item ID: {$row->item_id}, Master Item ID: {$row->master_item_id}, Unit ID: {$row->unit_id}\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking items: " . $e->getMessage() . "\n";
}

// Test 5: Check if items and units exist
echo "\n5. Checking items and units...\n";
try {
    $itemUnits = DB::table('retail_food_items as rfi')
        ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
        ->join('items as i', 'rfi.item_id', '=', 'i.id')
        ->join('units as u', 'rfi.unit_id', '=', 'u.id')
        ->where('rf.payment_method', 'contra_bon')
        ->where('rf.status', 'approved')
        ->select('rf.id as retail_food_id', 'rf.retail_number', 'i.name as item_name', 'u.name as unit_name')
        ->get();
    
    echo "Found " . count($itemUnits) . " retail food items with valid items and units\n";
    
    if (count($itemUnits) > 0) {
        echo "Sample data:\n";
        foreach ($itemUnits->take(3) as $row) {
            echo "- Retail Food: {$row->retail_number}, Item: {$row->item_name}, Unit: {$row->unit_name}\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking items and units: " . $e->getMessage() . "\n";
}

// Test 6: Check if retail food already used in contra bon
echo "\n6. Checking retail food already used in contra bon...\n";
try {
    $usedRetailFoods = DB::table('food_contra_bons')
        ->where('source_type', 'retail_food')
        ->whereNotNull('source_id')
        ->pluck('source_id')
        ->toArray();
    
    echo "Found " . count($usedRetailFoods) . " retail food already used in contra bon\n";
    
    if (count($usedRetailFoods) > 0) {
        echo "Used retail food IDs: " . implode(', ', $usedRetailFoods) . "\n";
    }
} catch (Exception $e) {
    echo "Error checking used retail food: " . $e->getMessage() . "\n";
}

// Test 7: Simulate the complete API query
echo "\n7. Simulating the complete API query...\n";
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
        foreach ($retailFoods as $row) {
            echo "- ID: {$row->retail_food_id}, Number: {$row->retail_number}, Supplier: {$row->supplier_name}, Date: {$row->transaction_date}\n";
        }
    } else {
        echo "No available retail food found\n";
    }
} catch (Exception $e) {
    echo "Error in complete API query: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test 8: Check table structure
echo "\n8. Checking table structure...\n";
try {
    $tables = ['retail_food', 'suppliers', 'users', 'retail_food_items', 'items', 'units', 'food_contra_bons'];
    
    foreach ($tables as $table) {
        $exists = DB::table($table)->exists();
        echo "- Table {$table}: " . ($exists ? "EXISTS" : "NOT EXISTS") . "\n";
    }
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nKESIMPULAN:\n";
echo "1. Test ini akan membantu mengidentifikasi masalah pada API retail food contra bon\n";
echo "2. Periksa apakah ada data retail food dengan payment_method = 'contra_bon'\n";
echo "3. Periksa apakah ada data supplier dan user yang valid\n";
echo "4. Periksa apakah ada data items dan units yang valid\n";
echo "5. Periksa apakah retail food sudah digunakan di contra bon\n";
echo "6. Periksa apakah semua tabel yang diperlukan ada\n";
