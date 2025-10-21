<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG ALYA PUTRI AMALIA - USER_ID 2558 ===\n";

// 1. Cek user data untuk ALYA
echo "1. User data untuk ALYA (user_id: 2558):\n";
$user = DB::table('users')
    ->where('id', 2558)
    ->first(['id', 'nama_lengkap', 'id_outlet', 'division_id']);

if ($user) {
    echo "ID: " . $user->id . ", Nama: " . $user->nama_lengkap . ", Outlet: " . $user->id_outlet . ", Division: " . $user->division_id . "\n";
} else {
    echo "User not found!\n";
}

echo "\n";

// 2. Cek user_pins untuk ALYA
echo "2. User pins untuk ALYA (user_id: 2558):\n";
$userPins = DB::table('user_pins')
    ->where('user_id', 2558)
    ->get(['user_id', 'pin', 'outlet_id']);

foreach ($userPins as $pin) {
    echo "User ID: " . $pin->user_id . ", PIN: " . $pin->pin . ", Outlet: " . $pin->outlet_id . "\n";
}

echo "\n";

// 3. Cek data attendance untuk Oktober 2025
echo "3. Data attendance Oktober 2025 untuk ALYA (user_id: 2558):\n";
$start = '2025-09-26';
$end = '2025-10-25';

$attendanceData = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', 2558)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->select('a.scan_date', 'a.inoutmode', 'a.sn', 'a.pin')
    ->orderBy('a.scan_date')
    ->get();

echo "Total attendance records: " . $attendanceData->count() . "\n";
foreach ($attendanceData as $att) {
    echo "Date: " . $att->scan_date . ", Mode: " . $att->inoutmode . ", SN: " . $att->sn . ", PIN: " . $att->pin . "\n";
}

echo "\n";

// 4. Cek shift data untuk ALYA
echo "4. Shift data untuk ALYA (user_id: 2558):\n";
$shifts = DB::table('user_shifts as us')
    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
    ->where('us.user_id', 2558)
    ->whereBetween('us.tanggal', [$start, $end])
    ->select('us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
    ->orderBy('us.tanggal')
    ->get();

echo "Total shift records: " . $shifts->count() . "\n";
foreach ($shifts as $shift) {
    echo "Date: " . $shift->tanggal . ", Shift: " . $shift->shift_name . ", Start: " . $shift->time_start . ", End: " . $shift->time_end . "\n";
}

echo "\n=== END DEBUG ===\n";
