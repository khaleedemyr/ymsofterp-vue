<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('tbl_data_outlet')
    ->where(function ($q) {
        $q->where('nama_outlet', 'like', '%Tempayan%')
            ->orWhere('nama_outlet', 'like', '%Dago%');
    })
    ->orderBy('nama_outlet')
    ->get(['id_outlet', 'nama_outlet']);

foreach ($rows as $o) {
    echo "{$o->id_outlet} | {$o->nama_outlet}\n";
}
