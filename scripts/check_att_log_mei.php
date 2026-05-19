<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$uid = (int) ($argv[1] ?? 2729);

echo "DB: ".config('database.connections.mysql.database').' @ '.config('database.connections.mysql.host')."\n\n";

$user = DB::table('users')->where('id', $uid)->first(['id', 'nik', 'nama_lengkap', 'id_outlet']);
echo "User: ".json_encode($user, JSON_PRETTY_PRINT)."\n\n";

$pins = DB::table('user_pins')->where('user_id', $uid)->pluck('pin')->all();
echo "PINs: ".implode(', ', $pins)."\n\n";

if (empty($pins)) {
    echo "Tidak ada user_pins.\n";
    exit(0);
}

// Kalender Mei 2026
$ranges = [
    'Kalender Mei 2026' => ['2026-05-01 00:00:00', '2026-06-01 00:00:00'],
    'Payroll Mei 2026 (26/04-25/05)' => ['2026-04-26 00:00:00', '2026-05-26 00:00:00'],
];

foreach ($ranges as $label => [$start, $end]) {
    echo "=== {$label} ===\n";
    echo "Rentang: {$start} s/d < {$end}\n";

    $q = DB::table('att_log')
        ->whereIn('pin', $pins)
        ->where('scan_date', '>=', $start)
        ->where('scan_date', '<', $end);

    $count = (clone $q)->count();
    echo "COUNT att_log (pin IN user_pins): {$count}\n";

    if ($count > 0) {
        $sample = (clone $q)->orderBy('scan_date')->limit(3)->get(['pin', 'scan_date', 'inoutmode', 'sn']);
        $last = (clone $q)->orderByDesc('scan_date')->limit(3)->get(['pin', 'scan_date', 'inoutmode', 'sn']);
        echo "Contoh awal:\n".$sample->toJson(JSON_PRETTY_PRINT)."\n";
        echo "Contoh akhir:\n".$last->toJson(JSON_PRETTY_PRINT)."\n";

        $sns = (clone $q)->select('sn')->distinct()->pluck('sn');
        echo "SN unik di scan: ".$sns->implode(', ')."\n";
    }

    // Cek juga tanpa filter pin — apakah ada scan dengan pin lain?
    echo "\n";
}

// SN outlet 3
$outlet3 = DB::table('tbl_data_outlet')->where('id_outlet', 3)->first(['id_outlet', 'nama_outlet', 'sn']);
echo "=== Outlet 3 (Festival Citylink) ===\n";
echo json_encode($outlet3, JSON_PRETTY_PRINT)."\n";

if ($outlet3 && $outlet3->sn) {
    $meiKalender = DB::table('att_log')
        ->where('sn', $outlet3->sn)
        ->where('scan_date', '>=', '2026-05-01')
        ->where('scan_date', '<', '2026-06-01')
        ->count();
    echo "Scan di att_log dengan SN outlet 3, Mei 2026 (kalender): {$meiKalender}\n";

    $meiPayroll = DB::table('att_log')
        ->where('sn', $outlet3->sn)
        ->where('scan_date', '>=', '2026-04-26')
        ->where('scan_date', '<', '2026-05-26')
        ->count();
    echo "Scan SN outlet 3, payroll Mei: {$meiPayroll}\n";

    // Pin 36 di SN outlet 3?
    $pin36mei = DB::table('att_log')
        ->where('sn', $outlet3->sn)
        ->where('pin', '36')
        ->where('scan_date', '>=', '2026-05-01')
        ->where('scan_date', '<', '2026-06-01')
        ->count();
    echo "Pin 36 + SN outlet 3, Mei kalender: {$pin36mei}\n";
}

// Raw: semua att_log pin 36 mei
echo "\n=== att_log WHERE pin='36' Mei 2026 (kalender) ===\n";
$raw36 = DB::table('att_log')
    ->where('pin', '36')
    ->where('scan_date', '>=', '2026-05-01')
    ->where('scan_date', '<', '2026-06-01')
    ->orderBy('scan_date')
    ->limit(10)
    ->get(['pin', 'scan_date', 'inoutmode', 'sn']);
echo 'Count: '.DB::table('att_log')->where('pin', '36')->where('scan_date', '>=', '2026-05-01')->where('scan_date', '<', '2026-06-01')->count()."\n";
echo $raw36->toJson(JSON_PRETTY_PRINT)."\n";

// Join seperti Report Attendance (SN + pin outlet harus cocok)
echo "\n=== Join Report Attendance (sn=outlet.sn AND pin outlet match) ===\n";
foreach ($ranges as $label => [$start, $end]) {
    $joinCount = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function ($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->join('users as u', 'up.user_id', '=', 'u.id')
        ->where('u.id', $uid)
        ->where('a.scan_date', '>=', $start)
        ->where('a.scan_date', '<', $end)
        ->count();
    echo "{$label}: {$joinCount} baris\n";
}

$joinOutlet3 = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->where('u.id', $uid)
    ->where('u.id_outlet', 3)
    ->where('a.scan_date', '>=', '2026-05-01')
    ->where('a.scan_date', '<', '2026-06-01')
    ->count();
echo "Join + filter users.id_outlet=3, Mei kalender: {$joinOutlet3}\n";

echo "\nuser_pins detail:\n";
echo DB::table('user_pins')->where('user_id', $uid)->get()->toJson(JSON_PRETTY_PRINT)."\n";

$sns = ['616202024451891', '616202024451911', '616202024450722'];
echo "\nOutlet master untuk SN terkait:\n";
echo DB::table('tbl_data_outlet')->whereIn('sn', $sns)->get(['id_outlet', 'nama_outlet', 'sn'])->toJson(JSON_PRETTY_PRINT)."\n";
