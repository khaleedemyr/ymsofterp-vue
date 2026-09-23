<?php

declare(strict_types=1);

/**
 * Trace EOD selisih Justus Steak House Paris Van Java — 2026-09-14
 *
 * Usage:
 *   php scripts/trace_eod_pvj_sep14.php
 *   php scripts/trace_eod_pvj_sep14.php --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);
$date = '2026-09-14';
$tolerance = 0.5;

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

$cols = Schema::getColumnListing('orders');
echo "orders cols sample: " . implode(', ', array_slice($cols, 0, 40)) . "\n\n";

$outlets = DB::table('tbl_data_outlet')
    ->where(function ($q) {
        $q->where('nama_outlet', 'like', '%Paris Van Java%')
            ->orWhere('nama_outlet', 'like', '%Paris van Java%')
            ->orWhere('nama_outlet', 'like', '%PVJ%');
    })
    ->get();

echo "=== OUTLETS MATCH ===\n";
foreach ($outlets as $o) {
    $arr = (array) $o;
    echo json_encode([
        'id_outlet' => $arr['id_outlet'] ?? null,
        'nama_outlet' => $arr['nama_outlet'] ?? null,
        'qr_code' => $arr['qr_code'] ?? null,
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
echo "\n";

if ($outlets->isEmpty()) {
    echo "Outlet tidak ditemukan\n";
    exit(1);
}

$qrCodes = $outlets->pluck('qr_code')->filter()->values()->all();

$orderSelect = [
    'o.id',
    'o.created_at',
    'o.status',
    'o.mode',
    'o.total',
    'o.discount',
    'o.cashback',
    'o.dpp',
    'o.pb1',
    'o.service',
    'o.commfee',
    'o.rounding',
    'o.grand_total',
];

foreach (['paid_number', 'nomor', 'kode_outlet', 'pax', 'manual_discount_amount'] as $c) {
    if (in_array($c, $cols, true)) {
        $orderSelect[] = 'o.' . $c;
    }
}

$orders = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->whereIn('o.kode_outlet', $qrCodes)
    ->whereDate('o.created_at', $date)
    ->where('o.status', 'paid')
    ->select(array_merge($orderSelect, ['tdo.nama_outlet']))
    ->orderBy('o.created_at')
    ->get();

echo "=== SUMMARY {$date} paid orders: {$orders->count()} ===\n";

$sum = [
    'total' => 0.0,
    'discount' => 0.0,
    'cashback' => 0.0,
    'dpp' => 0.0,
    'pb1' => 0.0,
    'service' => 0.0,
    'commfee' => 0.0,
    'rounding' => 0.0,
    'grand_total' => 0.0,
    'pax' => 0,
];

foreach ($orders as $o) {
    $sum['total'] += (float) $o->total;
    $sum['discount'] += effectiveDiscount($o);
    $sum['cashback'] += (float) ($o->cashback ?? 0);
    $sum['dpp'] += (float) ($o->dpp ?? 0);
    $sum['pb1'] += (float) ($o->pb1 ?? 0);
    $sum['service'] += (float) ($o->service ?? 0);
    $sum['commfee'] += (float) ($o->commfee ?? 0);
    $sum['rounding'] += (float) ($o->rounding ?? 0);
    $sum['grand_total'] += (float) ($o->grand_total ?? 0);
    $sum['pax'] += (int) ($o->pax ?? 0);
}

$netSales = $sum['total'] - $sum['discount'] - $sum['cashback'];
$rebuiltGt = $sum['dpp'] + $sum['pb1'] + $sum['service'] + $sum['commfee'] + $sum['rounding'];
$altRebuilt = $netSales + $sum['pb1'] + $sum['service'] + $sum['commfee'] + $sum['rounding'];

echo sprintf("Sales (total)     : %s\n", fmt($sum['total']));
echo sprintf("Disc              : %s\n", fmt($sum['discount']));
echo sprintf("Cashback          : %s\n", fmt($sum['cashback']));
echo sprintf("Net (total-disc)  : %s\n", fmt($netSales));
echo sprintf("DPP               : %s\n", fmt($sum['dpp']));
echo sprintf("PB1               : %s\n", fmt($sum['pb1']));
echo sprintf("Service           : %s\n", fmt($sum['service']));
echo sprintf("Commfee           : %s\n", fmt($sum['commfee']));
echo sprintf("Rounding          : %s\n", fmt($sum['rounding']));
echo sprintf("Grand Total       : %s\n", fmt($sum['grand_total']));
echo sprintf("Pax               : %s\n", $sum['pax']);
echo sprintf("Rebuild GT (dpp+pb1+svc+comm+rnd) : %s  SELISIH vs GT: %s\n", fmt($rebuiltGt), fmt($sum['grand_total'] - $rebuiltGt));
echo sprintf("Alt rebuild (net+pb1+svc+comm+rnd): %s  SELISIH vs GT: %s\n", fmt($altRebuilt), fmt($sum['grand_total'] - $altRebuilt));
echo sprintf("DPP vs Net Sales gap: %s\n\n", fmt($sum['dpp'] - $netSales));

$itemSums = DB::table('order_items')
    ->whereIn('order_id', $orders->pluck('id')->all() ?: [0])
    ->selectRaw('order_id, SUM(COALESCE(subtotal,0)) as sum_sub')
    ->groupBy('order_id')
    ->pluck('sum_sub', 'order_id');

$payTable = Schema::hasTable('order_payments') ? 'order_payments' : (Schema::hasTable('order_payment') ? 'order_payment' : null);
$paySums = [];
if ($payTable) {
    $changeCol = in_array('change', Schema::getColumnListing($payTable), true) ? 'change' : (in_array('kembalian', Schema::getColumnListing($payTable), true) ? 'kembalian' : null);
    $payExpr = $changeCol
        ? "SUM(COALESCE(amount,0) - COALESCE(`{$changeCol}`,0)) as net_pay"
        : 'SUM(COALESCE(amount,0)) as net_pay';
    $paySums = DB::table($payTable)
        ->whereIn('order_id', $orders->pluck('id')->all() ?: [0])
        ->selectRaw("order_id, {$payExpr}")
        ->groupBy('order_id')
        ->pluck('net_pay', 'order_id')
        ->all();
}

echo "=== ORDER-LEVEL ANOMALIES ===\n";
$toFix = [];
foreach ($orders as $o) {
    $itemSum = (float) ($itemSums[$o->id] ?? 0);
    $disc = effectiveDiscount($o);
    $cashback = (float) ($o->cashback ?? 0);
    $total = (float) $o->total;
    $dpp = (float) ($o->dpp ?? 0);
    $pb1 = (float) ($o->pb1 ?? 0);
    $svc = (float) ($o->service ?? 0);
    $comm = (float) ($o->commfee ?? 0);
    $rnd = (float) ($o->rounding ?? 0);
    $gt = (float) ($o->grand_total ?? 0);
    $parts = $dpp + $pb1 + $svc + $comm + $rnd;
    $gapParts = $gt - $parts;
    $gapItems = $itemSum > 0 ? $total - $itemSum : 0;
    $payNet = isset($paySums[$o->id]) ? (float) $paySums[$o->id] : null;
    $gapPay = $payNet !== null ? $gt - $payNet : null;

    $derivedComm = round($gt - $dpp - $pb1 - $svc - $rnd, 2);
    $reasons = [];
    $newTotal = $total;
    $newComm = $comm;

    if ($itemSum > 0 && abs($itemSum - $total) > $tolerance) {
        $newTotal = $itemSum;
        $reasons[] = 'total';
    }
    if ($derivedComm >= 0 && abs($derivedComm - $comm) > $tolerance) {
        $newComm = $derivedComm;
        $reasons[] = 'commfee';
    }

    $flag = abs($gapParts) > $tolerance
        || abs($gapItems) > $tolerance
        || ($gapPay !== null && abs($gapPay) > $tolerance)
        || $reasons !== [];

    if (!$flag) {
        continue;
    }

    $label = $o->paid_number ?? $o->id;
    echo sprintf(
        "%s | %s | total=%s items=%s disc=%s dpp=%s pb1=%s svc=%s comm=%s rnd=%s GT=%s | partsGap=%s itemsGap=%s payGap=%s | derivedComm=%s reasons=%s\n",
        $label,
        substr((string) $o->created_at, 11, 8),
        fmt($total),
        fmt($itemSum),
        fmt($disc),
        fmt($dpp),
        fmt($pb1),
        fmt($svc),
        fmt($comm),
        fmt($rnd),
        fmt($gt),
        fmt($gapParts),
        fmt($gapItems),
        $gapPay === null ? '-' : fmt($gapPay),
        fmt($derivedComm),
        $reasons ? implode(',', $reasons) : '-'
    );

    if ($reasons !== []) {
        $toFix[] = compact('o', 'itemSum', 'newTotal', 'newComm', 'derivedComm', 'reasons');
    }
}

echo "\nPerlu fix (total/commfee): " . count($toFix) . "\n";

if ($apply && count($toFix) > 0) {
    echo "\n=== APPLY FIXES ===\n";
    DB::beginTransaction();
    try {
        foreach ($toFix as $row) {
            $o = $row['o'];
            $update = [];
            if (in_array('total', $row['reasons'], true)) {
                $update['total'] = $row['newTotal'];
            }
            if (in_array('commfee', $row['reasons'], true)) {
                $update['commfee'] = $row['newComm'];
            }
            if ($update === []) {
                continue;
            }
            DB::table('orders')->where('id', $o->id)->update($update);
            echo sprintf(
                "UPDATED %s | %s\n",
                $o->paid_number ?? $o->id,
                json_encode($update)
            );
        }
        DB::commit();
        echo "DONE apply.\n";
    } catch (Throwable $e) {
        DB::rollBack();
        echo 'FAILED: ' . $e->getMessage() . "\n";
        exit(1);
    }
} elseif (!$apply) {
    echo "\nDry-run only. Jalankan ulang dengan --apply untuk update.\n";
}
