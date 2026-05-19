<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sn = $argv[1] ?? '616202024450722';
$pin = $argv[2] ?? '36';

echo "=== att_log: sn={$sn}, pin={$pin} ===\n\n";

$outlet = DB::table('tbl_data_outlet')->where('sn', $sn)->first(['id_outlet', 'nama_outlet', 'sn']);
echo "Outlet di master:\n".json_encode($outlet, JSON_PRETTY_PRINT)."\n\n";

$up = DB::table('user_pins as up')
    ->join('users as u', 'u.id', '=', 'up.user_id')
    ->where('up.pin', $pin)
    ->get(['up.id', 'up.pin', 'up.outlet_id', 'up.user_id', 'u.nik', 'u.nama_lengkap', 'u.id_outlet']);
echo "user_pins untuk pin {$pin}:\n".$up->toJson(JSON_PRETTY_PRINT)."\n\n";

$periods = [
    'Mei 2026 kalender' => ['2026-05-01', '2026-06-01'],
    'Payroll Mei (26/04-25/05)' => ['2026-04-26', '2026-05-26'],
    'Semua waktu' => [null, null],
];

foreach ($periods as $label => [$start, $end]) {
    $q = DB::table('att_log')->where('sn', $sn)->where('pin', $pin);
    if ($start) {
        $q->where('scan_date', '>=', $start)->where('scan_date', '<', $end);
    }
    $count = (clone $q)->count();
    echo "{$label}: {$count} baris\n";
    if ($count > 0 && $count <= 5) {
        echo (clone $q)->orderBy('scan_date')->get(['pin', 'scan_date', 'inoutmode', 'sn'])->toJson(JSON_PRETTY_PRINT)."\n";
    } elseif ($count > 5) {
        $first = (clone $q)->orderBy('scan_date')->limit(2)->get(['pin', 'scan_date', 'inoutmode', 'sn']);
        $last = (clone $q)->orderByDesc('scan_date')->limit(2)->get(['pin', 'scan_date', 'inoutmode', 'sn']);
        echo "  awal: ".$first->toJson()."\n  akhir: ".$last->toJson()."\n";
    }
}

echo "\n=== Pin {$pin} — SN apa saja yang dipakai (Mei 2026)? ===\n";
$bySn = DB::table('att_log')
    ->where('pin', $pin)
    ->where('scan_date', '>=', '2026-05-01')
    ->where('scan_date', '<', '2026-06-01')
    ->select('sn', DB::raw('COUNT(*) as cnt'))
    ->groupBy('sn')
    ->orderByDesc('cnt')
    ->get();
echo $bySn->toJson(JSON_PRETTY_PRINT)."\n";

echo "\n=== SN {$sn} — pin apa saja (Mei 2026)? ===\n";
$byPin = DB::table('att_log')
    ->where('sn', $sn)
    ->where('scan_date', '>=', '2026-05-01')
    ->where('scan_date', '<', '2026-06-01')
    ->select('pin', DB::raw('COUNT(*) as cnt'))
    ->groupBy('pin')
    ->orderByDesc('cnt')
    ->limit(15)
    ->get();
echo $byPin->toJson(JSON_PRETTY_PRINT)."\n";
if (DB::table('att_log')->where('sn', $sn)->where('pin', $pin)->exists()) {
    echo "\n(Pernah ada kombinasi ini di periode lain?)\n";
    $ever = DB::table('att_log')->where('sn', $sn)->where('pin', $pin)->count();
    echo "Total semua waktu: {$ever}\n";
    if ($ever > 0) {
        $range = DB::table('att_log')->where('sn', $sn)->where('pin', $pin)
            ->selectRaw('MIN(scan_date) as pertama, MAX(scan_date) as terakhir')->first();
        echo json_encode($range, JSON_PRETTY_PRINT)."\n";
    }
}
