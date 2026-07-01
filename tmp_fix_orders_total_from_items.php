<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fromDate = '2026-06-26';

$candidates = DB::table('orders as o')
    ->leftJoin('tbl_data_outlet as tdo', 'o.kode_outlet', '=', 'tdo.qr_code')
    ->whereDate('o.created_at', '>=', $fromDate)
    ->where('o.status', 'paid')
    ->where('o.total', 0)
    ->select([
        'o.id',
        'o.created_at',
        'o.kode_outlet',
        'tdo.nama_outlet',
        'o.nomor',
        'o.paid_number',
        'o.total as total_before',
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

if ($candidates->isEmpty()) {
    echo "Tidak ada candidate order total=0.\n";
    exit(0);
}

$itemSums = DB::table('order_items')
    ->whereIn('order_id', $candidates->pluck('id')->all())
    ->selectRaw('order_id, SUM(COALESCE(subtotal,0)) as sum_subtotal')
    ->groupBy('order_id')
    ->pluck('sum_subtotal', 'order_id');

$rowsToFix = [];

foreach ($candidates as $row) {
    $sumSubtotal = (float) ($itemSums[$row->id] ?? 0);

    if ($sumSubtotal > 0.0) {
        $rowsToFix[] = [
            'order_id' => (string) $row->id,
            'tanggal' => (string) $row->created_at,
            'outlet' => (string) ($row->nama_outlet ?? '-'),
            'kode_outlet' => (string) ($row->kode_outlet ?? '-'),
            'nomor_order' => (string) ($row->nomor ?? '-'),
            'paid_number' => (string) ($row->paid_number ?? '-'),
            'total_before' => (float) $row->total_before,
            'total_after' => (float) $sumSubtotal,
            'discount' => (float) ($row->discount ?? 0),
            'manual_discount_amount' => (float) ($row->manual_discount_amount ?? 0),
            'cashback' => (float) ($row->cashback ?? 0),
            'service' => (float) ($row->service ?? 0),
            'pb1' => (float) ($row->pb1 ?? 0),
            'commfee' => (float) ($row->commfee ?? 0),
            'rounding' => (float) ($row->rounding ?? 0),
            'grand_total' => (float) ($row->grand_total ?? 0),
        ];
    }
}

if (empty($rowsToFix)) {
    echo "Tidak ada order total=0 yang punya order_items subtotal > 0.\n";
    exit(0);
}

DB::transaction(function () use ($rowsToFix): void {
    foreach ($rowsToFix as $row) {
        DB::table('orders')
            ->where('id', $row['order_id'])
            ->update([
                'total' => (int) round((float) $row['total_after']),
                'updated_at' => now(),
            ]);
    }
});

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'reports';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$csv = $dir . DIRECTORY_SEPARATOR . 'orders_total_fix_from_items_2026-06-26.csv';
$fp = fopen($csv, 'w');
if ($fp !== false) {
    fputcsv($fp, array_keys($rowsToFix[0]));
    foreach ($rowsToFix as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

echo 'Order diperbaiki: ' . count($rowsToFix) . PHP_EOL;
echo 'CSV hasil perbaikan: ' . $csv . PHP_EOL . PHP_EOL;

foreach ($rowsToFix as $idx => $row) {
    echo sprintf(
        "%d) %s | %s | %s | total: %.2f -> %.2f\n",
        $idx + 1,
        $row['tanggal'],
        $row['outlet'],
        $row['nomor_order'],
        (float) $row['total_before'],
        (float) $row['total_after']
    );
}
