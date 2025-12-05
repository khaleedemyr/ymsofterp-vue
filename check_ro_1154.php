<?php

/**
 * Script untuk mengecek RO dengan ID 1154
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING RO ID 1154 ===\n\n";

try {
    // Cek RO dengan ID 1154
    $ro = DB::table('food_floor_orders')
        ->where('id', 1154)
        ->first();
    
    if (!$ro) {
        echo "RO dengan ID 1154 tidak ditemukan!\n";
        exit;
    }
    
    echo "RO Details:\n";
    echo "ID: {$ro->id}\n";
    echo "Order Number: {$ro->order_number}\n";
    echo "Status: {$ro->status}\n";
    echo "Created: {$ro->created_at}\n";
    echo "Updated: {$ro->updated_at}\n\n";
    
    // Cek semua packing list untuk RO ini
    $packingLists = DB::table('food_packing_lists')
        ->where('food_floor_order_id', 1154)
        ->get();
    
    echo "Packing Lists:\n";
    if ($packingLists->count() == 0) {
        echo "  - No packing lists found\n";
    } else {
        foreach ($packingLists as $pl) {
            echo "  - PL ID {$pl->id}: {$pl->packing_number} (Status: {$pl->status}, Warehouse Division: {$pl->warehouse_division_id})\n";
        }
    }
    
    echo "\n";
    
    // Cek apakah ada DO untuk packing list ini
    if ($packingLists->count() > 0) {
        $packingListIds = $packingLists->pluck('id')->toArray();
        $deliveryOrders = DB::table('delivery_orders')
            ->whereIn('packing_list_id', $packingListIds)
            ->get();
        
        echo "Delivery Orders:\n";
        if ($deliveryOrders->count() == 0) {
            echo "  - No delivery orders found\n";
        } else {
            foreach ($deliveryOrders as $do) {
                echo "  - DO ID {$do->id}: {$do->number} (Created: {$do->created_at})\n";
            }
        }
    }
    
    echo "\n=== ANALYSIS ===\n";
    
    if ($ro->status === 'delivered') {
        echo "RO status is 'delivered'\n";
        
        if ($packingLists->count() > 0) {
            $incompletePackingLists = $packingLists->filter(function($pl) {
                return !in_array($pl->status, ['done', 'delivered']);
            });
            
            if ($incompletePackingLists->count() > 0) {
                echo "PROBLEM: Found {$incompletePackingLists->count()} incomplete packing list(s)\n";
                foreach ($incompletePackingLists as $pl) {
                    echo "  - PL ID {$pl->id}: status '{$pl->status}'\n";
                }
                echo "This RO should NOT be 'delivered'!\n";
            } else {
                echo "All packing lists are complete, status 'delivered' is correct\n";
            }
        } else {
            echo "No packing lists found, status 'delivered' might be correct\n";
        }
    } else {
        echo "RO status is '{$ro->status}', this is correct\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
