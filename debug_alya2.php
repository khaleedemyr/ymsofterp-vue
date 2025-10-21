<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG ALYA PUTRI AMALIA - FIND CORRECT USER_ID ===\n";

// 1. Cari user_id yang benar untuk ALYA PUTRI AMALIA
echo "1. Cari user dengan nama ALYA PUTRI AMALIA:\n";
$users = DB::table('users')
    ->where('nama_lengkap', 'LIKE', '%ALYA%')
    ->get(['id', 'nama_lengkap', 'id_outlet', 'division_id']);

foreach ($users as $user) {
    echo "ID: " . $user->id . ", Nama: " . $user->nama_lengkap . ", Outlet: " . $user->id_outlet . ", Division: " . $user->division_id . "\n";
}

echo "\n";

// 2. Cek user_pins untuk user_id yang benar
echo "2. User pins untuk user_id yang benar:\n";
foreach ($users as $user) {
    $userPins = DB::table('user_pins')
        ->where('user_id', $user->id)
        ->get(['user_id', 'pin', 'outlet_id']);
    
    if ($userPins->count() > 0) {
        echo "User ID: " . $user->id . " - " . $user->nama_lengkap . "\n";
        foreach ($userPins as $pin) {
            echo "  PIN: " . $pin->pin . ", Outlet: " . $pin->outlet_id . "\n";
        }
    }
}

echo "\n";

// 3. Cek data attendance untuk user_id yang benar
echo "3. Data attendance Oktober 2025 untuk user_id yang benar:\n";
$start = '2025-09-26';
$end = '2025-10-25';

foreach ($users as $user) {
    $attendanceData = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $user->id)
        ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
        ->select('a.scan_date', 'a.inoutmode', 'a.sn', 'a.pin')
        ->orderBy('a.scan_date')
        ->get();

    if ($attendanceData->count() > 0) {
        echo "User ID: " . $user->id . " - " . $user->nama_lengkap . " (Total: " . $attendanceData->count() . " records)\n";
        foreach ($attendanceData->take(5) as $att) {
            echo "  Date: " . $att->scan_date . ", Mode: " . $att->inoutmode . ", SN: " . $att->sn . ", PIN: " . $att->pin . "\n";
        }
        if ($attendanceData->count() > 5) {
            echo "  ... and " . ($attendanceData->count() - 5) . " more records\n";
        }
    }
}

echo "\n=== END DEBUG ===\n";
