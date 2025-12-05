<?php

/**
 * Script untuk debug error attendance correction
 * Error: "The selected inoutmode is invalid"
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Attendance Correction Error ===\n\n";

try {
    // Check att_log table structure
    echo "1. Checking att_log table structure:\n";
    $columns = DB::select("SHOW COLUMNS FROM att_log");
    foreach ($columns as $column) {
        if ($column->Field === 'inoutmode') {
            echo "   inoutmode: Type={$column->Type}, Null={$column->Null}, Default={$column->Default}\n";
        }
    }
    
    // Check sample data from att_log
    echo "\n2. Sample data from att_log (inoutmode values):\n";
    $sampleData = DB::table('att_log')
        ->select('sn', 'pin', 'scan_date', 'inoutmode')
        ->limit(5)
        ->get();
    
    foreach ($sampleData as $record) {
        echo "   SN: {$record->sn}, PIN: {$record->pin}, Date: {$record->scan_date}, InOutMode: {$record->inoutmode} (Type: " . gettype($record->inoutmode) . ")\n";
    }
    
    // Check unique inoutmode values
    echo "\n3. Unique inoutmode values in att_log:\n";
    $uniqueValues = DB::table('att_log')
        ->select('inoutmode')
        ->distinct()
        ->get();
    
    foreach ($uniqueValues as $value) {
        echo "   Value: {$value->inoutmode} (Type: " . gettype($value->inoutmode) . ")\n";
    }
    
    // Check validation rules in controller
    echo "\n4. Current validation rules in ScheduleAttendanceCorrectionController:\n";
    echo "   'inoutmode' => 'required|in:1,2,3,4,5,\"1\",\"2\",\"3\",\"4\",\"5\"'\n";
    
    // Test validation with different data types
    echo "\n5. Testing validation with different data types:\n";
    
    $testValues = [
        '1' => 'string one (IN)',
        '2' => 'string two (OUT)', 
        '3' => 'string three',
        '4' => 'string four',
        '5' => 'string five',
        1 => 'integer one (IN)',
        2 => 'integer two (OUT)',
        3 => 'integer three',
        4 => 'integer four',
        5 => 'integer five',
        '0' => 'string zero (invalid)',
        0 => 'integer zero (invalid)',
        null => 'null',
        '' => 'empty string'
    ];
    
    foreach ($testValues as $value => $description) {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['inoutmode' => $value],
            ['inoutmode' => 'required|in:1,2,3,4,5,"1","2","3","4","5"']
        );
        
        if ($validator->fails()) {
            echo "   ✗ {$description} ({$value}): " . implode(', ', $validator->errors()->get('inoutmode')) . "\n";
        } else {
            echo "   ✓ {$description} ({$value}): Valid\n";
        }
    }
    
    // Check if there are any non-standard inoutmode values
    echo "\n6. Checking for non-standard inoutmode values:\n";
    $nonStandard = DB::table('att_log')
        ->whereNotIn('inoutmode', [1, 2, 3, 4, 5])
        ->select('inoutmode')
        ->distinct()
        ->get();
    
    if ($nonStandard->count() > 0) {
        echo "   Found non-standard values:\n";
        foreach ($nonStandard as $value) {
            echo "   - {$value->inoutmode} (Type: " . gettype($value->inoutmode) . ")\n";
        }
    } else {
        echo "   ✓ All inoutmode values are standard (1, 2, 3, 4, or 5)\n";
    }
    
    // Check recent attendance correction attempts
    echo "\n7. Checking recent attendance correction attempts:\n";
    $recentAttempts = DB::table('schedule_attendance_correction_approvals')
        ->where('type', 'attendance')
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get();
    
    if ($recentAttempts->count() > 0) {
        foreach ($recentAttempts as $attempt) {
            $oldValue = json_decode($attempt->old_value, true);
            $newValue = json_decode($attempt->new_value, true);
            echo "   Attempt ID: {$attempt->id}\n";
            echo "   Old inoutmode: " . ($oldValue['inoutmode'] ?? 'N/A') . " (Type: " . gettype($oldValue['inoutmode'] ?? null) . ")\n";
            echo "   New inoutmode: " . ($newValue['inoutmode'] ?? 'N/A') . " (Type: " . gettype($newValue['inoutmode'] ?? null) . ")\n";
            echo "   Status: {$attempt->status}\n";
            echo "   ---\n";
        }
    } else {
        echo "   No recent attendance correction attempts found\n";
    }
    
    echo "\n=== Debug Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
