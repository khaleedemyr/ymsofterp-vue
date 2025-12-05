<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== TEST CONTRA BON DEBUG ===\n";

// Test 1: Check API response structure
echo "\n1. Testing API response structure...\n";
try {
    // Simulate the API query
    $usedRetailFoods = DB::table('food_contra_bons')
        ->where('source_type', 'retail_food')
        ->whereNotNull('source_id')
        ->pluck('source_id')
        ->toArray();

    echo "Used retail food IDs: " . implode(', ', $usedRetailFoods) . "\n";

    $retailFoods = DB::table('retail_food as rf')
        ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
        ->join('users as creator', 'rf.created_by', '=', 'creator.id')
        ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
        ->leftJoin('warehouse_outlets as wo', 'rf.warehouse_outlet_id', '=', 'wo.id')
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
            'creator.nama_lengkap as creator_name',
            'o.nama_outlet as outlet_name',
            'wo.name as warehouse_outlet_name'
        )
        ->orderByDesc('rf.transaction_date')
        ->get();

    echo "Found " . count($retailFoods) . " retail food records\n";
    
    if (count($retailFoods) > 0) {
        $sampleRF = $retailFoods->first();
        echo "Sample retail food:\n";
        echo "- ID: {$sampleRF->retail_food_id}\n";
        echo "- Number: {$sampleRF->retail_number}\n";
        echo "- Date: {$sampleRF->transaction_date}\n";
        echo "- Outlet: " . ($sampleRF->outlet_name ?? 'null') . "\n";
        echo "- Warehouse Outlet: " . ($sampleRF->warehouse_outlet_name ?? 'null') . "\n";
        echo "- Supplier: {$sampleRF->supplier_name}\n";
        echo "- Creator: {$sampleRF->creator_name}\n";
        
        // Test items query
        $items = DB::table('retail_food_items as rfi')
            ->where('rfi.retail_food_id', $sampleRF->retail_food_id)
            ->select(
                'rfi.id',
                'rfi.item_name',
                'rfi.unit as unit_name',
                'rfi.qty',
                'rfi.price'
            )
            ->get();
        
        echo "Items for this retail food: " . count($items) . " items\n";
        foreach ($items as $item) {
            echo "- {$item->item_name} ({$item->unit_name}): {$item->qty} x {$item->price}\n";
        }
        
        // Simulate the complete API response
        $result = [
            'retail_food_id' => $sampleRF->retail_food_id,
            'retail_number' => $sampleRF->retail_number,
            'transaction_date' => $sampleRF->transaction_date,
            'total_amount' => $sampleRF->total_amount,
            'notes' => $sampleRF->notes,
            'supplier_id' => $sampleRF->supplier_id,
            'supplier_name' => $sampleRF->supplier_name,
            'creator_name' => $sampleRF->creator_name,
            'outlet_name' => $sampleRF->outlet_name,
            'warehouse_outlet_name' => $sampleRF->warehouse_outlet_name,
            'items' => $items->toArray(),
        ];
        
        echo "\nComplete API response structure:\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
} catch (Exception $e) {
    echo "Error in API test: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test 2: Check if there are any retail food with contra_bon payment method
echo "\n2. Checking retail food with contra_bon payment method...\n";
try {
    $count = DB::table('retail_food')
        ->where('payment_method', 'contra_bon')
        ->where('status', 'approved')
        ->count();
    
    echo "Total retail food with contra_bon payment method: {$count}\n";
    
    if ($count > 0) {
        $sample = DB::table('retail_food')
            ->where('payment_method', 'contra_bon')
            ->where('status', 'approved')
            ->first();
        
        echo "Sample retail food:\n";
        echo "- ID: {$sample->id}\n";
        echo "- Number: {$sample->retail_number}\n";
        echo "- Payment Method: {$sample->payment_method}\n";
        echo "- Status: {$sample->status}\n";
        echo "- Date: {$sample->transaction_date}\n";
    }
} catch (Exception $e) {
    echo "Error checking retail food: " . $e->getMessage() . "\n";
}

// Test 3: Check if any retail food is already used in contra bon
echo "\n3. Checking used retail food in contra bon...\n";
try {
    $usedCount = DB::table('food_contra_bons')
        ->where('source_type', 'retail_food')
        ->whereNotNull('source_id')
        ->count();
    
    echo "Retail food already used in contra bon: {$usedCount}\n";
    
    if ($usedCount > 0) {
        $used = DB::table('food_contra_bons')
            ->where('source_type', 'retail_food')
            ->whereNotNull('source_id')
            ->get();
        
        echo "Used retail food IDs:\n";
        foreach ($used as $u) {
            echo "- {$u->source_id}\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking used retail food: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nKESIMPULAN:\n";
echo "1. Pastikan ada retail food dengan payment_method = 'contra_bon' dan status = 'approved'\n";
echo "2. Pastikan retail food tersebut belum digunakan di contra bon\n";
echo "3. Pastikan retail food memiliki items di tabel retail_food_items\n";
echo "4. Cek browser console untuk melihat debug output dari frontend\n";
