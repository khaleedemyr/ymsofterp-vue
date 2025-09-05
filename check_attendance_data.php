<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING ACTUAL ATTENDANCE DATA ===\n\n";

try {
    // Test with user that has shifts
    $user = auth()->loginUsingId(2);
    
    if (!$user) {
        echo "❌ Could not login test user\n";
        exit;
    }
    
    echo "Testing with user: {$user->nama_lengkap} (ID: {$user->id})\n\n";
    
    // Check actual attendance data
    echo "1. Checking actual attendance data...\n";
    
    // Check att_log data for this user
    $attLogData = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $user->id)
        ->select('a.scan_date', 'a.inoutmode', 'o.nama_outlet')
        ->orderBy('a.scan_date', 'desc')
        ->limit(10)
        ->get();
    
    echo "   Recent att_log data for user:\n";
    if ($attLogData->count() > 0) {
        foreach ($attLogData as $log) {
            echo "   - {$log->scan_date} | {$log->inoutmode} | {$log->nama_outlet}\n";
        }
    } else {
        echo "   No att_log data found for this user.\n";
    }
    
    // Check user_shifts data
    echo "\n2. Checking user_shifts data...\n";
    
    $userShifts = DB::table('user_shifts as us')
        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
        ->where('us.user_id', $user->id)
        ->select('us.tanggal', 'us.shift_id', 's.shift_name', 's.time_start', 's.time_end')
        ->orderBy('us.tanggal', 'desc')
        ->limit(10)
        ->get();
    
    echo "   Recent user_shifts data:\n";
    if ($userShifts->count() > 0) {
        foreach ($userShifts as $shift) {
            echo "   - {$shift->tanggal} | {$shift->shift_id} | {$shift->shift_name} | {$shift->time_start} - {$shift->time_end}\n";
        }
    } else {
        echo "   No user_shifts data found for this user.\n";
    }
    
    // Check user_pins data
    echo "\n3. Checking user_pins data...\n";
    
    $userPins = DB::table('user_pins')
        ->where('user_id', $user->id)
        ->get();
    
    echo "   User pins data:\n";
    if ($userPins->count() > 0) {
        foreach ($userPins as $pin) {
            echo "   - PIN: {$pin->pin} | Outlet: {$pin->outlet_id} | Active: {$pin->is_active}\n";
        }
    } else {
        echo "   No user_pins data found for this user.\n";
    }
    
    // Check outlets data
    echo "\n4. Checking outlets data...\n";
    
    $outlets = DB::table('tbl_data_outlet')
        ->whereIn('id_outlet', $userPins->pluck('outlet_id'))
        ->get();
    
    echo "   User outlets:\n";
    if ($outlets->count() > 0) {
        foreach ($outlets as $outlet) {
            echo "   - {$outlet->id_outlet} | {$outlet->nama_outlet} | SN: {$outlet->sn}\n";
        }
    } else {
        echo "   No outlets found for this user.\n";
    }
    
    // Check if there's any data in the current month
    echo "\n5. Checking current month data...\n";
    
    $currentMonth = date('Y-m');
    $currentMonthStart = $currentMonth . '-01';
    $currentMonthEnd = date('Y-m-t');
    
    echo "   Checking data for current month: {$currentMonthStart} to {$currentMonthEnd}\n";
    
    $currentMonthAttLog = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $user->id)
        ->whereBetween(DB::raw('DATE(a.scan_date)'), [$currentMonthStart, $currentMonthEnd])
        ->select('a.scan_date', 'a.inoutmode', 'o.nama_outlet')
        ->orderBy('a.scan_date')
        ->get();
    
    echo "   Current month att_log data: {$currentMonthAttLog->count()} records\n";
    if ($currentMonthAttLog->count() > 0) {
        foreach ($currentMonthAttLog as $log) {
            echo "   - {$log->scan_date} | {$log->inoutmode} | {$log->nama_outlet}\n";
        }
    }
    
    $currentMonthShifts = DB::table('user_shifts as us')
        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
        ->where('us.user_id', $user->id)
        ->whereBetween('us.tanggal', [$currentMonthStart, $currentMonthEnd])
        ->select('us.tanggal', 'us.shift_id', 's.shift_name', 's.time_start', 's.time_end')
        ->orderBy('us.tanggal')
        ->get();
    
    echo "   Current month user_shifts data: {$currentMonthShifts->count()} records\n";
    if ($currentMonthShifts->count() > 0) {
        foreach ($currentMonthShifts as $shift) {
            echo "   - {$shift->tanggal} | {$shift->shift_id} | {$shift->shift_name} | {$shift->time_start} - {$shift->time_end}\n";
        }
    }
    
    echo "\n=== ATTENDANCE DATA CHECK COMPLETED ===\n";
    
    if ($currentMonthAttLog->count() > 0 && $currentMonthShifts->count() > 0) {
        echo "✅ Found data for current month! Use current month for testing.\n";
    } else {
        echo "❌ No data found for current month. Need to check other periods or create test data.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
