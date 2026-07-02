<?php

declare(strict_types=1);

/**
 * Perbaiki order_payment agar SUM(amount) = orders.grand_total.
 * Untuk order dengan 1 baris payment: update amount ke grand_total.
 * Untuk order dengan payment kurang dan perlu split: tambah baris payment ke-2.
 *
 * Usage:
 *   php scripts/fix_orders_payment_amounts.php           # dry-run
 *   php scripts/fix_orders_payment_amounts.php --apply # eksekusi
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$apply = in_array('--apply', $argv ?? [], true);
$tolerance = 0.01;

$paidNumbers = [
    'PVJ26061672',
    'DG26061491',
    'BTR26061591',
    'BTR26070031',
    'JTFCL26060864',
    'JTFCL26060928',
    'JTFCL26060943',
    'JTFCL26060991',
    'JTFCL26061033',
    'JTFCL26061032',
    'JTFCL26061052',
    'JTFCL26061053',
];

function makePaymentId(): string
{
    return strtolower(base_convert((string) time(), 10, 36) . Str::random(5));
}

function syncBankBookAmount(string $paymentId, float $newAmount): void
{
    $updated = DB::table('bank_books')
        ->where('reference_type', 'order_payment')
        ->where('reference_id', $paymentId)
        ->update([
            'amount' => $newAmount,
            'updated_at' => now(),
        ]);

    if ($updated > 0) {
        echo "    bank_books updated: {$updated} row(s) for payment {$paymentId}\n";
    }
}

echo $apply ? "=== APPLY fix order_payment ===\n\n" : "=== DRY-RUN fix order_payment ===\n\n";

$fixed = 0;
$skipped = 0;

foreach ($paidNumbers as $paidNumber) {
    $order = DB::table('orders as o')
        ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
        ->where('o.paid_number', $paidNumber)
        ->select('o.*', 'tdo.nama_outlet')
        ->first();

    if (!$order) {
        echo "[SKIP] {$paidNumber}: order tidak ditemukan\n\n";
        $skipped++;
        continue;
    }

    $payments = DB::table('order_payment')
        ->where('order_id', $order->id)
        ->orderBy('id')
        ->get();

    $sumAmount = (float) $payments->sum('amount');
    $grand = (float) ($order->grand_total ?? 0);
    $diff = round($grand - $sumAmount, 2);

    echo "{$paidNumber} | {$order->nama_outlet} | GT " . number_format($grand, 0, ',', '.')
        . ' | pay ' . number_format($sumAmount, 0, ',', '.')
        . ' | selisih ' . number_format($diff, 0, ',', '.')
        . " | {$payments->count()} baris\n";

    if (abs($diff) <= $tolerance) {
        echo "  OK, tidak perlu fix\n\n";
        $skipped++;
        continue;
    }

    if ($payments->count() === 1) {
        $p = $payments->first();
        $oldAmount = (float) $p->amount;
        echo "  -> update payment {$p->id}: {$oldAmount} => {$grand}\n";

        if ($apply) {
            DB::table('order_payment')
                ->where('id', $p->id)
                ->update([
                    'amount' => $grand,
                    'note' => trim(($p->note ?? '') . ' [fix sync amount=grand_total]'),
                ]);
            syncBankBookAmount((string) $p->id, $grand);
        }
        $fixed++;
    } elseif ($payments->count() > 1 && $diff > $tolerance) {
        // Kurang payment: tambah baris untuk selisih (split payment hilang)
        $ref = $payments->first();
        $newId = makePaymentId();
        echo "  -> insert payment split #{$newId}: +{$diff} ({$ref->payment_code}/{$ref->payment_type})\n";

        if ($apply) {
            DB::table('order_payment')->insert([
                'id' => $newId,
                'order_id' => $order->id,
                'paid_number' => $order->paid_number ?? $ref->paid_number,
                'payment_type' => $ref->payment_type,
                'payment_code' => $ref->payment_code,
                'bank_id' => $ref->bank_id ?? null,
                'amount' => $diff,
                'card_first4' => null,
                'card_last4' => null,
                'approval_code' => null,
                'created_at' => $ref->created_at ?? $order->created_at,
                'kasir' => $ref->kasir ?? '-',
                'note' => '[fix sync split payment selisih]',
                'change' => 0,
                'kode_outlet' => $order->kode_outlet ?? $ref->kode_outlet ?? null,
            ]);
        }
        $fixed++;
    } elseif ($payments->count() > 1 && $diff < -$tolerance) {
        // Lebih payment: kurangi dari baris terakhir atau proporsional — update baris terbesar
        $excess = abs($diff);
        $target = $payments->sortByDesc('amount')->first();
        $newAmt = (float) $target->amount - $excess;
        echo "  -> kurangi payment {$target->id}: {$target->amount} => {$newAmt}\n";

        if ($apply) {
            DB::table('order_payment')
                ->where('id', $target->id)
                ->update([
                    'amount' => $newAmt,
                    'note' => trim(($target->note ?? '') . ' [fix sync amount=grand_total]'),
                ]);
            syncBankBookAmount((string) $target->id, $newAmt);
        }
        $fixed++;
    } else {
        echo "  [SKIP] pola tidak didukung (0 baris payment)\n";
        $skipped++;
    }

    echo "\n";
}

echo "Selesai. fixed={$fixed} skipped={$skipped}\n";
if (!$apply) {
    echo "Jalankan dengan --apply untuk menulis ke database.\n";
}

// Verifikasi
echo "\n=== Verifikasi ===\n";
foreach ($paidNumbers as $paidNumber) {
    $order = DB::table('orders')->where('paid_number', $paidNumber)->first();
    if (!$order) {
        continue;
    }
    $sum = (float) DB::table('order_payment')->where('order_id', $order->id)->sum('amount');
    $gt = (float) $order->grand_total;
    $ok = abs($gt - $sum) <= $tolerance ? 'OK' : 'FAIL';
    echo "{$paidNumber}: GT={$gt} pay={$sum} [{$ok}]\n";
}
