<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$nik = '251411';
$u = DB::table('users')->where('nik', $nik)->first();
if (! $u) {
    echo "User not found\n";
    exit(1);
}

echo "=== Iqbal Hamdani debug ===\n";
echo "user_id={$u->id}\n";

$pg = DB::table('payroll_generated')->where('outlet_id', 24)->where('year', 2026)->where('month', 4)->first();
if ($pg) {
    echo "payroll status: gajian1={$pg->gajian1_status} gajian2={$pg->gajian2_status} overall={$pg->status}\n";
    $d = DB::table('payroll_generated_details')
        ->where('payroll_generated_id', $pg->id)
        ->where('user_id', $u->id)
        ->first();
    if ($d) {
        echo "DB saved: hari_kerja={$d->hari_kerja} service_charge={$d->service_charge}\n";
    } else {
        echo "No saved detail in payroll_generated_details\n";
    }
} else {
    echo "No payroll_generated record\n";
}

// Call trace service for this user only
$tracer = app(\App\Services\PayrollScTraceService::class);
$result = $tracer->run(24, 2026, 4, 22750551);
foreach ($result['rows'] as $row) {
    if (stripos($row['nama'], 'Iqbal Hamdani') !== false) {
        echo "API index(): hari_kerja={$row['hari_kerja']} hari_kerja_gajian2={$row['hari_kerja_gajian2']} sc={$row['erp_sc']}\n";
        echo "is_resign=" . ($row['is_resign'] ? 'yes' : 'no') . " is_mutasi=" . ($row['is_mutasi'] ? 'yes' : 'no') . "\n";
    }
}
