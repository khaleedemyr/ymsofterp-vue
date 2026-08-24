<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$orderId = 'CPT-msykoet29lg3';
$order = DB::table('orders')->where('id', $orderId)->first();
if (!$order) {
    fwrite(STDERR, "Order not found\n");
    exit(1);
}

$itemsTotal = (float) DB::table('order_items')
    ->where('order_id', $orderId)
    ->sum(DB::raw('qty * price'));

$manualDiscount = (float) ($order->manual_discount_amount ?? 0);
$promoDiscount = (float) ($order->discount ?? 0);
$totalDiscount = $manualDiscount + $promoDiscount;

// Formula sama seperti header lama:
// DPP = total - discount
// Service = 5% DPP
// PB1 = 10% (DPP + service)
// Grand = DPP + service + PB1
$newTotal = (int) round($itemsTotal);
$newDpp = (int) round(max(0, $newTotal - $totalDiscount));
$newService = (int) round($newDpp * 0.05);
$newPb1 = (int) round(($newDpp + $newService) * 0.10);
$newGrand = $newDpp + $newService + $newPb1;

echo "BEFORE:\n";
echo json_encode([
    'total' => $order->total,
    'discount' => $order->discount,
    'manual_discount_amount' => $order->manual_discount_amount,
    'dpp' => $order->dpp,
    'service' => $order->service,
    'pb1' => $order->pb1,
    'grand_total' => $order->grand_total,
], JSON_PRETTY_PRINT) . PHP_EOL;

echo "ITEMS TOTAL={$itemsTotal}\n";
echo "AFTER (planned):\n";
echo json_encode([
    'total' => $newTotal,
    'dpp' => $newDpp,
    'service' => $newService,
    'pb1' => $newPb1,
    'grand_total' => $newGrand,
], JSON_PRETTY_PRINT) . PHP_EOL;

// Find payment-like tables
$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();
$key = 'Tables_in_' . $dbName;
$paymentTables = [];
foreach ($tables as $t) {
    $name = $t->$key;
    if (preg_match('/payment|pembayaran|orders_payment|order_pay/i', $name)) {
        $paymentTables[] = $name;
    }
}
echo "payment tables: " . implode(', ', $paymentTables) . PHP_EOL;

foreach ($paymentTables as $name) {
    $cols = Schema::getColumnListing($name);
    $hasOrder = in_array('order_id', $cols, true) || in_array('id_order', $cols, true);
    if (!$hasOrder) continue;
    $fk = in_array('order_id', $cols, true) ? 'order_id' : 'id_order';
    $rows = DB::table($name)->where($fk, $orderId)->get();
    echo "{$name} count={$rows->count()}\n";
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}

$dry = in_array('--dry-run', $argv ?? [], true);
if ($dry) {
    echo "DRY RUN only\n";
    exit(0);
}

DB::beginTransaction();
try {
    DB::table('orders')->where('id', $orderId)->update([
        'total' => $newTotal,
        'dpp' => $newDpp,
        'service' => $newService,
        'pb1' => $newPb1,
        'grand_total' => $newGrand,
        'updated_at' => now(),
    ]);

    // Adjust payment amount if single payment row exists and equals old grand_total
    foreach ($paymentTables as $name) {
        $cols = Schema::getColumnListing($name);
        if (!in_array('order_id', $cols, true) && !in_array('id_order', $cols, true)) {
            continue;
        }
        $fk = in_array('order_id', $cols, true) ? 'order_id' : 'id_order';
        $amountCol = null;
        foreach (['amount', 'nominal', 'bayar', 'payment_amount', 'total'] as $c) {
            if (in_array($c, $cols, true)) {
                $amountCol = $c;
                break;
            }
        }
        if (!$amountCol) continue;

        $rows = DB::table($name)->where($fk, $orderId)->get();
        if ($rows->count() === 1) {
            $row = $rows->first();
            $oldAmount = (float) $row->$amountCol;
            // only rewrite if payment matched old inflated grand total (or old total)
            if (abs($oldAmount - (float) $order->grand_total) < 1 || abs($oldAmount - (float) $order->total) < 1) {
                DB::table($name)->where('id', $row->id)->update([
                    $amountCol => $newGrand,
                ]);
                echo "Updated {$name}.{$amountCol}: {$oldAmount} -> {$newGrand}\n";
            } else {
                echo "Skip {$name} amount={$oldAmount} (not matching old grand/total)\n";
            }
        } elseif ($rows->count() > 1) {
            echo "Skip {$name}: multiple payment rows, need manual review\n";
        }
    }

    DB::commit();

    $after = DB::table('orders')->where('id', $orderId)->first();
    echo "\nUPDATED:\n";
    echo json_encode([
        'id' => $after->id,
        'paid_number' => $after->paid_number,
        'total' => $after->total,
        'dpp' => $after->dpp,
        'service' => $after->service,
        'pb1' => $after->pb1,
        'grand_total' => $after->grand_total,
        'manual_discount_amount' => $after->manual_discount_amount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // verify day totals
    $headerTotal = DB::table('orders')
        ->where('kode_outlet', 'SH011')
        ->whereDate('created_at', '2026-08-18')
        ->where('status', 'paid')
        ->sum('total');
    $itemTotal = DB::table('order_items')
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->where('orders.kode_outlet', 'SH011')
        ->whereDate('orders.created_at', '2026-08-18')
        ->where('orders.status', 'paid')
        ->sum(DB::raw('order_items.qty * order_items.price'));
    echo "\nDAY CHECK SH011 2026-08-18\n";
    echo "SUM orders.total={$headerTotal}\n";
    echo "SUM items={$itemTotal}\n";
    echo "GAP=" . ((float)$headerTotal - (float)$itemTotal) . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
