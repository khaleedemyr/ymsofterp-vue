<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PayrollScTraceService;
use Illuminate\Support\Facades\DB;

$outletId = 24;
$year = 2026;
$month = 5;
$pool = 22750551;

$tracer = app(PayrollScTraceService::class);
$result = $tracer->run($outletId, $year, $month, $pool);

echo "=== ANALISIS SELISIH vs EXCEL (Mei 2026, outlet {$outletId}) ===\n\n";

$excelRef = PayrollScTraceService::EXCEL_REFERENCE;
$excelTotals = PayrollScTraceService::EXCEL_POOL_TOTALS;

// Simulasi pool pakai hari_kerja (gajian1) seperti kolom D Excel
$sumG1 = 0;
$sumPoinG1 = 0;
$rows = [];
foreach ($result['rows'] as $row) {
    $h = (int) $row['hari_kerja'];
    $p = (int) $row['poin'];
    $sumG1 += $h;
    $sumPoinG1 += $p * $h;

    $ex = $row['excel_ref_hari'] ?? null;
    if ($ex !== null && $h !== $ex) {
        $rows[] = $row;
    }
}

$half = $pool / 2;
$rateProG1 = $sumG1 > 0 ? $half / $sumG1 : 0;
$ratePointG1 = $sumPoinG1 > 0 ? $half / $sumPoinG1 : 0;

echo "--- DENOMINATOR ---\n";
printf("ERP pool pakai gajian2: Σhari=%d Σpoin×hari=%d\n",
    $result['erp_totals']['sum_hari_gajian2'],
    $result['erp_totals']['sum_poin_x_hari_gajian2']
);
printf("ERP hari_kerja (gajian1): Σhari=%d Σpoin×hari=%d\n", $sumG1, $sumPoinG1);
printf("Excel ref:              Σhari=%d Σpoin×hari=%d\n", $excelTotals['sum_hari'], $excelTotals['sum_poin_hari']);
echo "\n";

echo "--- KOLOM D EXCEL = hari_kerja (gajian1)? ---\n";
printf("%-28s %5s %5s %5s %5s %12s %12s\n", 'Nama', 'G1', 'G2', 'ExD', 'Δ G1-Ex', 'SC@G1sim', 'SC excel');
foreach ($result['rows'] as $row) {
    if ($row['excel_ref_total'] === null) {
        continue;
    }
    $h = (int) $row['hari_kerja'];
    $p = (int) $row['poin'];
    $scSimG1 = $h > 0 ? (int) round($rateProG1 * $h + $ratePointG1 * $p * $h) : 0;
    printf("%-28s %5d %5d %5d %5d %12s %12s\n",
        mb_substr($row['nama'], 0, 27),
        $h,
        (int) $row['hari_kerja_gajian2'],
        (int) $row['excel_ref_hari'],
        $h - (int) $row['excel_ref_hari'],
        number_format($scSimG1, 0, ',', '.'),
        number_format($row['excel_ref_total'], 0, ',', '.')
    );
}
echo "\n";

echo "--- KARYAWAN G1 ≠ Excel D ---\n";
if (empty($rows)) {
    echo "(semua yang ada di excel ref sudah match G1)\n";
} else {
    foreach ($rows as $r) {
        echo sprintf("%s: G1=%d Excel=%d mutasi=%s resign=%s\n",
            $r['nama'], $r['hari_kerja'], $r['excel_ref_hari'],
            $r['is_mutasi'] ? 'ya' : 'tidak',
            $r['is_resign'] ? 'ya' : 'tidak'
        );
    }
}
echo "\n";

// Iqbal attendance detail
$userId = DB::table('users')->where('nik', '251411')->value('id');
echo "--- IQBAL attendance outlet 24 ---\n";
$periods = [
    'gajian1 penuh' => ['2026-04-26', '2026-05-25'],
    'sejak mutasi 4 Mei' => ['2026-05-04', '2026-05-25'],
    'gajian2 Mei' => ['2026-05-01', '2026-05-31'],
    'mutasi segmen g2' => ['2026-05-04', '2026-05-31'],
];
foreach ($periods as $label => [$from, $to]) {
    $c = DB::table('att_log as a')
        ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
        ->join('user_pins as up', function ($q) {
            $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
        })
        ->where('up.user_id', $userId)
        ->where('o.id_outlet', $outletId)
        ->whereBetween(DB::raw('DATE(a.scan_date)'), [$from, $to])
        ->distinct()
        ->count(DB::raw('DATE(a.scan_date)'));
    echo "  {$label} ({$from}..{$to}): {$c} hari scan\n";
}

// Hanhan Sandy resign
echo "\n--- RESIGN (Hanhan/Sandy) ---\n";
foreach ($result['rows'] as $row) {
    if (! $row['is_resign']) {
        continue;
    }
    if (stripos($row['nama'], 'hanhan') === false && stripos($row['nama'], 'sandy') === false) {
        continue;
    }
    echo sprintf("%s: G1=%d G2=%d | SC erp=%s | excel=%s\n",
        $row['nama'],
        $row['hari_kerja'],
        $row['hari_kerja_gajian2'],
        number_format($row['erp_sc'], 0, ',', '.'),
        number_format($row['excel_ref_total'] ?? 0, 0, ',', '.')
    );
}
