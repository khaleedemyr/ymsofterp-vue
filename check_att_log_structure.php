<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking att_log Table Structure ===\n\n";

try {
    // Check if att_log table exists
    if (Schema::hasTable('att_log')) {
        echo "✓ Table 'att_log' exists\n";
        
        // Get table structure
        $columns = Schema::getColumnListing('att_log');
        echo "\nColumns in att_log table:\n";
        foreach ($columns as $column) {
            echo "  - {$column}\n";
        }
        
        // Check sample data
        echo "\nSample data from att_log (first 3 records):\n";
        $sampleData = DB::table('att_log')->limit(3)->get();
        if ($sampleData->count() > 0) {
            foreach ($sampleData as $record) {
                echo "  Record: " . json_encode($record) . "\n";
            }
        } else {
            echo "  No data found in att_log table\n";
        }
        
        // Check for time-related columns
        echo "\nChecking for time-related columns:\n";
        $timeColumns = ['checktime', 'check_time', 'datetime', 'created_at', 'updated_at', 'time'];
        foreach ($timeColumns as $col) {
            if (in_array($col, $columns)) {
                echo "  ✓ Found column: {$col}\n";
            } else {
                echo "  ✗ Column not found: {$col}\n";
            }
        }
        
    } else {
        echo "✗ Table 'att_log' does not exist\n";
        
        // Check for similar tables
        echo "\nChecking for similar tables:\n";
        $allTables = DB::select("SHOW TABLES");
        foreach ($allTables as $table) {
            $tableName = array_values((array)$table)[0];
            if (strpos($tableName, 'att') !== false || strpos($tableName, 'log') !== false) {
                echo "  - {$tableName}\n";
            }
        }
    }

    // Check users table structure
    echo "\n=== Checking users table structure ===\n";
    if (Schema::hasTable('users')) {
        $userColumns = Schema::getColumnListing('users');
        echo "Columns in users table:\n";
        foreach ($userColumns as $column) {
            echo "  - {$column}\n";
        }
    }

    // Check user_shifts table structure
    echo "\n=== Checking user_shifts table structure ===\n";
    if (Schema::hasTable('user_shifts')) {
        $shiftColumns = Schema::getColumnListing('user_shifts');
        echo "Columns in user_shifts table:\n";
        foreach ($shiftColumns as $column) {
            echo "  - {$column}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
