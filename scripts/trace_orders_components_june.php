<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tolerance = 0.01;

$scopes = [
    ['outlet' => 'Justus Steak House Festival Citylink', 'from' => '2026-06-08', 'to' => '2026-06-08'],
    ['outlet' => 'JUSTUS FCL', 'from' => '2026-06-13', 'to' => '2026-06-13'],
    ['outlet' => 'JUSTUS FCL', 'from' => '2026-06-27', 'to' => '2026-06-27'],
];

function effectiveDiscount(object $o): float
{
    $discount = (float) ($o->discount ?? 0);
    $manual = (float) ($o->manual_discount_amount ?? 0);
    if ($discount > 0 && $manual > 0) {
        return max($discount, $manual);
    }

    return $discount + $manual;
}

function calcGrandServicePb1(object $o): array
{
    $total = (float) ($o->total ?? 0);
    $cashback = (float) ($o->cashback ?? 0);
    $disc = effectiveDiscount($o);
    $dpp = max(0, $total - $disc - $cashback);
    $commfee = (float) ($o->commfee ?? 0);
    $rounding = (float) ($o->rounding ?? 0);

    // POS default Service+PB1: floor service on DPP, floor pb1 on (DPP+service)
    $pb1Pct = 0.10;
    $svcPct = 0.05;
    $service = (float) floor($dpp * $svcPct);
    $pb1 = (float) floor(($dpp + $service) * $pb1Pct);
    $grand = $dpp + $service + $pb1 + $commfee + $rounding;

    return compact('dpp', 'service', 'pb1', 'grand');
}

function calcGrandPb1Service(object $o): array
{
    $total = (float) ($o->total ?? 0);
    $cashback = (float) ($o->cashback ?? 0);
    $disc = effectiveDiscount($o);
    $dpp = max(0, $total - $disc - $cashback);
    $commfee = (float) ($o->commfee ?? 0);
    $rounding = (float) ($o->rounding ?? 0);

    $pb1Pct = 0.10;
    $svcPct = 0.05;
    $pb1 = (float) round($dpp * $pb1Pct);
    $service = (float) round($dpp * $svcPct);
    $grand = $dpp + $service + $pb1 + $commfee + $rounding;

    return compact('dpp', 'service', 'pb1', 'grand');
}

function sumComponents(object $o): float
{
    $total = (float) ($o->total ?? 0);
    $disc = effectiveDiscount($o);
    $cashback = (float) ($o->cashback ?? 0);
    $pb1 = (float) ($o->pb1 ?? 0);
    $service = (float) ($o->service ?? 0);
    $commfee = (float) ($o->commfee ?? 0);
    $rounding = (float) ($o->rounding ?? 0);

    return $total - $disc - $cashback + $pb1 + $service + $commfee + $rounding;
}

$outletCodes = DB::table('tbl_data_outlet')
    ->whereIn('nama_outlet', array_unique(array_column($scopes, 'outlet')))
    ->pluck('qr_code', 'nama_outlet');

echo "=== Trace orders internal consistency (Juni 2026) ===\n\n";

$allBad = [];

foreach ($scopes as $scope) {
    $kode = $outletCodes[$scope['outlet']] ?? null;
    echo str_repeat('=', 80) . "\n";
    echo "{$scope['outlet']} | {$scope['from']}" . ($scope['from'] !== $scope['to'] ? " s/d {$scope['to']}" : '') . "\n";

    if (!$kode) {
        echo "Outlet tidak ditemukan\n\n";
        continue;
    }

    $orders = DB::table('orders')
        ->where('kode_outlet', $kode)
        ->whereDate('created_at', '>=', $scope['from'])
        ->whereDate('created_at', '<=', $scope['to'])
        ->where('status', 'paid')
        ->orderBy('created_at')
        ->get();

    $bad = [];
    foreach ($orders as $o) {
        $stored = (float) ($o->grand_total ?? 0);
        $fromParts = sumComponents($o);
        $diffParts = round($stored - $fromParts, 2);

        if (abs($diffParts) <= $tolerance) {
            continue;
        }

        $sp = calcGrandServicePb1($o);
        $ps = calcGrandPb1Service($o);
        $pb1Diff = round((float) $o->pb1 - $sp['pb1'], 2);
        $svcDiff = round((float) $o->service - $sp['service'], 2);

        $bad[] = [
            'scope' => $scope['outlet'] . ' ' . $scope['from'],
            'o' => $o,
            'stored' => $stored,
            'fromParts' => $fromParts,
            'diffParts' => $diffParts,
            'sp' => $sp,
            'ps' => $ps,
            'pb1Diff' => $pb1Diff,
            'svcDiff' => $svcDiff,
        ];
        $allBad[] = end($bad);
    }

    echo "Orders paid: {$orders->count()}, selisih komponen: " . count($bad) . "\n\n";

    foreach ($bad as $row) {
        $o = $row['o'];
        echo sprintf(
            "%s | %s | stored GT %s | sum parts %s | diff %s\n",
            substr((string) $o->created_at, 0, 16),
            $o->paid_number ?: $o->nomor,
            number_format($row['stored'], 0, ',', '.'),
            number_format($row['fromParts'], 0, ',', '.'),
            number_format($row['diffParts'], 0, ',', '.'),
        );
        echo sprintf(
            "  total=%s disc=%s manual=%s cashback=%s pb1=%s svc=%s comm=%s round=%s\n",
            number_format((float) $o->total, 0, ',', '.'),
            number_format((float) $o->discount, 0, ',', '.'),
            number_format((float) $o->manual_discount_amount, 0, ',', '.'),
            number_format((float) $o->cashback, 0, ',', '.'),
            number_format((float) $o->pb1, 0, ',', '.'),
            number_format((float) $o->service, 0, ',', '.'),
            number_format((float) $o->commfee, 0, ',', '.'),
            number_format((float) $o->rounding, 0, ',', '.'),
        );
        echo sprintf(
            "  expected Svc+PB1: pb1=%s svc=%s GT=%s | pb1Δ=%s svcΔ=%s\n",
            number_format($row['sp']['pb1'], 0, ',', '.'),
            number_format($row['sp']['service'], 0, ',', '.'),
            number_format($row['sp']['grand'], 0, ',', '.'),
            number_format($row['pb1Diff'], 0, ',', '.'),
            number_format($row['svcDiff'], 0, ',', '.'),
        );
        $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');
        echo '  payment sum=' . number_format($pay, 0, ',', '.') . ' pay vs stored GT diff=' . number_format($row['stored'] - $pay, 0, ',', '.') . "\n\n";
    }
}

echo str_repeat('=', 80) . "\n";
echo 'Total order bermasalah: ' . count($allBad) . "\n";

$csv = __DIR__ . '/orders_component_mismatch_june.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['scope', 'paid_number', 'order_id', 'grand_total', 'sum_parts', 'diff', 'total', 'discount', 'pb1', 'service', 'commfee', 'exp_pb1', 'exp_service', 'exp_grand', 'pay_sum']);
foreach ($allBad as $row) {
    $o = $row['o'];
    $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');
    fputcsv($fp, [
        $row['scope'], $o->paid_number ?: $o->nomor, $o->id,
        $row['stored'], $row['fromParts'], $row['diffParts'],
        $o->total, $o->discount, $o->pb1, $o->service, $o->commfee,
        $row['sp']['pb1'], $row['sp']['service'], $row['sp']['grand'], $pay,
    ]);
}
fclose($fp);
echo "CSV: {$csv}\n";
