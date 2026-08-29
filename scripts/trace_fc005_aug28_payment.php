<?php

declare(strict_types=1);

/**
 * Trace selisih orders.grand_total vs SUM(order_payment.amount)
 * Outlet FC005, tanggal 28 Agustus 2026.
 *
 * Usage:
 *   php scripts/trace_fc005_aug28_payment.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tolerance = 0.01;
$kodeOutlet = 'fc005';
$date = '2026-08-28';

function fmt($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function effectiveDiscount(object $o): float
{
    $discount = (float) ($o->discount ?? 0);
    $manual = (float) ($o->manual_discount_amount ?? 0);
    if ($discount > 0 && $manual > 0) {
        return max($discount, $manual);
    }

    return $discount + $manual;
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

$outlet = DB::table('tbl_data_outlet')
    ->where('qr_code', $kodeOutlet)
    ->orWhere('qr_code', strtoupper($kodeOutlet))
    ->first();

echo "=== Trace FC005 {$date}: orders.grand_total vs order_payment.amount ===\n";
if ($outlet) {
    echo "Outlet: {$outlet->nama_outlet} | qr_code={$outlet->qr_code} | id={$outlet->id_outlet}\n\n";
    $kodeOutlet = $outlet->qr_code;
} else {
    echo "Outlet qr_code={$kodeOutlet} tidak ditemukan, tetap query kode_outlet={$kodeOutlet}\n\n";
}

$orders = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->where('o.kode_outlet', $kodeOutlet)
    ->whereDate('o.created_at', $date)
    ->select([
        'o.id', 'o.created_at', 'o.updated_at', 'o.nomor', 'o.paid_number', 'o.status',
        'o.mode', 'o.kode_outlet', 'tdo.nama_outlet',
        'o.total', 'o.discount', 'o.manual_discount_amount', 'o.cashback',
        'o.dpp', 'o.pb1', 'o.service', 'o.commfee', 'o.rounding', 'o.grand_total',
    ])
    ->orderBy('o.created_at')
    ->get();

echo 'Total orders (semua status): ' . $orders->count() . "\n";
$statusCounts = $orders->groupBy('status')->map->count();
foreach ($statusCounts as $st => $n) {
    echo "  status={$st}: {$n}\n";
}

$paid = $orders->where('status', 'paid');
echo "\nOrders paid: " . $paid->count() . "\n";

$paymentsByOrder = collect();
if ($orders->isNotEmpty()) {
    $paymentsByOrder = DB::table('order_payment')
        ->whereIn('order_id', $orders->pluck('id'))
        ->select('id', 'order_id', 'paid_number', 'payment_code', 'payment_type', 'amount', 'change', 'kasir', 'note', 'created_at', 'kode_outlet')
        ->orderBy('id')
        ->get()
        ->groupBy('order_id');
}

$itemSums = collect();
if ($orders->isNotEmpty()) {
    $itemSums = DB::table('order_items')
        ->whereIn('order_id', $orders->pluck('id'))
        ->selectRaw('order_id, SUM(COALESCE(subtotal,0)) as sum_sub, COUNT(*) as n')
        ->groupBy('order_id')
        ->get()
        ->keyBy('order_id');
}

$categories = [
    'no_payment' => [],
    'missing_payment' => [],
    'over_payment' => [],
    'duplicate_lines' => [],
    'cash_change_only' => [],
    'ok' => [],
];

$sumGtAll = 0.0;
$sumPayAll = 0.0;
$sumGtPaid = 0.0;
$sumPayPaid = 0.0;
$componentMismatch = [];

foreach ($orders as $o) {
    $pays = $paymentsByOrder->get((string) $o->id, collect());
    $sumAmount = (float) $pays->sum('amount');
    $sumNet = (float) $pays->sum(fn ($p) => (float) $p->amount - (float) ($p->change ?? 0));
    $grand = (float) ($o->grand_total ?? 0);
    $fromParts = sumComponents($o);
    $diffParts = round($grand - $fromParts, 2);
    $item = $itemSums->get($o->id);
    $itemSum = $item ? (float) $item->sum_sub : 0.0;

    $sumGtAll += $grand;
    $sumPayAll += $sumAmount;
    if ($o->status === 'paid') {
        $sumGtPaid += $grand;
        $sumPayPaid += $sumAmount;
    }

    if (abs($diffParts) > $tolerance && $o->status === 'paid') {
        $componentMismatch[] = compact('o', 'fromParts', 'diffParts', 'itemSum', 'sumAmount');
    }

    $dupes = $pays->groupBy(fn ($p) => ($p->payment_code ?? '') . '|' . ($p->payment_type ?? '') . '|' . (string) $p->amount)
        ->filter(fn ($g) => $g->count() > 1);

    $row = compact('o', 'pays', 'sumAmount', 'sumNet', 'grand', 'fromParts', 'diffParts', 'itemSum', 'dupes');

    if ($o->status !== 'paid') {
        continue;
    }

    if ($pays->isEmpty()) {
        if (abs($grand) > $tolerance) {
            $categories['no_payment'][] = $row;
        } else {
            $categories['ok'][] = $row;
        }
        continue;
    }

    if ($dupes->isNotEmpty() && abs($grand - $sumAmount) > $tolerance) {
        $categories['duplicate_lines'][] = $row;
        continue;
    }

    if (abs($grand - $sumAmount) <= $tolerance) {
        $categories['ok'][] = $row;
        continue;
    }

    if (abs($grand - $sumNet) <= $tolerance) {
        $categories['cash_change_only'][] = $row;
        continue;
    }

    if ($sumAmount < $grand - $tolerance) {
        $categories['missing_payment'][] = $row;
    } else {
        $categories['over_payment'][] = $row;
    }
}

echo "\n--- Agregat semua status ---\n";
echo 'SUM grand_total: ' . fmt($sumGtAll) . "\n";
echo 'SUM payment amount: ' . fmt($sumPayAll) . "\n";
echo 'Selisih: ' . fmt($sumGtAll - $sumPayAll) . "\n";

echo "\n--- Agregat status=paid ---\n";
echo 'SUM grand_total: ' . fmt($sumGtPaid) . "\n";
echo 'SUM payment amount: ' . fmt($sumPayPaid) . "\n";
echo 'Selisih GT - pay: ' . fmt($sumGtPaid - $sumPayPaid) . "\n";

echo "\n--- Kategori selisih (paid only) ---\n";
foreach ($categories as $cat => $rows) {
    if ($cat === 'ok') {
        echo "  ok: " . count($rows) . "\n";
        continue;
    }
    echo "  {$cat}: " . count($rows) . "\n";
}

$mismatchPaid = array_merge(
    $categories['no_payment'],
    $categories['missing_payment'],
    $categories['over_payment'],
    $categories['duplicate_lines'],
    $categories['cash_change_only']
);

echo "\nOrder paid dengan selisih GT vs payment: " . count($mismatchPaid) . "\n";
echo "Order paid dengan GT vs komponen (total-disc-cb+pb1+svc+comm+round) selisih: " . count($componentMismatch) . "\n\n";

$printRows = static function (string $title, array $rows) {
    if ($rows === []) {
        return;
    }
    echo "=== {$title} ===\n";
    foreach ($rows as $row) {
        $o = $row['o'];
        echo sprintf(
            "%s | %s | status=%s mode=%s | GT %s | pay %s | net %s | selisih %s | %d pay\n",
            substr((string) $o->created_at, 0, 19),
            $o->paid_number ?: $o->nomor,
            $o->status,
            $o->mode ?? '-',
            fmt($row['grand']),
            fmt($row['sumAmount']),
            fmt($row['sumNet']),
            fmt($row['grand'] - $row['sumAmount']),
            $row['pays']->count()
        );
        echo sprintf(
            "  total=%s disc=%s manual=%s cb=%s dpp=%s pb1=%s svc=%s comm=%s rnd=%s | parts=%s partsΔ=%s | items=%s\n",
            fmt($o->total),
            fmt($o->discount),
            fmt($o->manual_discount_amount),
            fmt($o->cashback),
            fmt($o->dpp ?? 0),
            fmt($o->pb1),
            fmt($o->service),
            fmt($o->commfee),
            fmt($o->rounding),
            fmt($row['fromParts']),
            fmt($row['diffParts']),
            fmt($row['itemSum'])
        );
        foreach ($row['pays'] as $p) {
            echo sprintf(
                "  - [%s] %s/%s amt=%s chg=%s kasir=%s note=%s @%s\n",
                $p->id,
                $p->payment_code ?? '-',
                $p->payment_type ?? '-',
                fmt($p->amount),
                fmt($p->change ?? 0),
                $p->kasir ?? '-',
                $p->note ?? '-',
                substr((string) $p->created_at, 0, 19)
            );
        }
        echo "\n";
    }
};

$printRows('Paid tanpa payment', $categories['no_payment']);
$printRows('Payment kurang (GT > sum amount)', $categories['missing_payment']);
$printRows('Payment lebih (sum amount > GT)', $categories['over_payment']);
$printRows('Kemungkinan duplikat baris payment', $categories['duplicate_lines']);
$printRows('Selisih hanya karena change cash', $categories['cash_change_only']);

if ($componentMismatch !== []) {
    echo "=== Grand total vs komponen (paid, bukan masalah payment) ===\n";
    foreach ($componentMismatch as $row) {
        $o = $row['o'];
        echo sprintf(
            "%s | %s | GT %s | parts %s | Δ %s | pay %s | items %s\n",
            substr((string) $o->created_at, 0, 19),
            $o->paid_number ?: $o->nomor,
            fmt($o->grand_total),
            fmt($row['fromParts']),
            fmt($row['diffParts']),
            fmt($row['sumAmount']),
            fmt($row['itemSum'])
        );
    }
    echo "\n";
}

// Payment rows whose created_at is 28 Aug but order is another day (or vice versa)
$orphanPays = DB::table('order_payment as op')
    ->leftJoin('orders as o', 'o.id', '=', 'op.order_id')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->where(function ($q) use ($kodeOutlet, $date) {
        $q->where('op.kode_outlet', $kodeOutlet)
            ->orWhere('o.kode_outlet', $kodeOutlet);
    })
    ->where(function ($q) use ($date) {
        $q->whereDate('op.created_at', $date)
            ->orWhereDate('o.created_at', $date);
    })
    ->where(function ($q) use ($date) {
        $q->whereNull('o.id')
            ->orWhereRaw('DATE(op.created_at) <> DATE(o.created_at)');
    })
    ->select([
        'op.id as pay_id', 'op.order_id', 'op.amount', 'op.created_at as pay_at',
        'op.payment_code', 'o.created_at as order_at', 'o.paid_number', 'o.status', 'o.kode_outlet',
        'tdo.nama_outlet',
    ])
    ->orderBy('op.created_at')
    ->get();

echo "=== Payment vs order beda tanggal / payment yatim (FC005, sentuh {$date}) ===\n";
echo 'Count: ' . $orphanPays->count() . "\n";
foreach ($orphanPays as $p) {
    echo sprintf(
        "  pay %s amt=%s pay_at=%s | order %s status=%s order_at=%s\n",
        $p->pay_id,
        fmt($p->amount),
        substr((string) $p->pay_at, 0, 19),
        $p->paid_number ?: ($p->order_id ?? 'NULL'),
        $p->status ?? 'NO_ORDER',
        $p->order_at ? substr((string) $p->order_at, 0, 19) : '-'
    );
}
echo "\n";

$csv = __DIR__ . '/trace_fc005_aug28_payment.csv';
$fp = fopen($csv, 'w');
fputcsv($fp, [
    'category', 'order_id', 'created_at', 'paid_number', 'status', 'mode',
    'grand_total', 'sum_amount', 'sum_net', 'diff_gt_pay',
    'total', 'discount', 'manual_discount', 'cashback', 'dpp', 'pb1', 'service', 'commfee', 'rounding',
    'sum_parts', 'diff_gt_parts', 'item_sum', 'payments',
]);
foreach (array_merge($mismatchPaid, $componentMismatch) as $row) {
    $o = $row['o'];
    $cat = 'component';
    foreach ($categories as $c => $rows) {
        if ($c === 'ok') {
            continue;
        }
        foreach ($rows as $r) {
            if ($r['o']->id === $o->id) {
                $cat = $c;
                break 2;
            }
        }
    }
    $detail = $row['pays']->map(fn ($p) => "#{$p->id} {$p->payment_code}/{$p->payment_type}:{$p->amount}")->implode(' | ');
    fputcsv($fp, [
        $cat,
        $o->id,
        $o->created_at,
        $o->paid_number ?: $o->nomor,
        $o->status,
        $o->mode,
        $o->grand_total,
        $row['sumAmount'],
        $row['sumNet'] ?? 0,
        (float) $o->grand_total - $row['sumAmount'],
        $o->total,
        $o->discount,
        $o->manual_discount_amount,
        $o->cashback,
        $o->dpp,
        $o->pb1,
        $o->service,
        $o->commfee,
        $o->rounding,
        $row['fromParts'] ?? '',
        $row['diffParts'] ?? '',
        $row['itemSum'] ?? '',
        $detail,
    ]);
}
fclose($fp);
echo "CSV: {$csv}\n";
