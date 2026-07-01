<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fromDate = '2026-06-26';

$rows = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->whereDate('o.created_at', '>=', $fromDate)
    ->select([
        'o.id',
        'o.created_at',
        'o.kode_outlet',
        'tdo.nama_outlet',
        'o.nomor',
        'o.paid_number',
        'o.status',
        'o.total',
        'o.discount',
        'o.manual_discount_amount',
        'o.cashback',
        'o.pb1',
        'o.service',
        'o.commfee',
        'o.rounding',
        'o.grand_total',
    ])
    ->orderBy('o.created_at')
    ->get();

$diffRows = [];

foreach ($rows as $row) {
    $total = (float) ($row->total ?? 0);
    $discount = (float) ($row->discount ?? 0);
    $manualDiscount = (float) ($row->manual_discount_amount ?? 0);
    $cashback = (float) ($row->cashback ?? 0);
    $pb1 = (float) ($row->pb1 ?? 0);
    $service = (float) ($row->service ?? 0);
    $commfee = (float) ($row->commfee ?? 0);
    $rounding = (float) ($row->rounding ?? 0);
    $grandTotal = (float) ($row->grand_total ?? 0);

    $discUsed = ($discount > 0 && $manualDiscount > 0)
        ? max($discount, $manualDiscount)
        : ($discount + $manualDiscount);

    $expectedSimple = $total - $discUsed + $pb1 + $service + $commfee;
    $diffSimple = $grandTotal - $expectedSimple;

    $expectedWithCashbackRounding = $expectedSimple - $cashback + $rounding;
    $diffWithCashbackRounding = $grandTotal - $expectedWithCashbackRounding;

    if (abs($diffSimple) > 0.5) {
        $diffRows[] = [
            'tanggal' => (string) $row->created_at,
            'outlet' => (string) ($row->nama_outlet ?? '-'),
            'kode_outlet' => (string) ($row->kode_outlet ?? '-'),
            'nomor_order' => (string) ($row->nomor ?? '-'),
            'paid_number' => (string) ($row->paid_number ?? '-'),
            'status' => (string) ($row->status ?? '-'),
            'total' => $total,
            'disc_used' => $discUsed,
            'pb1' => $pb1,
            'service' => $service,
            'commfee' => $commfee,
            'cashback' => $cashback,
            'rounding' => $rounding,
            'grand_total' => $grandTotal,
            'expected_simple' => $expectedSimple,
            'selisih_simple' => $diffSimple,
            'expected_with_cashback_rounding' => $expectedWithCashbackRounding,
            'selisih_with_cashback_rounding' => $diffWithCashbackRounding,
        ];
    }
}

$exportDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'reports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

$csvPath = $exportDir . DIRECTORY_SEPARATOR . 'orders_discrepancy_from_2026-06-26_after_all_fix.csv';
$fp = fopen($csvPath, 'w');
if ($fp === false) {
    fwrite(STDERR, "Gagal membuat file CSV: {$csvPath}\n");
    exit(1);
}

$headers = [
    'tanggal',
    'outlet',
    'kode_outlet',
    'nomor_order',
    'paid_number',
    'status',
    'total',
    'disc_used',
    'pb1',
    'service',
    'commfee',
    'cashback',
    'rounding',
    'grand_total',
    'expected_simple',
    'selisih_simple',
    'expected_with_cashback_rounding',
    'selisih_with_cashback_rounding',
];

fputcsv($fp, $headers);
foreach ($diffRows as $item) {
    fputcsv($fp, $item);
}
fclose($fp);

echo "Total order dicek: " . count($rows) . PHP_EOL;
echo "Order selisih (formula simple): " . count($diffRows) . PHP_EOL;
echo "CSV: {$csvPath}" . PHP_EOL;
