<?php

declare(strict_types=1);

/**
 * Fix orders fc005: service=0, pb1=10% DPP, grand_total=dpp+pb1+commfee.
 * Sync order_payment.amount dengan grand_total baru.
 *
 * Usage:
 *   php scripts/fix_fc005_service_zero_jun13.php --from=2026-06-26 --to=2026-06-30
 *   php scripts/fix_fc005_service_zero_jun13.php --from=2026-06-26 --to=2026-06-30 --apply
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);
$tolerance = 0.01;
$kodeOutlet = 'fc005';
$dateFrom = '2026-06-13';
$dateTo = '2026-06-13';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $dateFrom = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $dateTo = substr($arg, 5);
    }
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

function calcPb1Only(float $dpp): array
{
    $service = 0.0;
    $pb1 = (float) floor($dpp * 0.10);

    return [$service, $pb1];
}

function syncPaymentToGrand(string $orderId, float $grandTotal, bool $apply): array
{
    $payments = DB::table('order_payment')->where('order_id', $orderId)->orderBy('id')->get();
    $sum = (float) $payments->sum('amount');
    $diff = round($grandTotal - $sum, 2);
    $actions = [];

    if (abs($diff) <= 0.01) {
        return $actions;
    }

    if ($payments->count() === 1) {
        $p = $payments->first();
        $actions[] = "payment {$p->id}: {$p->amount} => {$grandTotal}";
        if ($apply) {
            DB::table('order_payment')->where('id', $p->id)->update([
                'amount' => $grandTotal,
                'note' => trim(($p->note ?? '') . ' [fix service=0 pb1-only sync]'),
            ]);
            DB::table('bank_books')
                ->where('reference_type', 'order_payment')
                ->where('reference_id', $p->id)
                ->update(['amount' => $grandTotal, 'updated_at' => now()]);
        }
    } elseif ($payments->count() > 1 && $diff > 0) {
        $ref = $payments->first();
        $newId = strtolower(base_convert((string) time(), 10, 36) . \Illuminate\Support\Str::random(5));
        $actions[] = "insert split payment {$newId}: +{$diff}";
        if ($apply) {
            DB::table('order_payment')->insert([
                'id' => $newId,
                'order_id' => $orderId,
                'paid_number' => $ref->paid_number,
                'payment_type' => $ref->payment_type,
                'payment_code' => $ref->payment_code,
                'bank_id' => $ref->bank_id ?? null,
                'amount' => $diff,
                'created_at' => $ref->created_at,
                'kasir' => $ref->kasir ?? '-',
                'note' => '[fix service=0 pb1-only split selisih]',
                'change' => 0,
                'kode_outlet' => $ref->kode_outlet ?? null,
            ]);
        }
    } elseif ($payments->count() > 1 && $diff < 0) {
        $target = $payments->sortByDesc('amount')->first();
        $newAmt = (float) $target->amount + $diff;
        $actions[] = "kurangi payment {$target->id}: {$target->amount} => {$newAmt}";
        if ($apply) {
            DB::table('order_payment')->where('id', $target->id)->update(['amount' => $newAmt]);
            DB::table('bank_books')
                ->where('reference_type', 'order_payment')
                ->where('reference_id', $target->id)
                ->update(['amount' => $newAmt, 'updated_at' => now()]);
        }
    } elseif ($payments->count() === 0) {
        $actions[] = 'WARN: tidak ada payment';
    }

    return $actions;
}

$outletName = DB::table('tbl_data_outlet')->where('qr_code', $kodeOutlet)->value('nama_outlet') ?? $kodeOutlet;

$dateLabel = $dateFrom === $dateTo ? $dateFrom : "{$dateFrom} s/d {$dateTo}";
echo ($apply ? '=== APPLY' : '=== TRACE') . " fix service=0 pb1-only | {$kodeOutlet} ({$outletName}) | {$dateLabel} ===\n\n";

$orders = DB::table('orders')
    ->where('kode_outlet', $kodeOutlet)
    ->whereDate('created_at', '>=', $dateFrom)
    ->whereDate('created_at', '<=', $dateTo)
    ->where('status', 'paid')
    ->orderBy('created_at')
    ->get();

echo "Orders paid: {$orders->count()}\n\n";

$toFix = [];

foreach ($orders as $o) {
    $disc = effectiveDiscount($o);
    $dpp = max(0, (float) $o->total - $disc - (float) ($o->cashback ?? 0));
    [$expSvc, $expPb1] = calcPb1Only($dpp);
    $commfee = (float) ($o->commfee ?? 0);
    $expGrand = $dpp + $expPb1 + $commfee;

    $svcDiff = abs((float) $o->service - $expSvc);
    $pb1Diff = abs((float) $o->pb1 - $expPb1);
    $gtDiff = abs((float) $o->grand_total - $expGrand);

    if ($svcDiff <= $tolerance && $pb1Diff <= $tolerance && $gtDiff <= $tolerance) {
        continue;
    }

    $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');

    echo sprintf(
        "%s | dpp=%s | svc %s=>0 | pb1 %s=>%s | GT %s=>%s | pay=%s\n",
        $o->paid_number ?: $o->nomor,
        number_format($dpp, 0, ',', '.'),
        number_format((float) $o->service, 0, ',', '.'),
        number_format((float) $o->pb1, 0, ',', '.'),
        number_format($expPb1, 0, ',', '.'),
        number_format((float) $o->grand_total, 0, ',', '.'),
        number_format($expGrand, 0, ',', '.'),
        number_format($pay, 0, ',', '.'),
    );

    $toFix[] = compact('o', 'dpp', 'expSvc', 'expPb1', 'expGrand');
}

echo "\nPerlu fix: " . count($toFix) . " order\n";

if ($toFix === []) {
    echo "Tidak ada order yang perlu diperbaiki.\n";
    exit(0);
}

if (!$apply) {
    echo "\nJalankan dengan --apply untuk update database.\n";
    exit(0);
}

$fixedOrders = 0;
$fixedPayments = 0;

foreach ($toFix as $row) {
    $o = $row['o'];
    $update = [
        'service' => 0,
        'pb1' => $row['expPb1'],
        'grand_total' => $row['expGrand'],
        'updated_at' => now(),
    ];
    if (Schema::hasColumn('orders', 'dpp')) {
        $update['dpp'] = $row['dpp'];
    }

    DB::table('orders')->where('id', $o->id)->update($update);
    $fixedOrders++;

    $payActions = syncPaymentToGrand((string) $o->id, (float) $row['expGrand'], true);
    if ($payActions !== []) {
        $fixedPayments++;
        foreach ($payActions as $a) {
            echo "  {$o->paid_number}: {$a}\n";
        }
    }
}

echo "\nFixed orders: {$fixedOrders}, payment adjusted: {$fixedPayments}\n";

// Verifikasi
$taxBad = 0;
$payBad = 0;
foreach ($orders as $o) {
    $o = DB::table('orders')->where('id', $o->id)->first();
    $disc = effectiveDiscount($o);
    $dpp = max(0, (float) $o->total - $disc - (float) ($o->cashback ?? 0));
    [$expSvc, $expPb1] = calcPb1Only($dpp);
    $expGrand = $dpp + $expPb1 + (float) ($o->commfee ?? 0);

    if (abs((float) $o->service) > $tolerance || abs((float) $o->pb1 - $expPb1) > $tolerance) {
        $taxBad++;
    }
    $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');
    if (abs((float) $o->grand_total - $pay) > $tolerance) {
        $payBad++;
    }
    if (abs((float) $o->grand_total - $expGrand) > $tolerance) {
        $taxBad++;
    }
}

echo "Verifikasi: taxBad={$taxBad} payBad={$payBad} / {$orders->count()}\n";
