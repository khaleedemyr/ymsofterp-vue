<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Outlet Status ===\n\n";

try {
    // Check all outlets
    echo "1. Checking all outlets in tbl_data_outlet:\n";
    $allOutlets = DB::table('tbl_data_outlet')->get();
    echo "   Total outlets: " . $allOutlets->count() . "\n";
    
    if ($allOutlets->count() > 0) {
        echo "   Sample data:\n";
        foreach ($allOutlets->take(3) as $outlet) {
            echo "   - ID: {$outlet->id_outlet}, Name: {$outlet->nama_outlet}, Status: '{$outlet->status}'\n";
        }
    }

    // Check outlets with status A
    echo "\n2. Checking outlets with status='A':\n";
    $activeOutlets = DB::table('tbl_data_outlet')
        ->where('status', 'A')
        ->get();
    echo "   Active outlets: " . $activeOutlets->count() . "\n";
    
    if ($activeOutlets->count() > 0) {
        echo "   Active outlet data:\n";
        foreach ($activeOutlets->take(5) as $outlet) {
            echo "   - ID: {$outlet->id_outlet}, Name: {$outlet->nama_outlet}\n";
        }
    }

    // Check outlets with different status values
    echo "\n3. Checking all unique status values:\n";
    $statusValues = DB::table('tbl_data_outlet')
        ->select('status')
        ->distinct()
        ->get();
    
    foreach ($statusValues as $status) {
        $count = DB::table('tbl_data_outlet')->where('status', $status->status)->count();
        echo "   Status '{$status->status}': {$count} outlets\n";
    }

    // Test the exact query from controller
    echo "\n4. Testing controller query:\n";
    try {
        $controllerQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->get();
        echo "   ✓ Controller query successful\n";
        echo "   Found: " . $controllerQuery->count() . " outlets\n";
        
        if ($controllerQuery->count() > 0) {
            echo "   Sample results:\n";
            foreach ($controllerQuery->take(3) as $outlet) {
                echo "   - ID: {$outlet->id}, Name: {$outlet->name}\n";
            }
        }
    } catch (Exception $e) {
        echo "   ✗ Controller query failed: " . $e->getMessage() . "\n";
    }

    // Check if we need to update status values
    echo "\n5. Recommendations:\n";
    if ($activeOutlets->count() == 0) {
        echo "   ⚠️  No outlets with status='A' found!\n";
        echo "   You may need to update outlet status values.\n";
        
        // Show how to update
        $firstOutlet = DB::table('tbl_data_outlet')->first();
        if ($firstOutlet) {
            echo "   Example: Update outlet ID {$firstOutlet->id_outlet} to status 'A'\n";
            echo "   SQL: UPDATE tbl_data_outlet SET status = 'A' WHERE id_outlet = {$firstOutlet->id_outlet};\n";
        }
    } else {
        echo "   ✓ Found {$activeOutlets->count()} active outlets\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
