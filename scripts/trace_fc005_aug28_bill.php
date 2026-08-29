<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$paid = 'JTFCL26081187';

function fmt($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

$order = DB::table('orders')->where('paid_number', $paid)->first();
if (!$order) {
    echo "Order {$paid} tidak ditemukan\n";
    exit(1);
}

echo "=== ORDER ===\n";
foreach ((array) $order as $k => $v) {
    if (is_string($v) && strlen($v) > 200) {
        $v = substr($v, 0, 200) . '...';
    }
    echo "  {$k}: {$v}\n";
}

echo "\n=== ITEMS ===\n";
$items = DB::table('order_items')->where('order_id', $order->id)->orderBy('created_at')->get();
foreach ($items as $it) {
    echo sprintf("  %s x%s @%s = %s | %s\n", $it->item_name, fmt($it->qty), fmt($it->price), fmt($it->subtotal), $it->id);
}

echo "\n=== PAYMENTS ===\n";
$pays = DB::table('order_payment')->where('order_id', $order->id)->orderBy('id')->get();
foreach ($pays as $p) {
    echo "  -- payment --\n";
    foreach ((array) $p as $k => $v) {
        echo "    {$k}: {$v}\n";
    }
}

$payIds = $pays->pluck('id')->all();

echo "\n=== BANK BOOKS (reference_type=order_payment) ===\n";
if (Schema::hasTable('bank_books')) {
    $bb = DB::table('bank_books')
        ->where('reference_type', 'order_payment')
        ->whereIn('reference_id', $payIds)
        ->get();
    echo 'count: ' . $bb->count() . "\n";
    foreach ($bb as $row) {
        echo sprintf(
            "  id=%s acc=%s date=%s type=%s amt=%s bal=%s ref=%s desc=%s\n",
            $row->id,
            $row->bank_account_id,
            $row->transaction_date,
            $row->transaction_type,
            fmt($row->amount),
            fmt($row->balance ?? 0),
            $row->reference_id,
            $row->description ?? ''
        );
    }
}

echo "\n=== JURNAL (reference_type=pos_order, reference_id=order.id) ===\n";
if (Schema::hasTable('jurnal')) {
    $jurnal = DB::table('jurnal')
        ->where('reference_type', 'pos_order')
        ->where('reference_id', $order->id)
        ->get();
    echo 'count: ' . $jurnal->count() . "\n";
    foreach ($jurnal as $j) {
        echo sprintf(
            "  %s | %s | debit=%s kredit=%s | coa_d=%s coa_k=%s | status=%s\n",
            $j->no_jurnal,
            $j->tanggal,
            fmt($j->jumlah_debit),
            fmt($j->jumlah_kredit),
            $j->coa_debit_id,
            $j->coa_kredit_id,
            $j->status
        );
    }
}

echo "\n=== JURNAL GLOBAL ===\n";
if (Schema::hasTable('jurnal_global')) {
    $jg = DB::table('jurnal_global')
        ->where('reference_type', 'pos_order')
        ->where('reference_id', $order->id)
        ->get();
    echo 'count: ' . $jg->count() . "\n";
    foreach ($jg as $j) {
        echo sprintf(
            "  %s | %s | debit=%s kredit=%s\n",
            $j->no_jurnal,
            $j->tanggal,
            fmt($j->jumlah_debit),
            fmt($j->jumlah_kredit)
        );
    }
}

echo "\n=== Decode payment id timestamps ===\n";
foreach ($payIds as $id) {
    $tsPart = preg_replace('/[a-z0-9]{5}$/i', '', $id);
    $ms = intval($tsPart, 36);
    echo "  {$id} ts_part={$tsPart} ms={$ms} iso=" . date('Y-m-d H:i:s', (int) floor($ms / 1000)) . "\n";
}

echo "\n=== Nearby paid_numbers same outlet/day ===\n";
$near = DB::table('orders')
    ->where('kode_outlet', $order->kode_outlet)
    ->whereDate('created_at', '2026-08-28')
    ->where('paid_number', 'like', 'JTFCL260811%')
    ->orderBy('paid_number')
    ->get(['paid_number', 'id', 'grand_total', 'status', 'created_at', 'updated_at']);
foreach ($near as $n) {
    $mark = $n->paid_number === $paid ? ' <==' : '';
    echo sprintf("  %s | %s | GT %s | %s | created %s updated %s%s\n",
        $n->paid_number, $n->status, fmt($n->grand_total),
        substr($n->id, 0, 12),
        $n->created_at, $n->updated_at, $mark
    );
}
