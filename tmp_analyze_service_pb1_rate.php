<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fromDate = '2026-06-26';

$rows = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->whereDate('o.created_at', '>=', $fromDate)
    ->where('o.status', 'paid')
    ->select([
        'o.id',
        'o.created_at',
        'o.kode_outlet',
        'tdo.nama_outlet',
        'o.nomor',
        'o.paid_number',
        'o.total',
        'o.discount',
        'o.manual_discount_amount',
        'o.cashback',
        'o.dpp',
        'o.pb1',
        'o.service',
        'o.commfee',
        'o.rounding',
        'o.grand_total',
    ])
    ->orderBy('o.created_at')
    ->get();

function discUsed(object $row): float
{
    $discount = (float) ($row->discount ?? 0);
    $manual = (float) ($row->manual_discount_amount ?? 0);
    if ($discount > 0 && $manual > 0) {
        return max($discount, $manual);
    }

    return $discount + $manual;
}

function near(float $a, float $b, float $tol = 1.0): bool
{
    return abs($a - $b) <= $tol;
}

$stats = [
    'total_rows' => 0,
    'rows_with_base_pos' => 0,
    'service_match_round_total_minus_disc_cashback' => 0,
    'service_match_round_dpp' => 0,
    'pb1_match_round_base_x_10' => 0,
    'pb1_match_round_base_plus_service_x_10' => 0,
    'pb1_match_round_dpp_x_10' => 0,
    'grand_match_formula_a' => 0,
    'grand_match_formula_b' => 0,
];

$formulaA_diff_rows = []; // base = total-disc-cashback; pb1 = 10% * base
$formulaB_diff_rows = []; // base = total-disc-cashback; pb1 = 10% * (base+service)

foreach ($rows as $row) {
    $stats['total_rows']++;

    $total = (float) ($row->total ?? 0);
    $disc = discUsed($row);
    $cashback = (float) ($row->cashback ?? 0);
    $dpp = (float) ($row->dpp ?? 0);
    $service = (float) ($row->service ?? 0);
    $pb1 = (float) ($row->pb1 ?? 0);
    $commfee = (float) ($row->commfee ?? 0);
    $rounding = (float) ($row->rounding ?? 0);
    $grand = (float) ($row->grand_total ?? 0);

    $base = $total - $disc - $cashback;

    if ($base > 0) {
        $stats['rows_with_base_pos']++;
    }

    $expServiceByBase = round($base * 0.05);
    $expServiceByDpp = round($dpp * 0.05);

    $expPb1A = round($base * 0.10);
    $expPb1B = round(($base + $service) * 0.10);
    $expPb1ByDpp = round($dpp * 0.10);

    if (near($service, $expServiceByBase)) {
        $stats['service_match_round_total_minus_disc_cashback']++;
    }
    if (near($service, $expServiceByDpp)) {
        $stats['service_match_round_dpp']++;
    }
    if (near($pb1, $expPb1A)) {
        $stats['pb1_match_round_base_x_10']++;
    }
    if (near($pb1, $expPb1B)) {
        $stats['pb1_match_round_base_plus_service_x_10']++;
    }
    if (near($pb1, $expPb1ByDpp)) {
        $stats['pb1_match_round_dpp_x_10']++;
    }

    $grandExpA = $base + $service + $pb1 + $commfee + $rounding;
    $grandExpB = $base + $service + $pb1 + $commfee + $rounding;

    if (near($grand, $grandExpA)) {
        $stats['grand_match_formula_a']++;
    } else {
        $serviceDelta = $service - $expServiceByBase;
        $pb1DeltaA = $pb1 - $expPb1A;
        $formulaA_diff_rows[] = [
            'tanggal' => (string) $row->created_at,
            'outlet' => (string) ($row->nama_outlet ?? '-'),
            'kode_outlet' => (string) ($row->kode_outlet ?? '-'),
            'nomor_order' => (string) ($row->nomor ?? '-'),
            'paid_number' => (string) ($row->paid_number ?? '-'),
            'total' => $total,
            'disc_used' => $disc,
            'cashback' => $cashback,
            'base' => $base,
            'service_actual' => $service,
            'service_expected_5pct_base' => $expServiceByBase,
            'service_delta' => $serviceDelta,
            'pb1_actual' => $pb1,
            'pb1_expected_10pct_base' => $expPb1A,
            'pb1_delta' => $pb1DeltaA,
            'commfee' => $commfee,
            'rounding' => $rounding,
            'grand_total' => $grand,
            'grand_expected' => $grandExpA,
            'grand_diff' => $grand - $grandExpA,
        ];
    }

    if (near($grand, $grandExpB)) {
        $stats['grand_match_formula_b']++;
    } else {
        $pb1DeltaB = $pb1 - $expPb1B;
        $formulaB_diff_rows[] = [
            'tanggal' => (string) $row->created_at,
            'outlet' => (string) ($row->nama_outlet ?? '-'),
            'kode_outlet' => (string) ($row->kode_outlet ?? '-'),
            'nomor_order' => (string) ($row->nomor ?? '-'),
            'paid_number' => (string) ($row->paid_number ?? '-'),
            'total' => $total,
            'disc_used' => $disc,
            'cashback' => $cashback,
            'base' => $base,
            'service_actual' => $service,
            'service_expected_5pct_base' => $expServiceByBase,
            'service_delta' => $service - $expServiceByBase,
            'pb1_actual' => $pb1,
            'pb1_expected_10pct_base_plus_service' => $expPb1B,
            'pb1_delta' => $pb1DeltaB,
            'commfee' => $commfee,
            'rounding' => $rounding,
            'grand_total' => $grand,
            'grand_expected' => $grandExpB,
            'grand_diff' => $grand - $grandExpB,
        ];
    }
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'reports';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fileA = $dir . DIRECTORY_SEPARATOR . 'orders_service_pb1_analysis_formulaA_from_2026-06-26.csv';
$fileB = $dir . DIRECTORY_SEPARATOR . 'orders_service_pb1_analysis_formulaB_from_2026-06-26.csv';

$fpA = fopen($fileA, 'w');
if ($fpA !== false) {
    if (!empty($formulaA_diff_rows)) {
        fputcsv($fpA, array_keys($formulaA_diff_rows[0]));
        foreach ($formulaA_diff_rows as $r) {
            fputcsv($fpA, $r);
        }
    }
    fclose($fpA);
}

$fpB = fopen($fileB, 'w');
if ($fpB !== false) {
    if (!empty($formulaB_diff_rows)) {
        fputcsv($fpB, array_keys($formulaB_diff_rows[0]));
        foreach ($formulaB_diff_rows as $r) {
            fputcsv($fpB, $r);
        }
    }
    fclose($fpB);
}

echo "=== STATISTIK GLOBAL ===\n";
foreach ($stats as $k => $v) {
    echo $k . ': ' . $v . "\n";
}

echo "\nCSV Formula A diff: {$fileA}\n";
echo "CSV Formula B diff: {$fileB}\n";

echo "\nSample Formula A (20 rows):\n";
foreach (array_slice($formulaA_diff_rows, 0, 20) as $i => $r) {
    echo sprintf(
        "%d) %s | %s | %s | svcΔ=%.2f | pb1Δ=%.2f | grandΔ=%.2f\n",
        $i + 1,
        $r['tanggal'],
        $r['outlet'],
        $r['nomor_order'],
        (float) $r['service_delta'],
        (float) $r['pb1_delta'],
        (float) $r['grand_diff']
    );
}
