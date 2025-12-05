<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Final Check user_shifts Table Structure ===\n\n";

try {
    // Check user_shifts table structure
    echo "1. user_shifts table structure:\n";
    if (Schema::hasTable('user_shifts')) {
        $columns = Schema::getColumnListing('user_shifts');
        echo "   Columns: " . implode(', ', $columns) . "\n";
        
        // Check if status column exists
        if (Schema::hasColumn('user_shifts', 'status')) {
            echo "   ✓ Column 'status' exists\n";
        } else {
            echo "   ✗ Column 'status' does NOT exist\n";
        }
        
        // Check sample data
        $sample = DB::table('user_shifts')->limit(3)->get();
        if ($sample->count() > 0) {
            echo "   Sample records:\n";
            foreach ($sample as $record) {
                echo "   - " . json_encode($record) . "\n";
            }
        } else {
            echo "   No data found in user_shifts table\n";
        }
    } else {
        echo "   ✗ user_shifts table not found\n";
    }

    // Test the final corrected query
    echo "\n2. Testing final corrected query (without status):\n";
    try {
        $testQuery = "
            SELECT 
                u.id as user_id,
                u.nama_lengkap,
                DATE(a.scan_date) as work_date,
                us.tanggal as shift_date,
                us.id as shift_id
            FROM att_log a
            INNER JOIN user_pins up ON a.pin = up.pin
            INNER JOIN users u ON up.user_id = u.id
            LEFT JOIN user_shifts us ON u.id = us.user_id 
                AND DATE(a.scan_date) = DATE(us.tanggal)
            WHERE 
                a.inoutmode = 1
                AND DATE(a.scan_date) = ?
            LIMIT 5
        ";
        
        $testDate = date('Y-m-d', strtotime('-1 day')); // Yesterday
        $results = DB::select($testQuery, [$testDate]);
        echo "   ✓ Query executed successfully\n";
        echo "   Found " . count($results) . " records for date {$testDate}\n";
        
        if (count($results) > 0) {
            echo "   Sample results:\n";
            foreach ($results as $result) {
                $shiftInfo = $result->shift_id ? "Has shift (ID: {$result->shift_id})" : "No shift";
                echo "   - User: {$result->nama_lengkap}, Work Date: {$result->work_date}, {$shiftInfo}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ✗ Query failed: " . $e->getMessage() . "\n";
    }

    // Test the extra-off detection query
    echo "\n3. Testing extra-off detection query:\n";
    try {
        $detectionQuery = "
            SELECT 
                u.id as user_id,
                u.nama_lengkap,
                u.nik,
                DATE(a.scan_date) as work_date,
                COUNT(*) as attendance_count
            FROM att_log a
            INNER JOIN user_pins up ON a.pin = up.pin
            INNER JOIN users u ON up.user_id = u.id
            LEFT JOIN user_shifts us ON u.id = us.user_id 
                AND DATE(a.scan_date) = DATE(us.tanggal)
            WHERE 
                a.inoutmode = 1
                AND DATE(a.scan_date) = ?
                AND us.id IS NULL
                AND u.status = 'A'
            GROUP BY u.id, DATE(a.scan_date)
            ORDER BY a.scan_date DESC
        ";
        
        $testDate = date('Y-m-d', strtotime('-1 day')); // Yesterday
        $results = DB::select($detectionQuery, [$testDate]);
        echo "   ✓ Detection query executed successfully\n";
        echo "   Found " . count($results) . " unscheduled workers for date {$testDate}\n";
        
        if (count($results) > 0) {
            echo "   Unscheduled workers:\n";
            foreach ($results as $result) {
                echo "   - User: {$result->nama_lengkap} (ID: {$result->user_id}), Work Date: {$result->work_date}, Attendance Count: {$result->attendance_count}\n";
            }
        } else {
            echo "   No unscheduled workers found for this date.\n";
        }
        
    } catch (Exception $e) {
        echo "   ✗ Detection query failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "Final user_shifts structure analysis completed.\n";
    echo "The query should work without the 'status' condition.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
