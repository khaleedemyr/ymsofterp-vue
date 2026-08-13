<?php

declare(strict_types=1);

/**
 * Perbaiki data order yang bikin EOD selisih, tanpa ubah rumus laporan.
 *
 * 1) orders.total diset ke SUM(order_items.subtotal) jika item ada dan beda
 * 2) orders.commfee diset ke grand_total - dpp - pb1 - service - rounding jika selisih
 *
 * grand_total / pb1 / service / payment tidak diubah.
 *
 * Usage:
 *   php scripts/fix_eod_stale_order_totals.php
 *   php scripts/fix_eod_stale_order_totals.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv ?? [], true);
$from = '2026-08-01';
$to = '2026-08-13';
$tolerance = 0.01;

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

$orders = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->where('o.status', 'paid')
    ->whereDate('o.created_at', '>=', $from)
    ->whereDate('o.created_at', '<=', $to)
    ->select([
        'o.id',
        'o.created_at',
        'o.paid_number',
        'o.nomor',
        'o.kode_outlet',
        'tdo.nama_outlet',
        'o.mode',
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

$itemSums = DB::table('order_items')
    ->whereIn('order_id', $orders->pluck('id')->all())
    ->selectRaw('order_id, SUM(COALESCE(subtotal,0)) as sum_sub')
    ->groupBy('order_id')
    ->pluck('sum_sub', 'order_id');

$toFix = [];

foreach ($orders as $o) {
    $itemSum = (float) ($itemSums[$o->id] ?? 0);
    $total = (float) $o->total;
    $dpp = (float) $o->dpp;
    $pb1 = (float) $o->pb1;
    $svc = (float) $o->service;
    $comm = (float) $o->commfee;
    $rnd = (float) $o->rounding;
    $gt = (float) $o->grand_total;

    $newTotal = $total;
    $newComm = $comm;
    $reasons = [];

    if ($itemSum > 0 && abs($itemSum - $total) > $tolerance) {
        $newTotal = $itemSum;
        $reasons[] = 'total';
    }

    $derivedComm = round($gt - $dpp - $pb1 - $svc - $rnd, 2);
    if ($derivedComm >= 0 && abs($derivedComm - $comm) > $tolerance) {
        $newComm = $derivedComm;
        $reasons[] = 'commfee';
    }

    if ($reasons === []) {
        continue;
    }

    $toFix[] = compact('o', 'itemSum', 'newTotal', 'newComm', 'derivedComm', 'reasons');
}

echo ($apply ? '=== APPLY' : '=== TRACE') . " fix stale orders.total / missing commfee ({$from} s/d {$to}) ===\n";
echo 'Orders paid: ' . $orders->count() . ', perlu fix: ' . count($toFix) . "\n\n";

foreach ($toFix as $row) {
    $o = $row['o'];
    echo sprintf(
        "%s | %s | %s | %s\n  total %s => %s (items %s) | commfee %s => %s | dpp=%s gt=%s\n",
        substr((string) $o->created_at, 0, 10),
        $o->nama_outlet,
        $o->paid_number ?: $o->nomor,
        implode(',', $row['reasons']),
        fmt($o->total),
        fmt($row['newTotal']),
        fmt($row['itemSum']),
        fmt($o->commfee),
        fmt($row['newComm']),
        fmt($o->dpp),
        fmt($o->grand_total)
    );
}

if ($toFix === []) {
    echo "Tidak ada order yang perlu diperbaiki.\n";
    exit(0);
}

if (!$apply) {
    echo "\nJalankan dengan --apply untuk update database.\n";
    exit(0);
}

$fixed = 0;
DB::beginTransaction();
try {
    foreach ($toFix as $row) {
        $o = $row['o'];
        $update = ['updated_at' => now()];
        if (in_array('total', $row['reasons'], true)) {
            $update['total'] = $row['newTotal'];
        }
        if (in_array('commfee', $row['reasons'], true)) {
            $update['commfee'] = $row['newComm'];
        }
        DB::table('orders')->where('id', $o->id)->update($update);
        $fixed++;
    }
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    echo 'GAGAL: ' . $e->getMessage() . "\n";
    exit(1);
}

echo "\nUpdated {$fixed} orders.\n\n";

echo "=== VERIFY EOD identity setelah fix ===\n";
$days = DB::select("
    SELECT tdo.nama_outlet, DATE(o.created_at) d, COUNT(*) n,
           SUM(o.total) total,
           SUM(
             CASE
               WHEN COALESCE(o.discount,0) > 0 AND COALESCE(o.manual_discount_amount,0) > 0
                 THEN GREATEST(o.discount, o.manual_discount_amount)
               ELSE COALESCE(o.discount,0) + COALESCE(o.manual_discount_amount,0)
             END
           ) disc,
           SUM(COALESCE(o.cashback,0)) cb,
           SUM(o.dpp) dpp,
           SUM(o.pb1) pb1,
           SUM(o.service) svc,
           SUM(o.commfee) comm,
           SUM(COALESCE(o.rounding,0)) rnd,
           SUM(o.grand_total) gt
    FROM orders o
    LEFT JOIN tbl_data_outlet tdo ON o.kode_outlet = tdo.qr_code
    WHERE o.status = 'paid'
      AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY tdo.nama_outlet, DATE(o.created_at)
    HAVING ABS(
        (SUM(o.total) - SUM(
             CASE
               WHEN COALESCE(o.discount,0) > 0 AND COALESCE(o.manual_discount_amount,0) > 0
                 THEN GREATEST(o.discount, o.manual_discount_amount)
               ELSE COALESCE(o.discount,0) + COALESCE(o.manual_discount_amount,0)
             END
           ) - SUM(COALESCE(o.cashback,0)) + SUM(o.pb1) + SUM(o.service) + SUM(o.commfee) + SUM(COALESCE(o.rounding,0)))
        - SUM(o.grand_total)
    ) > 1
    ORDER BY d, tdo.nama_outlet
", [$from, $to]);

if ($days === []) {
    echo "Semua EOD {$from} s/d {$to}: Net + PB1 + Service + Commfee + Rounding = Grand Total.\n";
} else {
    echo "Masih selisih:\n";
    foreach ($days as $d) {
        $net = (float) $d->total - (float) $d->disc - (float) $d->cb;
        $parts = $net + (float) $d->pb1 + (float) $d->svc + (float) $d->comm + (float) $d->rnd;
        echo sprintf(
            "  %s | %s | n=%s | parts-gt=%s | net-dpp=%s\n",
            $d->d,
            $d->nama_outlet,
            $d->n,
            fmt($parts - (float) $d->gt),
            fmt($net - (float) $d->dpp)
        );
    }
}
