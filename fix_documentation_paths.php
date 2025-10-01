<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Fixing double-encoded documentation_paths...\n";

// Get all dynamic inspection details with documentation_paths
$details = DB::table('dynamic_inspection_details')
    ->whereNotNull('documentation_paths')
    ->where('documentation_paths', '!=', '[]')
    ->where('documentation_paths', '!=', 'null')
    ->get();

echo "Found " . $details->count() . " records to fix...\n";

foreach ($details as $detail) {
    $currentPaths = $detail->documentation_paths;
    echo "Record ID: {$detail->id}, Current: {$currentPaths}\n";
    
    // Try to decode the double-encoded JSON
    $decoded = json_decode($currentPaths, true);
    
    if (is_array($decoded)) {
        // If it's an array, it means it was double-encoded
        $fixedPaths = json_encode($decoded);
        echo "Fixed: {$fixedPaths}\n";
        
        DB::table('dynamic_inspection_details')
            ->where('id', $detail->id)
            ->update(['documentation_paths' => $fixedPaths]);
    } else {
        echo "Skipped (not double-encoded)\n";
    }
}

echo "Done!\n";
