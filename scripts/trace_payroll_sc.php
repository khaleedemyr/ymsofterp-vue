#!/usr/bin/env php
<?php

/**
 * Standalone runner — trace SC payroll vs Excel.
 *
 * Usage:
 *   php scripts/trace_payroll_sc.php <outlet_id> [year] [month] [pool]
 *   php scripts/trace_payroll_sc.php --find "Iqbal Hamdani" 2026 4 22750551
 */

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PayrollScTraceService;
use Illuminate\Support\Facades\DB;

$args = array_slice($argv, 1);
$findName = null;
$outletId = null;
$year = (int) date('Y');
$month = (int) date('m');
$pool = null;

for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--find' && isset($args[$i + 1])) {
        $findName = $args[++$i];
    } elseif ($args[$i] === '--pool' && isset($args[$i + 1])) {
        $pool = (float) $args[++$i];
    } elseif ($outletId === null && is_numeric($args[$i])) {
        $outletId = (int) $args[$i];
    } elseif (! isset($yearSet)) {
        $year = (int) $args[$i];
        $yearSet = true;
    } elseif (! isset($monthSet)) {
        $month = (int) $args[$i];
        $monthSet = true;
    } elseif ($pool === null && is_numeric($args[$i])) {
        $pool = (float) $args[$i];
    }
}

if ($findName && ! $outletId) {
    $matches = DB::table('users')
        ->where('nama_lengkap', 'like', '%'.$findName.'%')
        ->get(['id', 'nama_lengkap', 'id_outlet', 'nik', 'status']);

    if ($matches->isEmpty()) {
        fwrite(STDERR, "Karyawan tidak ditemukan: {$findName}\n");
        exit(1);
    }

    echo "Karyawan:\n";
    foreach ($matches as $m) {
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $m->id_outlet)->value('nama_outlet');
        echo "  [{$m->id_outlet}] {$outletName} — {$m->nama_lengkap} (status {$m->status})\n";
    }

    if ($matches->count() === 1) {
        $outletId = (int) $matches->first()->id_outlet;
    } else {
        fwrite(STDERR, "Beberapa match — tentukan outlet_id manual.\n");
        exit(1);
    }
}

if (! $outletId) {
    fwrite(STDERR, "Usage: php scripts/trace_payroll_sc.php <outlet_id> [year] [month] [--pool=22750551]\n");
    fwrite(STDERR, "   or: php scripts/trace_payroll_sc.php --find \"Iqbal Hamdani\" 2026 4 --pool 22750551\n");
    exit(1);
}

$tracer = app(PayrollScTraceService::class);
$result = $tracer->run($outletId, $year, $month, $pool);
echo $tracer->formatReport($result).PHP_EOL;
