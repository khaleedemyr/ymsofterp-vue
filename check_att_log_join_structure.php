<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking att_log JOIN Structure ===\n\n";

try {
    // Check att_log table structure
    echo "1. att_log table structure:\n";
    if (Schema::hasTable('att_log')) {
        $columns = Schema::getColumnListing('att_log');
        echo "   Columns: " . implode(', ', $columns) . "\n";
        
        // Check sample data
        $sample = DB::table('att_log')->limit(1)->first();
        if ($sample) {
            echo "   Sample record: " . json_encode($sample) . "\n";
        }
    } else {
        echo "   ✗ att_log table not found\n";
    }

    // Check user_pins table structure
    echo "\n2. user_pins table structure:\n";
    if (Schema::hasTable('user_pins')) {
        $columns = Schema::getColumnListing('user_pins');
        echo "   Columns: " . implode(', ', $columns) . "\n";
        
        // Check sample data
        $sample = DB::table('user_pins')->limit(1)->first();
        if ($sample) {
            echo "   Sample record: " . json_encode($sample) . "\n";
        }
    } else {
        echo "   ✗ user_pins table not found\n";
    }

    // Check users table structure
    echo "\n3. users table structure:\n";
    if (Schema::hasTable('users')) {
        $columns = Schema::getColumnListing('users');
        echo "   Columns: " . implode(', ', $columns) . "\n";
    } else {
        echo "   ✗ users table not found\n";
    }

    // Test the correct JOIN
    echo "\n4. Testing correct JOIN query:\n";
    try {
        $testQuery = "
            SELECT 
                u.id as user_id,
                u.nama_lengkap,
                a.scan_date,
                a.pin,
                up.user_id as pin_user_id
            FROM att_log a
            INNER JOIN user_pins up ON a.pin = up.pin
            INNER JOIN users u ON up.user_id = u.id
            LIMIT 3
        ";
        
        $results = DB::select($testQuery);
        echo "   ✓ JOIN query successful\n";
        echo "   Found " . count($results) . " records\n";
        
        if (count($results) > 0) {
            echo "   Sample results:\n";
            foreach ($results as $result) {
                echo "   - User ID: {$result->user_id}, Name: {$result->nama_lengkap}, PIN: {$result->pin}, Scan Date: {$result->scan_date}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ✗ JOIN query failed: " . $e->getMessage() . "\n";
    }

    // Test the old (wrong) JOIN
    echo "\n5. Testing old (wrong) JOIN query:\n";
    try {
        $wrongQuery = "
            SELECT 
                u.id as user_id,
                u.nama_lengkap
            FROM att_log a
            INNER JOIN users u ON a.userid = u.id
            LIMIT 1
        ";
        
        $results = DB::select($wrongQuery);
        echo "   ✓ Old JOIN query worked (unexpected!)\n";
        
    } catch (Exception $e) {
        echo "   ✗ Old JOIN query failed (expected): " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "Structure analysis completed.\n";
    echo "The correct JOIN pattern is: att_log -> user_pins -> users\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
