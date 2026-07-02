<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$from = '2026-07-01';
$to = '2026-07-31';
$tolerance = 0.01;
$paidOnly = in_array('--paid-only', $argv ?? [], true);

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

echo "=== Trace orders.grand_total vs order_payment.amount ===\n";
echo "Periode order created_at: {$from} s/d {$to}\n";
echo 'Filter: ' . ($paidOnly ? 'status=paid' : 'semua status') . "\n\n";

$query = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->whereDate('o.created_at', '>=', $from)
    ->whereDate('o.created_at', '<=', $to);

if ($paidOnly) {
    $query->where('o.status', 'paid');
}

$orders = $query
    ->select([
        'o.id', 'o.created_at', 'o.nomor', 'o.paid_number', 'o.status',
        'o.kode_outlet', 'tdo.nama_outlet', 'o.grand_total',
    ])
    ->orderBy('o.created_at')
    ->get();

$paymentsByOrder = DB::table('order_payment')
    ->whereIn('order_id', $orders->pluck('id'))
    ->select('order_id', 'payment_code', 'payment_type', 'amount', 'change', 'kasir', 'created_at')
    ->get()
    ->groupBy('order_id');

$categories = [
    'missing_payment' => [],
    'over_payment' => [],
    'duplicate_lines' => [],
    'cash_change_only' => [],
    'no_payment' => [],
];

foreach ($orders as $o) {
    $pays = $paymentsByOrder->get((string) $o->id, collect());
    $sumAmount = (float) $pays->sum('amount');
    $sumNet = (float) $pays->sum(fn ($p) => (float) $p->amount - (float) ($p->change ?? 0));
    $grand = (float) ($o->grand_total ?? 0);

    if ($pays->isEmpty()) {
        if (abs($grand) > $tolerance) {
            $categories['no_payment'][] = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand');
        }
        continue;
    }

    $dupes = $pays->groupBy(fn ($p) => ($p->payment_code ?? '') . '|' . ($p->payment_type ?? '') . '|' . (string) $p->amount)
        ->filter(fn ($g) => $g->count() > 1);

    if ($dupes->isNotEmpty() && abs($grand - $sumAmount) > $tolerance) {
        $categories['duplicate_lines'][] = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand', 'dupes');
        continue;
    }

    if (abs($grand - $sumAmount) <= $tolerance) {
        continue;
    }

    if (abs($grand - $sumNet) <= $tolerance) {
        $categories['cash_change_only'][] = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand');
        continue;
    }

    if ($sumAmount < $grand - $tolerance) {
        $categories['missing_payment'][] = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand');
    } else {
        $categories['over_payment'][] = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand');
    }
}

$totalMismatch = array_sum(array_map('count', $categories));

echo 'Total orders: ' . $orders->count() . PHP_EOL;
echo 'Total selisih: ' . $totalMismatch . PHP_EOL;
foreach ($categories as $cat => $rows) {
    echo "  {$cat}: " . count($rows) . PHP_EOL;
}

$totalGrand = (float) $orders->sum('grand_total');
$totalPayAmount = (float) DB::table('order_payment as op')
    ->join('orders as o', 'o.id', '=', 'op.order_id')
    ->whereDate('o.created_at', '>=', $from)
    ->whereDate('o.created_at', '<=', $to)
    ->when($paidOnly, fn ($q) => $q->where('o.status', 'paid'))
    ->sum('op.amount');

echo PHP_EOL . 'SUM grand_total: ' . number_format($totalGrand, 0, ',', '.') . PHP_EOL;
echo 'SUM payment amount: ' . number_format($totalPayAmount, 0, ',', '.') . PHP_EOL;
echo 'Selisih agregat: ' . number_format($totalGrand - $totalPayAmount, 0, ',', '.') . PHP_EOL . PHP_EOL;

$printSample = static function (string $title, array $rows, int $limit = 15) {
    if ($rows === []) {
        return;
    }
    echo "=== {$title} (sample) ===" . PHP_EOL;
    foreach (array_slice($rows, 0, $limit) as $row) {
        $o = $row['o'];
        echo sprintf(
            "%s | %s | %s | GT %s | pay %s | selisih %s\n",
            substr((string) $o->created_at, 0, 16),
            $o->nama_outlet ?? $o->kode_outlet,
            $o->paid_number ?: $o->nomor,
            number_format((float) $o->grand_total, 0, ',', '.'),
            number_format($row['sumAmount'], 0, ',', '.'),
            number_format((float) $o->grand_total - $row['sumAmount'], 0, ',', '.'),
        );
        foreach ($row['pays'] as $p) {
            echo "  - {$p->payment_code}/{$p->payment_type} amt=" . number_format((float) $p->amount, 0, ',', '.')
                . ' chg=' . number_format((float) ($p->change ?? 0), 0, ',', '.') . PHP_EOL;
        }
    }
    if (count($rows) > $limit) {
        echo '... +' . (count($rows) - $limit) . " lainnya\n";
    }
    echo PHP_EOL;
};

$printSample('Payment kurang (GT > sum amount)', $categories['missing_payment']);
$printSample('Payment lebih (sum amount > GT)', $categories['over_payment']);
$printSample('Kemungkinan duplikat baris payment', $categories['duplicate_lines']);
$printSample('Selisih hanya karena change cash', $categories['cash_change_only']);
$printSample('Paid tanpa payment', $categories['no_payment']);

$csv = __DIR__ . '/orders_payment_mismatch.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, ['category', 'order_id', 'created_at', 'outlet', 'paid_number', 'status', 'grand_total', 'sum_amount', 'sum_net', 'diff', 'payments']);
foreach ($categories as $cat => $rows) {
    foreach ($rows as $row) {
        $o = $row['o'];
        $detail = $row['pays']->map(fn ($p) => ($p->payment_code ?? '-') . ':' . $p->amount)->implode(' | ');
        fputcsv($fp, [
            $cat,
            $o->id,
            $o->created_at,
            $o->nama_outlet ?? $o->kode_outlet,
            $o->paid_number ?: $o->nomor,
            $o->status,
            $o->grand_total,
            $row['sumAmount'],
            $row['sumNet'],
            (float) $o->grand_total - $row['sumAmount'],
            $detail,
        ]);
    }
}
fclose($fp);
echo "CSV: {$csv}\n";
