<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$user = DB::table('users')->where('nama_lengkap', 'like', '%Muhammad Gantina%')->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "user_id={$user->id} | {$user->nama_lengkap}\n\n";

$rows = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $user->id)
    ->where('a.scan_date', '>=', '2026-06-29 00:00:00')
    ->where('a.scan_date', '<', '2026-07-01 00:00:00')
    ->select('o.id_outlet', 'o.nama_outlet', 'a.scan_date', 'a.inoutmode')
    ->orderBy('a.scan_date')
    ->get();

foreach ($rows as $r) {
    $modeLabel = match ((int) $r->inoutmode) {
        1 => 'IN',
        2 => 'OUT',
        4 => 'KEMBALI',
        default => 'MODE_'.$r->inoutmode,
    };
    echo "{$r->nama_outlet} | {$r->scan_date} | {$modeLabel}\n";
}
