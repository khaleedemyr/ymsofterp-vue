<?php

/**
 * Script untuk memperbaiki status RO yang sudah terlanjur menjadi "delivered"
 * padahal masih ada packing list yang berstatus "packing"
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SCRIPT PERBAIKAN STATUS RO DELIVERED ===\n\n";

try {
    DB::beginTransaction();
    
    // Cari semua RO yang berstatus "delivered"
    $deliveredROs = DB::table('food_floor_orders')
        ->where('status', 'delivered')
        ->get();
    
    echo "Total RO dengan status 'delivered': " . $deliveredROs->count() . "\n\n";
    
    $fixedCount = 0;
    $checkedCount = 0;
    
    foreach ($deliveredROs as $ro) {
        $checkedCount++;
        echo "Checking RO ID: {$ro->id} (Order: {$ro->order_number})\n";
        
        // Cek semua packing list untuk RO ini
        $packingLists = DB::table('food_packing_lists')
            ->where('food_floor_order_id', $ro->id)
            ->get();
        
        if ($packingLists->count() == 0) {
            echo "  - No packing lists found, keeping status 'delivered'\n";
            continue;
        }
        
        echo "  - Found {$packingLists->count()} packing list(s)\n";
        
        // Cek status packing list
        $incompletePackingLists = $packingLists->filter(function($pl) {
            return !in_array($pl->status, ['done', 'delivered']);
        });
        
        if ($incompletePackingLists->count() > 0) {
            echo "  - Found {$incompletePackingLists->count()} incomplete packing list(s):\n";
            foreach ($incompletePackingLists as $pl) {
                echo "    * PL ID {$pl->id} ({$pl->packing_number}): status '{$pl->status}'\n";
            }
            
            // Update RO status back to 'packing'
            DB::table('food_floor_orders')
                ->where('id', $ro->id)
                ->update([
                    'status' => 'packing',
                    'updated_at' => now()
                ]);
            
            echo "  - FIXED: Updated RO status from 'delivered' to 'packing'\n";
            $fixedCount++;
        } else {
            echo "  - All packing lists are complete, keeping status 'delivered'\n";
        }
        
        echo "\n";
    }
    
    DB::commit();
    
    echo "=== SUMMARY ===\n";
    echo "Total RO checked: {$checkedCount}\n";
    echo "Total RO fixed: {$fixedCount}\n";
    echo "Script completed successfully!\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
