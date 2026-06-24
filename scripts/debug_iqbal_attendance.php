<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$userId = 1850;
$year = 2026;
$month = 4;
$start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
$end = date('Y-m-d', strtotime("$year-$month-25"));
$gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

echo "Gajian1: {$start} .. {$end}\n";

$u = DB::table('users')->where('id', $userId)->first(['nama_lengkap', 'tanggal_masuk', 'id_outlet', 'status']);
echo "user: {$u->nama_lengkap} masuk={$u->tanggal_masuk} outlet={$u->id_outlet} status={$u->status}\n";

echo 'user_pins: '.json_encode($pins)."\n";

$outletId = 24;
$any = DB::table('att_log as a')
    ->join('user_pins as up', 'a.pin', '=', 'up.pin')
    ->where('up.user_id', $userId)
    ->where('a.scan_date', '>=', $start.' 00:00:00')
    ->where('a.scan_date', '<', date('Y-m-d', strtotime($gajian2End.' +1 day')).' 00:00:00')
    ->count();
echo "att_log rows in range (any pin): {$any}\n";

$g1DaysOutlet = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->where('u.id', $userId)
    ->where('u.id_outlet', $outletId)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end])
    ->distinct()
    ->count(DB::raw('DATE(a.scan_date)'));
echo "distinct gajian1 dates (payroll join outlet {$outletId}): {$g1DaysOutlet}\n";

// Employee summary style Apr 1-30 only
$g2Only = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->join('users as u', 'up.user_id', '=', 'u.id')
    ->where('u.id', $userId)
    ->where('u.id_outlet', $outletId)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), ["{$year}-{$month}-01", $gajian2End])
    ->distinct()
    ->count(DB::raw('DATE(a.scan_date)'));
echo "distinct Apr 1-30 dates (payroll join): {$g2Only}\n";

