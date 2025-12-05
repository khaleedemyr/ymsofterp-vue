<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking user_shifts Table Structure ===\n\n";

try {
    // Check user_shifts table structure
    echo "1. user_shifts table structure:\n";
    if (Schema::hasTable('user_shifts')) {
        $columns = Schema::getColumnListing('user_shifts');
        echo "   Columns: " . implode(', ', $columns) . "\n";
        
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

    // Check for date-related columns
    echo "\n2. Checking for date-related columns:\n";
    $dateColumns = ['tanggal', 'shift_date', 'date', 'created_at', 'updated_at'];
    foreach ($dateColumns as $col) {
        if (Schema::hasColumn('user_shifts', $col)) {
            echo "   ✓ Found column: {$col}\n";
        } else {
            echo "   ✗ Column not found: {$col}\n";
        }
    }

    // Test the corrected query
    echo "\n3. Testing corrected query:\n";
    try {
        $testQuery = "
            SELECT 
                u.id as user_id,
                u.nama_lengkap,
                DATE(a.scan_date) as work_date,
                us.tanggal as shift_date,
                us.status as shift_status
            FROM att_log a
            INNER JOIN user_pins up ON a.pin = up.pin
            INNER JOIN users u ON up.user_id = u.id
            LEFT JOIN user_shifts us ON u.id = us.user_id 
                AND DATE(a.scan_date) = DATE(us.tanggal)
                AND us.status = 'active'
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
                $shiftInfo = $result->shift_date ? "Shift: {$result->shift_date}" : "No shift";
                echo "   - User: {$result->nama_lengkap}, Work Date: {$result->work_date}, {$shiftInfo}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ✗ Query failed: " . $e->getMessage() . "\n";
    }

    // Check if there are any user_shifts records
    echo "\n4. Checking user_shifts data:\n";
    try {
        $totalShifts = DB::table('user_shifts')->count();
        $activeShifts = DB::table('user_shifts')->where('status', 'active')->count();
        echo "   Total user_shifts records: {$totalShifts}\n";
        echo "   Active user_shifts records: {$activeShifts}\n";
        
        if ($totalShifts > 0) {
            $sampleShift = DB::table('user_shifts')->first();
            echo "   Sample shift record: " . json_encode($sampleShift) . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ✗ Error checking user_shifts data: " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "user_shifts structure analysis completed.\n";
    echo "The correct column name is 'tanggal' (not 'shift_date').\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
