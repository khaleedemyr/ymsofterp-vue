<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$userId = 1850;
$outletId = 24;
$effective = '2026-05-04';
$g1Start = '2026-04-26';
$g1End = '2026-05-25';

$days = DB::table('att_log as a')
    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
    ->join('user_pins as up', function ($q) {
        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
    })
    ->where('up.user_id', $userId)
    ->where('o.id_outlet', $outletId)
    ->whereBetween(DB::raw('DATE(a.scan_date)'), [$effective, $g1End])
    ->distinct()
    ->pluck(DB::raw('DATE(a.scan_date)'));

echo "Scan dates outlet 24 ({$effective} .. {$g1End}): ".$days->count()."\n";
echo implode(', ', $days->toArray())."\n";

// Miko outlet
$miko = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%Miko%')->get(['id_outlet', 'nama_outlet']);
echo "\nMiko outlets: ".json_encode($miko)."\n";

if ($miko->isNotEmpty()) {
    $mikoId = $miko->first()->id_outlet;
    $daysMiko = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function ($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $userId)
        ->where('o.id_outlet', $mikoId)
        ->whereBetween(DB::raw('DATE(a.scan_date)'), [$g1Start, '2026-05-03'])
        ->distinct()
        ->count(DB::raw('DATE(a.scan_date)'));
    echo "Scan days Miko ({$g1Start} .. 2026-05-03): {$daysMiko}\n";
}
