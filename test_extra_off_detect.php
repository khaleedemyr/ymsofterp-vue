<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Extra Off Detect Command ===\n\n";

try {
    // Test date (yesterday)
    $testDate = Carbon::yesterday()->format('Y-m-d');
    echo "Testing date: {$testDate}\n\n";

    // Test the query directly
    echo "1. Testing the corrected query:\n";
    $query = "
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
            AND NOT EXISTS (
                SELECT 1 FROM extra_off_transactions eot 
                WHERE eot.user_id = u.id 
                AND eot.source_date = DATE(a.scan_date)
                AND eot.source_type = 'unscheduled_work'
                AND eot.transaction_type = 'earned'
            )
            AND NOT EXISTS (
                SELECT 1 FROM tbl_kalender_perusahaan kp 
                WHERE kp.tgl_libur = DATE(a.scan_date)
            )
        GROUP BY u.id, DATE(a.scan_date)
        ORDER BY DATE(a.scan_date) DESC, u.id
    ";

    $results = DB::select($query, [$testDate]);
    echo "   ✓ Query executed successfully\n";
    echo "   Found " . count($results) . " unscheduled workers\n";

    if (count($results) > 0) {
        echo "   Sample results:\n";
        foreach (array_slice($results, 0, 3) as $result) {
            echo "   - User ID: {$result->user_id}, Name: {$result->nama_lengkap}, Date: {$result->work_date}\n";
        }
    }

    // Test command execution
    echo "\n2. Testing command execution:\n";
    try {
        $exitCode = \Artisan::call('extra-off:detect', [
            '--date' => $testDate
        ]);
        
        $output = \Artisan::output();
        echo "   Exit code: {$exitCode}\n";
        echo "   Output:\n{$output}\n";
        
        if ($exitCode === 0) {
            echo "   ✓ Command executed successfully\n";
        } else {
            echo "   ✗ Command failed with exit code {$exitCode}\n";
        }
        
    } catch (Exception $e) {
        echo "   ✗ Command execution failed: " . $e->getMessage() . "\n";
    }

    // Check if tables exist
    echo "\n3. Checking required tables:\n";
    $tables = ['att_log', 'users', 'user_shifts', 'extra_off_transactions', 'extra_off_balance', 'tbl_kalender_perusahaan'];
    foreach ($tables as $table) {
        try {
            $count = DB::table($table)->count();
            echo "   ✓ Table '{$table}' exists - {$count} records\n";
        } catch (Exception $e) {
            echo "   ✗ Table '{$table}' error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Summary ===\n";
    echo "Extra off detect command testing completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
