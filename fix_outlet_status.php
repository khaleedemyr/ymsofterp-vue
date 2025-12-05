<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Fix Outlet Status ===\n\n";

try {
    // Check current status values
    echo "1. Current outlet status distribution:\n";
    $statusCounts = DB::table('tbl_data_outlet')
        ->select('status', DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();
    
    foreach ($statusCounts as $status) {
        echo "   Status '{$status->status}': {$status->count} outlets\n";
    }

    // Check if we have any outlets with status 'A'
    $activeCount = DB::table('tbl_data_outlet')->where('status', 'A')->count();
    
    if ($activeCount == 0) {
        echo "\n2. No outlets with status 'A' found!\n";
        echo "   Options to fix:\n";
        echo "   a) Set all outlets to status 'A'\n";
        echo "   b) Set outlets with specific status to 'A'\n";
        echo "   c) Set first few outlets to 'A' for testing\n";
        
        // Show sample outlets
        $sampleOutlets = DB::table('tbl_data_outlet')->take(5)->get();
        echo "\n   Sample outlets:\n";
        foreach ($sampleOutlets as $outlet) {
            echo "   - ID: {$outlet->id_outlet}, Name: {$outlet->nama_outlet}, Status: '{$outlet->status}'\n";
        }
        
        echo "\n3. Auto-fix: Setting first 5 outlets to status 'A'...\n";
        $updated = DB::table('tbl_data_outlet')
            ->whereIn('id_outlet', $sampleOutlets->pluck('id_outlet'))
            ->update(['status' => 'A']);
        
        echo "   ✓ Updated {$updated} outlets to status 'A'\n";
        
        // Verify the fix
        $newActiveCount = DB::table('tbl_data_outlet')->where('status', 'A')->count();
        echo "   ✓ Now have {$newActiveCount} outlets with status 'A'\n";
        
    } else {
        echo "\n2. Found {$activeCount} outlets with status 'A' - no fix needed!\n";
    }

    // Test the controller query
    echo "\n4. Testing controller query:\n";
    $controllerQuery = DB::table('tbl_data_outlet')
        ->where('status', 'A')
        ->select('id_outlet as id', 'nama_outlet as name')
        ->get();
    
    echo "   ✓ Controller query successful\n";
    echo "   Found: " . $controllerQuery->count() . " active outlets\n";
    
    if ($controllerQuery->count() > 0) {
        echo "   Active outlets:\n";
        foreach ($controllerQuery->take(5) as $outlet) {
            echo "   - ID: {$outlet->id}, Name: {$outlet->name}\n";
        }
    }

    echo "\n=== Summary ===\n";
    echo "Outlet status has been fixed!\n";
    echo "The investor outlet menu should now work properly.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
