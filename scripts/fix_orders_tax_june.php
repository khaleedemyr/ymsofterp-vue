<?php

declare(strict_types=1);

/**
 * Trace & fix orders: pb1 + service (mode Service+PB1) dan grand_total.
 * Lalu samakan order_payment.amount dengan grand_total baru.
 *
 * Usage:
 *   php scripts/fix_orders_tax_june.php              # trace only
 *   php scripts/fix_orders_tax_june.php --apply      # apply fixes
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv ?? [], true);
$tolerance = 0.01;

$scopes = [
    ['outlet' => 'Justus Steak House Festival Citylink', 'from' => '2026-06-08', 'to' => '2026-06-08'],
    ['outlet' => 'JUSTUS FCL', 'from' => '2026-06-13', 'to' => '2026-06-13'],
    ['outlet' => 'JUSTUS FCL', 'from' => '2026-06-27', 'to' => '2026-06-27'],
];

$outletCodes = DB::table('tbl_data_outlet')
    ->whereIn('nama_outlet', array_unique(array_column($scopes, 'outlet')))
    ->pluck('qr_code', 'nama_outlet');

function effectiveDiscount(object $o): float
{
    $discount = (float) ($o->discount ?? 0);
    $manual = (float) ($o->manual_discount_amount ?? 0);
    if ($discount > 0 && $manual > 0) {
        return max($discount, $manual);
    }

    return $discount + $manual;
}

function calcServicePb1(float $dpp): array
{
    $service = (float) floor($dpp * 0.05);
    $pb1 = (float) floor(($dpp + $service) * 0.10);

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
                'note' => trim(($p->note ?? '') . ' [fix tax sync grand_total]'),
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
                'note' => '[fix tax sync split selisih]',
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

echo ($apply ? '=== APPLY' : '=== TRACE') . " fix pb1/service/grand_total (Juni 2026) ===\n\n";

$toFix = [];
$payFix = [];

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

    $bad = 0;
    foreach ($orders as $o) {
        $disc = effectiveDiscount($o);
        $dpp = max(0, (float) $o->total - $disc - (float) ($o->cashback ?? 0));
        [$expSvc, $expPb1] = calcServicePb1($dpp);
        $commfee = (float) ($o->commfee ?? 0);
        $rounding = (float) ($o->rounding ?? 0);
        $expGrand = $dpp + $expSvc + $expPb1 + $commfee + $rounding;

        $pb1Diff = abs((float) $o->pb1 - $expPb1);
        $svcDiff = abs((float) $o->service - $expSvc);
        $gtDiff = abs((float) $o->grand_total - $expGrand);

        if ($pb1Diff <= $tolerance && $svcDiff <= $tolerance && $gtDiff <= $tolerance) {
            continue;
        }

        $bad++;
        $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');

        echo sprintf(
            "%s | pb1 %s=>%s | svc %s=>%s | GT %s=>%s | pay=%s\n",
            $o->paid_number ?: $o->nomor,
            number_format((float) $o->pb1, 0, ',', '.'),
            number_format($expPb1, 0, ',', '.'),
            number_format((float) $o->service, 0, ',', '.'),
            number_format($expSvc, 0, ',', '.'),
            number_format((float) $o->grand_total, 0, ',', '.'),
            number_format($expGrand, 0, ',', '.'),
            number_format($pay, 0, ',', '.'),
        );

        $toFix[] = compact('o', 'dpp', 'expSvc', 'expPb1', 'expGrand', 'scope');
    }

    echo "Orders paid: {$orders->count()}, perlu fix tax: {$bad}\n\n";
}

if ($toFix === []) {
    echo "Tidak ada order yang perlu diperbaiki.\n";
    exit(0);
}

echo str_repeat('=', 80) . "\n";
echo 'Total order fix tax: ' . count($toFix) . "\n\n";

if (!$apply) {
    echo "Jalankan dengan --apply untuk update database.\n";
    exit(0);
}

$fixedOrders = 0;
$fixedPayments = 0;

foreach ($toFix as $row) {
    $o = $row['o'];
    $update = [
        'pb1' => $row['expPb1'],
        'service' => $row['expSvc'],
        'grand_total' => $row['expGrand'],
        'updated_at' => now(),
    ];
    if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'dpp')) {
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

echo "\n=== Verifikasi akhir ===\n";
foreach ($scopes as $scope) {
    $kode = $outletCodes[$scope['outlet']] ?? null;
    if (!$kode) {
        continue;
    }
    $orders = DB::table('orders')
        ->where('kode_outlet', $kode)
        ->whereDate('created_at', '>=', $scope['from'])
        ->whereDate('created_at', '<=', $scope['to'])
        ->where('status', 'paid')
        ->get();

    $taxBad = 0;
    $payBad = 0;
    $partsBad = 0;
    foreach ($orders as $o) {
        $disc = effectiveDiscount($o);
        $dpp = max(0, (float) $o->total - $disc - (float) ($o->cashback ?? 0));
        [$expSvc, $expPb1] = calcServicePb1($dpp);
        $expGrand = $dpp + $expSvc + $expPb1 + (float) ($o->commfee ?? 0) + (float) ($o->rounding ?? 0);
        $parts = $dpp + (float) $o->pb1 + (float) $o->service + (float) ($o->commfee ?? 0) + (float) ($o->rounding ?? 0);

        if (abs((float) $o->pb1 - $expPb1) > $tolerance || abs((float) $o->service - $expSvc) > $tolerance) {
            $taxBad++;
        }
        if (abs((float) $o->grand_total - $parts) > $tolerance) {
            $partsBad++;
        }
        $pay = (float) DB::table('order_payment')->where('order_id', $o->id)->sum('amount');
        if (abs((float) $o->grand_total - $pay) > $tolerance) {
            $payBad++;
        }
    }

    echo "{$scope['outlet']} {$scope['from']}: taxBad={$taxBad} partsBad={$partsBad} payBad={$payBad} / {$orders->count()}\n";
}
