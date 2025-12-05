<?php

/**
 * Script untuk mengecek semua RO dan packing list untuk menemukan masalah
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING ALL RO STATUS ===\n\n";

try {
    // Cari semua RO yang memiliki packing list
    $rosWithPackingLists = DB::table('food_floor_orders as fo')
        ->join('food_packing_lists as pl', 'fo.id', '=', 'pl.food_floor_order_id')
        ->select('fo.id', 'fo.order_number', 'fo.status as ro_status', 'fo.updated_at')
        ->distinct()
        ->get();
    
    echo "Total RO dengan packing list: " . $rosWithPackingLists->count() . "\n\n";
    
    $problemCount = 0;
    
    foreach ($rosWithPackingLists as $ro) {
        echo "Checking RO ID: {$ro->id} (Order: {$ro->order_number}, Status: {$ro->ro_status})\n";
        
        // Cek semua packing list untuk RO ini
        $packingLists = DB::table('food_packing_lists')
            ->where('food_floor_order_id', $ro->id)
            ->get();
        
        echo "  - Found {$packingLists->count()} packing list(s):\n";
        
        $incompletePackingLists = [];
        $completePackingLists = [];
        
        foreach ($packingLists as $pl) {
            echo "    * PL ID {$pl->id}: {$pl->packing_number} (Status: {$pl->status}, Warehouse Division: {$pl->warehouse_division_id})\n";
            
            if (in_array($pl->status, ['done', 'delivered'])) {
                $completePackingLists[] = $pl;
            } else {
                $incompletePackingLists[] = $pl;
            }
        }
        
        // Cek apakah ada masalah
        if ($ro->ro_status === 'delivered' && count($incompletePackingLists) > 0) {
            echo "  - PROBLEM: RO status 'delivered' but has incomplete packing lists!\n";
            echo "    Incomplete: " . count($incompletePackingLists) . ", Complete: " . count($completePackingLists) . "\n";
            $problemCount++;
        } elseif ($ro->ro_status === 'packing' && count($incompletePackingLists) > 0) {
            echo "  - OK: RO status 'packing' with incomplete packing lists\n";
        } elseif ($ro->ro_status === 'delivered' && count($incompletePackingLists) === 0) {
            echo "  - OK: RO status 'delivered' with all complete packing lists\n";
        }
        
        echo "\n";
    }
    
    echo "=== SUMMARY ===\n";
    echo "Total RO checked: " . $rosWithPackingLists->count() . "\n";
    echo "Total RO with problems: {$problemCount}\n";
    
    if ($problemCount > 0) {
        echo "\nROs with problems need to be fixed!\n";
    } else {
        echo "\nAll RO statuses are correct!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
