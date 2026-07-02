<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tolerance = 0.01;

// Outlet + tanggal bermasalah (Juni 2026)
$scopes = [
    ['outlet' => 'Justus Steak House Paris Van Java', 'from' => '2026-06-30', 'to' => '2026-06-30'],
    ['outlet' => 'Justus Steak House Dago', 'from' => '2026-06-26', 'to' => '2026-06-26'],
    ['outlet' => 'Justus Steak House Bintaro', 'from' => '2026-06-29', 'to' => '2026-06-29'],
    ['outlet' => 'JUSTUS FCL', 'from' => '2026-06-26', 'to' => '2026-06-30'],
];

$outletCodes = DB::table('tbl_data_outlet')
    ->whereIn('nama_outlet', array_column($scopes, 'outlet'))
    ->pluck('qr_code', 'nama_outlet');

echo "=== Trace fokus outlet Juni 2026 ===\n";
echo "Outlet codes: " . json_encode($outletCodes, JSON_UNESCAPED_UNICODE) . "\n\n";

$allMismatches = [];
$grandAgg = 0.0;
$payAgg = 0.0;

foreach ($scopes as $scope) {
    $outletName = $scope['outlet'];
    $from = $scope['from'];
    $to = $scope['to'];
    $kode = $outletCodes[$outletName] ?? null;

    echo str_repeat('=', 80) . "\n";
    echo "{$outletName} | {$from}" . ($from !== $to ? " s/d {$to}" : '') . "\n";
    if (!$kode) {
        echo "  [!] Outlet tidak ditemukan di tbl_data_outlet\n\n";
        continue;
    }

    $orders = DB::table('orders as o')
        ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
        ->where('o.kode_outlet', $kode)
        ->whereDate('o.created_at', '>=', $from)
        ->whereDate('o.created_at', '<=', $to)
        ->where('o.status', 'paid')
        ->select([
            'o.id', 'o.created_at', 'o.nomor', 'o.paid_number', 'o.status',
            'o.kode_outlet', 'tdo.nama_outlet', 'o.grand_total', 'o.total', 'o.pb1', 'o.service',
        ])
        ->orderBy('o.created_at')
        ->get();

    $paymentsByOrder = DB::table('order_payment')
        ->whereIn('order_id', $orders->pluck('id'))
        ->select('id', 'order_id', 'payment_code', 'payment_type', 'amount', 'change', 'kasir', 'created_at')
        ->orderBy('id')
        ->get()
        ->groupBy('order_id');

    $scopeGrand = 0.0;
    $scopePay = 0.0;
    $mismatches = [];

    foreach ($orders as $o) {
        $pays = $paymentsByOrder->get((string) $o->id, collect());
        $sumAmount = (float) $pays->sum('amount');
        $sumNet = (float) $pays->sum(fn ($p) => (float) $p->amount - (float) ($p->change ?? 0));
        $grand = (float) ($o->grand_total ?? 0);
        $scopeGrand += $grand;
        $scopePay += $sumAmount;

        if (abs($grand - $sumAmount) <= $tolerance) {
            continue;
        }

        $payCount = $pays->count();
        $isSplit = $payCount >= 1 && $sumAmount < $grand - $tolerance;
        $isCashChange = abs($grand - $sumNet) <= $tolerance;
        $dupes = $pays->groupBy(fn ($p) => ($p->payment_code ?? '') . '|' . ($p->payment_type ?? '') . '|' . (string) $p->amount)
            ->filter(fn ($g) => $g->count() > 1);

        $category = 'other';
        if ($pays->isEmpty()) {
            $category = 'no_payment';
        } elseif ($dupes->isNotEmpty() && $sumAmount > $grand + $tolerance) {
            $category = 'duplicate_lines';
        } elseif ($isCashChange) {
            $category = 'cash_change_only';
        } elseif ($isSplit) {
            $category = 'missing_split_or_payment';
        } elseif ($sumAmount > $grand + $tolerance) {
            $category = 'over_payment';
        }

        $row = [
            'scope' => $outletName,
            'category' => $category,
            'o' => $o,
            'pays' => $pays,
            'sumAmount' => $sumAmount,
            'sumNet' => $sumNet,
            'grand' => $grand,
            'payCount' => $payCount,
            'missing' => $grand - $sumAmount,
        ];
        $mismatches[] = $row;
        $allMismatches[] = $row;
    }

    $grandAgg += $scopeGrand;
    $payAgg += $scopePay;

    echo "Orders paid: {$orders->count()}\n";
    echo 'SUM grand_total: ' . number_format($scopeGrand, 0, ',', '.') . "\n";
    echo 'SUM payment amount: ' . number_format($scopePay, 0, ',', '.') . "\n";
    echo 'Selisih agregat: ' . number_format($scopeGrand - $scopePay, 0, ',', '.') . "\n";
    echo 'Order selisih: ' . count($mismatches) . "\n\n";

    if ($mismatches === []) {
        echo "  (semua order cocok)\n\n";
        continue;
    }

    foreach ($mismatches as $row) {
        $o = $row['o'];
        $splitHint = $row['category'] === 'missing_split_or_payment'
            ? " [KEMUNGKINAN SPLIT: {$row['payCount']} baris payment, kurang " . number_format($row['missing'], 0, ',', '.') . ']'
            : '';
        echo sprintf(
            "[%s]%s %s | %s | GT %s | pay %s | net %s | selisih %s | %d payment(s)\n",
            $row['category'],
            $splitHint,
            substr((string) $o->created_at, 0, 16),
            $o->paid_number ?: $o->nomor,
            number_format($grand = (float) $o->grand_total, 0, ',', '.'),
            number_format($row['sumAmount'], 0, ',', '.'),
            number_format($row['sumNet'], 0, ',', '.'),
            number_format($row['missing'], 0, ',', '.'),
            $row['payCount'],
        );
        echo "  breakdown: total=" . number_format((float) ($o->total ?? 0), 0, ',', '.')
            . ' pb1=' . number_format((float) ($o->pb1 ?? 0), 0, ',', '.')
            . ' svc=' . number_format((float) ($o->service ?? 0), 0, ',', '.') . "\n";
        foreach ($row['pays'] as $p) {
            echo "  - [{$p->id}] {$p->payment_code}/{$p->payment_type} amt="
                . number_format((float) $p->amount, 0, ',', '.')
                . ' chg=' . number_format((float) ($p->change ?? 0), 0, ',', '.')
                . ' @' . substr((string) $p->created_at, 0, 19) . "\n";
        }
        echo "\n";
    }
}

