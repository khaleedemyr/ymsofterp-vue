<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RetailNonFood;

$number = 'RNF202606100008';
$row = RetailNonFood::withTrashed()->where('retail_number', $number)->first();

if (!$row) {
    echo "NOT FOUND in retail_non_food\n";
    exit(0);
}

$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $row->outlet_id)->value('nama_outlet');
$supplier = DB::table('suppliers')->where('id', $row->supplier_id)->value('name');

echo json_encode([
    'id' => $row->id,
    'retail_number' => $row->retail_number,
    'transaction_date' => $row->transaction_date?->format('Y-m-d'),
    'payment_method' => $row->payment_method,
    'status' => $row->status,
    'jurnal_created' => $row->jurnal_created,
    'outlet_id' => $row->outlet_id,
    'total_amount' => $row->total_amount,
    'deleted_at' => $row->deleted_at?->format('Y-m-d H:i:s'),
    'outlet_name' => $outlet,
    'supplier_name' => $supplier,
    'reason_not_in_payment_page' => $row->payment_method !== 'cash'
        ? 'Halaman Retail Non Food Payment hanya menampilkan payment_method = cash. Transaksi ini: ' . $row->payment_method
        : null,
], JSON_PRETTY_PRINT) . "\n";
