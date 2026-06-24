<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = app(\App\Services\PayrollScTraceService::class)->run(2, 2026, 5, 0);
foreach ($r['rows'] as $row) {
    if (stripos($row['nama'], 'Iqbal Hamdani') !== false) {
        echo "Outlet 2 (Miko): G1={$row['hari_kerja']} G2={$row['hari_kerja_gajian2']} mutasi=".($row['is_mutasi']?'ya':'tidak')." SC={$row['erp_sc']}\n";
    }
}
$r24 = app(\App\Services\PayrollScTraceService::class)->run(24, 2026, 5, 22750551);
foreach ($r24['rows'] as $row) {
    if (stripos($row['nama'], 'Iqbal Hamdani') !== false) {
        echo "Outlet 24: G1={$row['hari_kerja']} G2={$row['hari_kerja_gajian2']} mutasi=".($row['is_mutasi']?'ya':'tidak')." SC={$row['erp_sc']}\n";
    }
}
