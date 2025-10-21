<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG ALYA PUTRI AMALIA - FINAL CALCULATION ===\n";

// Simulate the exact calculation from the system
$start = '2025-09-26';
$end = '2025-10-25';

echo "Processing attendance for ALYA PUTRI AMALIA (user_id: 2558)\n";
echo "Period: $start to $end\n\n";

// Get raw attendance data
$rawData = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', 2558)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->select('a.scan_date', 'a.inoutmode')
    ->orderBy('a.scan_date')
    ->get();

echo "Total raw attendance records: " . $rawData->count() . "\n\n";

// Process attendance data
$processedData = [];
foreach ($rawData as $scan) {
    $date = date('Y-m-d', strtotime($scan->scan_date));
    $key = '2558_' . $date;
    
    if (!isset($processedData[$key])) {
        $processedData[$key] = [
            'tanggal' => $date,
            'user_id' => 2558,
            'scans' => []
        ];
    }
    
    $processedData[$key]['scans'][] = [
        'scan_date' => $scan->scan_date,
        'inoutmode' => $scan->inoutmode
    ];
}

echo "Processed attendance days: " . count($processedData) . "\n\n";

// Calculate overtime for each day
$totalOvertime = 0;
$overtimeDetails = [];

foreach ($processedData as $key => $data) {
    $tanggal = $data['tanggal'];
    $scans = collect($data['scans']);
    
    // Get shift data
    $shift = DB::table('user_shifts as us')
        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
        ->where('us.user_id', 2558)
        ->where('us.tanggal', $tanggal)
        ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
        ->first();
    
    if (!$shift || !$shift->time_end) {
        continue;
    }
    
    // Find first IN and last OUT
    $inScans = $scans->where('inoutmode', 1);
    $outScans = $scans->where('inoutmode', 2);
    
    if ($inScans->isEmpty() || $outScans->isEmpty()) {
        continue;
    }
    
    $firstIn = $inScans->first()['scan_date'];
    $lastOut = $outScans->last()['scan_date'];
    
    // Calculate overtime
    $jamKeluarTime = date('H:i:s', strtotime($lastOut));
    $shiftEndTime = $shift->time_end;
    
    $keluarTimestamp = strtotime($jamKeluarTime);
    $shiftEndTimestamp = strtotime($shiftEndTime);
    $diffSeconds = $keluarTimestamp - $shiftEndTimestamp;
    
    $overtimeHours = 0;
    
    if ($diffSeconds < 0) {
        // Check if cross-day
        $checkoutHour = (int)date('H', strtotime($jamKeluarTime));
        $shiftEndHour = (int)date('H', strtotime($shiftEndTime));
        
        if ($checkoutHour >= 0 && $checkoutHour <= 6 && $shiftEndHour >= 17) {
            // Cross-day overtime
            $crossDaySeconds = (24 * 3600) + $diffSeconds;
            $overtimeHours = floor($crossDaySeconds / 3600);
        }
    } else {
        // Normal overtime
        $overtimeHours = floor($diffSeconds / 3600);
    }
    
    // Safety check
    $overtimeHours = min($overtimeHours, 12);
    if ($overtimeHours < 0) {
        $overtimeHours = 0;
    }
    
    $totalOvertime += $overtimeHours;
    
    if ($overtimeHours > 0) {
        $overtimeDetails[] = [
            'tanggal' => $tanggal,
            'jam_keluar' => $jamKeluarTime,
            'shift_end' => $shiftEndTime,
            'diff_seconds' => $diffSeconds,
            'overtime_hours' => $overtimeHours,
            'is_cross_day' => $diffSeconds < 0 && $checkoutHour >= 0 && $checkoutHour <= 6 && $shiftEndHour >= 17
        ];
    }
}

echo "Total calculated overtime: " . $totalOvertime . " hours\n\n";

// Get Extra Off overtime
$extraOffOvertime = DB::table('extra_off_transactions')
    ->where('user_id', 2558)
    ->where('source_type', 'overtime_work')
    ->where('transaction_type', 'earned')
    ->whereBetween('source_date', [$start, $end])
    ->get();

$totalExtraOffOvertime = 0;
foreach ($extraOffOvertime as $transaction) {
    if (preg_match('/\(([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
        $workHours = (float) $matches[1];
        $totalExtraOffOvertime += $workHours;
    }
}

echo "Extra Off overtime: " . $totalExtraOffOvertime . " hours\n";
echo "Total final overtime: " . ($totalOvertime + $totalExtraOffOvertime) . " hours\n\n";

echo "Overtime details:\n";
foreach ($overtimeDetails as $detail) {
    echo "Date: " . $detail['tanggal'] . 
         ", Checkout: " . $detail['jam_keluar'] . 
         ", Shift End: " . $detail['shift_end'] . 
         ", Overtime: " . $detail['overtime_hours'] . 
         " hours, Cross-day: " . ($detail['is_cross_day'] ? 'Yes' : 'No') . "\n";
}

echo "\n=== END DEBUG ===\n";
