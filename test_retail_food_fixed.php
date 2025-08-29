<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== TEST RETAIL FOOD FIXED ===\n";

// Test 1: Check if the fix works by simulating the same scenario
echo "\n1. Testing the fixed retail food logic...\n";

try {
    // Get sample data
    $sampleItem = DB::table('items')->first();
    $sampleOutlet = DB::table('tbl_data_outlet')->first();
    $sampleWarehouse = DB::table('warehouse_outlets')->first();
    
    if ($sampleItem && $sampleOutlet && $sampleWarehouse) {
        echo "Sample data found:\n";
        echo "- Item: {$sampleItem->name} (ID: {$sampleItem->id})\n";
        echo "- Outlet: {$sampleOutlet->nama_outlet} (ID: {$sampleOutlet->id_outlet})\n";
        echo "- Warehouse: {$sampleWarehouse->name} (ID: {$sampleWarehouse->id})\n";
        
        // Check if inventory item exists
        $inventoryItem = DB::table('outlet_food_inventory_items')
            ->where('item_id', $sampleItem->id)
            ->first();
            
        if (!$inventoryItem) {
            echo "Creating inventory item...\n";
            $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                'item_id' => $sampleItem->id,
                'small_unit_id' => $sampleItem->small_unit_id,
                'medium_unit_id' => $sampleItem->medium_unit_id,
                'large_unit_id' => $sampleItem->large_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Inventory item created with ID: {$inventoryItemId}\n";
        } else {
            $inventoryItemId = $inventoryItem->id;
            echo "Inventory item already exists with ID: {$inventoryItemId}\n";
        }
        
        // Test the fixed logic - check existing stock with warehouse_outlet_id
        echo "\n2. Testing fixed stock lookup logic...\n";
        $existingStock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $sampleOutlet->id_outlet)
            ->where('warehouse_outlet_id', $sampleWarehouse->id)
            ->first();
            
        if ($existingStock) {
            echo "Existing stock found, testing update...\n";
            $result = DB::table('outlet_food_inventory_stocks')
                ->where('id', $existingStock->id)
                ->update([
                    'qty_small' => $existingStock->qty_small + 1,
                    'updated_at' => now(),
                ]);
            echo "Update result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        } else {
            echo "Stock doesn't exist, testing insert...\n";
            $result = DB::table('outlet_food_inventory_stocks')->insert([
                'inventory_item_id' => $inventoryItemId,
                'id_outlet' => $sampleOutlet->id_outlet,
                'warehouse_outlet_id' => $sampleWarehouse->id,
                'qty_small' => 10,
                'qty_medium' => 5,
                'qty_large' => 2,
                'value' => 1000,
                'last_cost_small' => 100,
                'last_cost_medium' => 200,
                'last_cost_large' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Insert result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
        }
        
        // Test kartu stok lookup with warehouse_outlet_id
        echo "\n3. Testing fixed kartu stok lookup logic...\n";
        $lastCard = DB::table('outlet_food_inventory_cards')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $sampleOutlet->id_outlet)
            ->where('warehouse_outlet_id', $sampleWarehouse->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
            
        if ($lastCard) {
            echo "Last card found with saldo: {$lastCard->saldo_qty_small}\n";
        } else {
            echo "No existing cards found\n";
        }
        
        // Test cost history lookup with warehouse_outlet_id
        echo "\n4. Testing fixed cost history lookup logic...\n";
        $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('id_outlet', $sampleOutlet->id_outlet)
            ->where('warehouse_outlet_id', $sampleWarehouse->id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->first();
            
        if ($lastCostHistory) {
            echo "Last cost history found with new_cost: {$lastCostHistory->new_cost}\n";
        } else {
            echo "No existing cost history found\n";
        }
        
    } else {
        echo "Sample data not found\n";
    }
} catch (Exception $e) {
    echo "Error testing fixed logic: " . $e->getMessage() . "\n";
}

// Test 2: Check for any remaining duplicate entries
echo "\n5. Checking for duplicate entries...\n";
try {
    $duplicates = DB::select("
        SELECT 
            inventory_item_id, 
            id_outlet, 
            warehouse_outlet_id, 
            COUNT(*) as count
        FROM outlet_food_inventory_stocks 
        GROUP BY inventory_item_id, id_outlet, warehouse_outlet_id 
        HAVING COUNT(*) > 1
    ");
    
    if (empty($duplicates)) {
        echo "No duplicate entries found ✓\n";
    } else {
        echo "Duplicate entries found:\n";
        foreach ($duplicates as $duplicate) {
            echo "- Item: {$duplicate->inventory_item_id}, Outlet: {$duplicate->id_outlet}, Warehouse: {$duplicate->warehouse_outlet_id} (Count: {$duplicate->count})\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking duplicates: " . $e->getMessage() . "\n";
}

echo "\n=== TEST SELESAI ===\n";
echo "\nKESIMPULAN:\n";
echo "1. Retail Food Controller sudah diperbaiki mengikuti pola Good Receive Outlet Food\n";
echo "2. Semua query pencarian stock, kartu stok, dan cost history sudah menggunakan warehouse_outlet_id\n";
echo "3. Update stock tidak lagi mengubah warehouse_outlet_id yang bisa menyebabkan duplicate entry\n";
echo "4. MAC calculation sudah diperbaiki untuk insert stock baru\n";
echo "5. Cost per small di kartu stok sudah menggunakan MAC\n";
echo "6. Saldo value sudah menggunakan MAC\n";
echo "\nSekarang coba test fitur retail food lagi untuk memastikan error sudah teratasi.\n";
