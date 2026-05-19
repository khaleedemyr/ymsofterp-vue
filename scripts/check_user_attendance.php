<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$uid = (int) ($argv[1] ?? 0);
$bulan = (int) ($argv[2] ?? 0);
$tahun = (int) ($argv[3] ?? (int) date('Y'));
if ($uid <= 0) {
    fwrite(STDERR, "Usage: php scripts/check_user_attendance.php {user_id} [bulan] [tahun]\n");
    fwrite(STDERR, "  Periode payroll: 26 bulan sebelumnya s/d 25 bulan dipilih (default: bulan/tahun sekarang)\n");
    exit(1);
}
if ($bulan <= 0) {
    $bulan = (int) date('n');
}

$user = DB::table('users')->where('id', $uid)->first(['id', 'nik', 'nama_lengkap', 'id_outlet', 'division_id', 'id_jabatan', 'status']);
if (! $user) {
    echo "User id={$uid} tidak ditemukan di tabel users.\n";
    exit(0);
}

$outlet = $user->id_outlet
    ? DB::table('tbl_data_outlet')->where('id_outlet', $user->id_outlet)->value('nama_outlet')
    : null;

echo "=== User ===\n";
echo json_encode(['user' => $user, 'nama_outlet' => $outlet], JSON_PRETTY_PRINT)."\n\n";

$pins = DB::table('user_pins')->where('user_id', $uid)->get(['pin', 'outlet_id']);
echo "=== user_pins ({$pins->count()}) ===\n";
echo $pins->toJson(JSON_PRETTY_PRINT)."\n\n";

if ($pins->isEmpty()) {
    echo "Tidak ada PIN fingerprint → scan att_log tidak bisa terhubung ke user ini.\n";
    exit(0);
}

$base = DB::table('att_log as a')
    ->join('user_pins as up', 'a.pin', '=', 'up.pin')
    ->where('up.user_id', $uid);

$totalAll = (clone $base)->count();
$first = (clone $base)->orderBy('a.scan_date')->value('a.scan_date');
$last = (clone $base)->orderByDesc('a.scan_date')->value('a.scan_date');

echo "=== Ringkasan absensi (att_log + user_pins) ===\n";
echo "Total scan (semua waktu): {$totalAll}\n";
echo "Scan pertama: ".($first ?: '-')."\n";
echo "Scan terakhir: ".($last ?: '-')."\n\n";

// Periode payroll (26 bulan lalu - 25 bulan dipilih)
$start = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month")).' 00:00:00';
$end = date('Y-m-d', strtotime("$tahun-$bulan-25 +1 day")).' 00:00:00';
$periodeLabel = date('d/m/Y', strtotime("$tahun-$bulan-26 -1 month")).' - '.date('d/m/Y', strtotime("$tahun-$bulan-25"));
$periodCount = (clone $base)
    ->where('a.scan_date', '>=', $start)
    ->where('a.scan_date', '<', $end)
    ->count();

echo "=== Periode payroll {$periodeLabel} (bulan={$bulan}, tahun={$tahun}) ===\n";
echo "Jumlah scan: {$periodCount}\n";

if ($periodCount > 0) {
    $byDay = (clone $base)
        ->where('a.scan_date', '>=', $start)
        ->where('a.scan_date', '<', $end)
        ->selectRaw('DATE(a.scan_date) as tgl, COUNT(*) as cnt, SUM(CASE WHEN a.inoutmode = 1 THEN 1 ELSE 0 END) as masuk, SUM(CASE WHEN a.inoutmode = 2 THEN 1 ELSE 0 END) as keluar')
        ->groupByRaw('DATE(a.scan_date)')
        ->orderBy('tgl')
        ->get();
    echo json_encode($byDay, JSON_PRETTY_PRINT)."\n";
}

// Join dengan outlet (seperti report attendance)
$withOutlet = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $uid)
    ->where('a.scan_date', '>=', $start)
    ->where('a.scan_date', '<', $end)
    ->count();

echo "\n=== Scan periode (join outlet+pin, seperti report attendance) ===\n";
echo "Jumlah scan: {$withOutlet}\n";

$byOutletPin = (clone $base)
    ->leftJoin('tbl_data_outlet as o', 'up.outlet_id', '=', 'o.id_outlet')
    ->where('a.scan_date', '>=', $start)
    ->where('a.scan_date', '<', $end)
    ->selectRaw('up.outlet_id, o.nama_outlet, COUNT(*) as cnt')
    ->groupBy('up.outlet_id', 'o.nama_outlet')
    ->orderByDesc('cnt')
    ->get();

echo "\n=== Scan periode per outlet_id (dari user_pins, tanpa match SN) ===\n";
echo $byOutletPin->toJson(JSON_PRETTY_PRINT)."\n";

if ($user->id_outlet) {
    $outletOnly = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function ($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $uid)
        ->where('o.id_outlet', $user->id_outlet)
        ->where('a.scan_date', '>=', $start)
        ->where('a.scan_date', '<', $end)
        ->count();
    echo "\nScan periode di outlet user saat ini (id_outlet={$user->id_outlet}): {$outletOnly}\n";
}