echo str_repeat('=', 80) . "\n";
echo "TOTAL AGREGAT SEMUA SCOPE\n";
echo 'SUM grand_total: ' . number_format($grandAgg, 0, ',', '.') . "\n";
echo 'SUM payment amount: ' . number_format($payAgg, 0, ',', '.') . "\n";
echo 'Selisih agregat: ' . number_format($grandAgg - $payAgg, 0, ',', '.') . "\n";
echo 'Total order bermasalah: ' . count($allMismatches) . "\n";

$byCat = [];
foreach ($allMismatches as $m) {
    $byCat[$m['category']] = ($byCat[$m['category']] ?? 0) + 1;
}
foreach ($byCat as $cat => $n) {
    echo "  {$cat}: {$n}\n";
}

$csv = __DIR__ . '/orders_payment_focus_june.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['scope', 'category', 'order_id', 'created_at', 'paid_number', 'grand_total', 'sum_amount', 'sum_net', 'diff', 'pay_count', 'payments']);
foreach ($allMismatches as $row) {
    $o = $row['o'];
    $detail = $row['pays']->map(fn ($p) => "#{$p->id} {$p->payment_code}/{$p->payment_type}:{$p->amount}")->implode(' | ');
    fputcsv($fp, [
        $row['scope'],
        $row['category'],
        $o->id,
        $o->created_at,
        $o->paid_number ?: $o->nomor,
        $o->grand_total,
        $row['sumAmount'],
        $row['sumNet'],
        $row['missing'],
        $row['payCount'],
        $detail,
    ]);
}
fclose($fp);
echo "\nCSV: {$csv}\n";
