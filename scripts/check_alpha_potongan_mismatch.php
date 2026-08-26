<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('payroll_details as pd')
    ->join('payrolls as p', 'p.id', '=', 'pd.payroll_id')
    ->where('pd.potongan_alpha', '>', 0)
    ->where(function ($q) {
        $q->whereNull('pd.total_alpha')->orWhere('pd.total_alpha', 0);
    })
    ->orderByDesc('p.id')
    ->limit(15)
    ->get([
        'pd.nama_lengkap',
        'pd.total_alpha',
        'pd.potongan_alpha',
        'pd.gaji_pokok',
        'pd.tunjangan',
        'p.bulan',
        'p.tahun',
        'p.outlet_id',
    ]);

echo "DB mismatch total_alpha=0 but potongan>0: ".$rows->count()."\n";
foreach ($rows as $r) {
    $base = (float) $r->gaji_pokok + (float) $r->tunjangan;
    $implied = $base > 0 ? round(((float) $r->potongan_alpha) / ($base / 31), 2) : null;
    echo $r->nama_lengkap
        .' | alpha='.$r->total_alpha
        .' | pot='.round((float) $r->potongan_alpha)
        .' | base='.round($base)
        .' | impliedDays='.$implied
        .' | '.$r->bulan.'/'.$r->tahun
        ."\n";
}

$rows2 = DB::table('payroll_details as pd')
    ->join('payrolls as p', 'p.id', '=', 'pd.payroll_id')
    ->where('pd.potongan_alpha', '>', 0)
    ->where('pd.total_alpha', '>', 0)
    ->where('p.bulan', 8)
    ->where('p.tahun', 2026)
    ->orderByDesc('pd.potongan_alpha')
    ->limit(10)
    ->get(['pd.nama_lengkap', 'pd.total_alpha', 'pd.potongan_alpha', 'pd.gaji_pokok', 'pd.tunjangan', 'p.outlet_id']);

echo "\nSample Aug 2026 with alpha>0:\n";
foreach ($rows2 as $r) {
    $base = (float) $r->gaji_pokok + (float) $r->tunjangan;
    $implied = $base > 0 ? round(((float) $r->potongan_alpha) / ($base / 31), 2) : null;
    echo $r->nama_lengkap.' | alpha='.$r->total_alpha.' | pot='.round((float)$r->potongan_alpha).' | implied='.$implied."\n";
}
