<?php

/**
 * Script untuk test fix attendance correction
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test Attendance Correction Fix ===\n\n";

try {
    // Test validation with different data types
    echo "1. Testing validation rules:\n";
    
    $testCases = [
        ['inoutmode' => '1', 'expected' => 'valid'], // IN
        ['inoutmode' => '2', 'expected' => 'valid'], // OUT
        ['inoutmode' => '3', 'expected' => 'valid'],
        ['inoutmode' => '4', 'expected' => 'valid'],
        ['inoutmode' => '5', 'expected' => 'valid'],
        ['inoutmode' => 1, 'expected' => 'valid'], // IN
        ['inoutmode' => 2, 'expected' => 'valid'], // OUT
        ['inoutmode' => 3, 'expected' => 'valid'],
        ['inoutmode' => 4, 'expected' => 'valid'],
        ['inoutmode' => 5, 'expected' => 'valid'],
        ['inoutmode' => '0', 'expected' => 'invalid'],
        ['inoutmode' => 0, 'expected' => 'invalid'],
        ['inoutmode' => '6', 'expected' => 'invalid'],
        ['inoutmode' => 'a', 'expected' => 'invalid'],
        ['inoutmode' => null, 'expected' => 'invalid'],
        ['inoutmode' => '', 'expected' => 'invalid'],
    ];
    
    foreach ($testCases as $testCase) {
        $validator = \Illuminate\Support\Facades\Validator::make(
            $testCase,
            ['inoutmode' => 'required|in:1,2,3,4,5,"1","2","3","4","5"']
        );
        
        $isValid = !$validator->fails();
        $expected = $testCase['expected'] === 'valid';
        $status = ($isValid === $expected) ? '✓' : '✗';
        
        echo "   {$status} Value: {$testCase['inoutmode']} (Type: " . gettype($testCase['inoutmode']) . ") - " . 
             ($isValid ? 'Valid' : 'Invalid') . " (Expected: {$testCase['expected']})\n";
        
        if (!$isValid && $expected) {
            echo "      Error: " . implode(', ', $validator->errors()->get('inoutmode')) . "\n";
        }
    }
    
    // Test integer conversion
    echo "\n2. Testing integer conversion:\n";
    
    $conversionTests = [
        '1' => 1, // IN
        '2' => 2, // OUT
        '3' => 3,
        '4' => 4,
        '5' => 5,
        1 => 1, // IN
        2 => 2, // OUT
        3 => 3,
        4 => 4,
        5 => 5,
    ];
    
    foreach ($conversionTests as $input => $expected) {
        $result = (int) $input;
        $status = ($result === $expected) ? '✓' : '✗';
        echo "   {$status} (int) '{$input}' = {$result} (Expected: {$expected})\n";
    }
    
    // Test with actual att_log data
    echo "\n3. Testing with actual att_log data:\n";
    
    $sampleRecord = DB::table('att_log')
        ->select('sn', 'pin', 'scan_date', 'inoutmode')
        ->first();
    
    if ($sampleRecord) {
        echo "   Sample record found:\n";
        echo "   SN: {$sampleRecord->sn}\n";
        echo "   PIN: {$sampleRecord->pin}\n";
        echo "   Scan Date: {$sampleRecord->scan_date}\n";
        echo "   InOutMode: {$sampleRecord->inoutmode} (Type: " . gettype($sampleRecord->inoutmode) . ")\n";
        
        // Test validation with this data
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['inoutmode' => $sampleRecord->inoutmode],
            ['inoutmode' => 'required|in:1,2,3,4,5,"1","2","3","4","5"']
        );
        
        if ($validator->fails()) {
            echo "   ✗ Validation failed: " . implode(', ', $validator->errors()->get('inoutmode')) . "\n";
        } else {
            echo "   ✓ Validation passed\n";
        }
        
        // Test conversion
        $converted = (int) $sampleRecord->inoutmode;
        echo "   Converted to integer: {$converted}\n";
    } else {
        echo "   No sample records found in att_log\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
