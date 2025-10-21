<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG ALYA PUTRI AMALIA ===\n";
echo "SN: 66208024520065, PIN: 38\n\n";

// 1. Cek data att_log untuk ALYA
echo "1. Data att_log untuk ALYA:\n";
$attLogs = DB::table('att_log')
    ->where('sn', '66208024520065')
    ->where('pin', '38')
    ->orderBy('scan_date', 'desc')
    ->limit(10)
    ->get(['scan_date', 'inoutmode']);

echo "Total records: " . $attLogs->count() . "\n";
foreach ($attLogs as $log) {
    echo "Date: " . $log->scan_date . ", Mode: " . $log->inoutmode . "\n";
}

echo "\n";

// 2. Cek user_pins untuk ALYA
echo "2. User pins untuk ALYA:\n";
$userPins = DB::table('user_pins')
    ->where('pin', '38')
    ->get(['user_id', 'pin', 'outlet_id']);

foreach ($userPins as $pin) {
    echo "User ID: " . $pin->user_id . ", PIN: " . $pin->pin . ", Outlet: " . $pin->outlet_id . "\n";
}

echo "\n";

// 3. Cek user data untuk ALYA
echo "3. User data untuk ALYA:\n";
$users = DB::table('users')
    ->where('id', 251739)
    ->get(['id', 'nama_lengkap', 'id_outlet', 'division_id']);

foreach ($users as $user) {
    echo "ID: " . $user->id . ", Nama: " . $user->nama_lengkap . ", Outlet: " . $user->id_outlet . ", Division: " . $user->division_id . "\n";
}

echo "\n";

// 4. Cek data attendance untuk Oktober 2025
echo "4. Data attendance Oktober 2025:\n";
$start = '2025-09-26';
$end = '2025-10-25';

$attendanceData = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', 251739)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->select('a.scan_date', 'a.inoutmode', 'a.sn', 'a.pin')
    ->orderBy('a.scan_date')
    ->get();

echo "Total attendance records: " . $attendanceData->count() . "\n";
foreach ($attendanceData as $att) {
    echo "Date: " . $att->scan_date . ", Mode: " . $att->inoutmode . ", SN: " . $att->sn . ", PIN: " . $att->pin . "\n";
}

echo "\n=== END DEBUG ===\n";
