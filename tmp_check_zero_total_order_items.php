<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fromDate = '2026-06-26';

$orders = DB::table('orders as o')
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

$orderIds = $orders->pluck('id')->all();

$itemAgg = [];
if (!empty($orderIds)) {
    $rows = DB::table('order_items')
        ->whereIn('order_id', $orderIds)
        ->selectRaw('order_id, COUNT(*) as item_count, SUM(COALESCE(subtotal,0)) as sum_subtotal, SUM(COALESCE(qty,0) * COALESCE(price,0)) as sum_qty_price')
        ->groupBy('order_id')
        ->get();

    foreach ($rows as $r) {
        $itemAgg[(string) $r->order_id] = [
            'item_count' => (int) $r->item_count,
            'sum_subtotal' => (float) ($r->sum_subtotal ?? 0),
            'sum_qty_price' => (float) ($r->sum_qty_price ?? 0),
        ];
    }
}

$out = [];
foreach ($orders as $o) {
    $k = (string) $o->id;
    $agg = $itemAgg[$k] ?? ['item_count' => 0, 'sum_subtotal' => 0.0, 'sum_qty_price' => 0.0];

    $discount = (float) ($o->discount ?? 0);
    $manual = (float) ($o->manual_discount_amount ?? 0);
    $discUsed = ($discount > 0 && $manual > 0) ? max($discount, $manual) : ($discount + $manual);

    $baseFromItems = $agg['sum_subtotal'] - $discUsed - (float) ($o->cashback ?? 0);
    $expService = round($baseFromItems * 0.05);
    $expPb1 = round(($baseFromItems + (float) ($o->service ?? 0)) * 0.10);
    $expGrand = $baseFromItems + (float) ($o->service ?? 0) + (float) ($o->pb1 ?? 0) + (float) ($o->commfee ?? 0) + (float) ($o->rounding ?? 0);

    $out[] = [
        'tanggal' => (string) $o->created_at,
        'outlet' => (string) ($o->nama_outlet ?? '-'),
        'kode_outlet' => (string) ($o->kode_outlet ?? '-'),
        'order_id' => (string) $o->id,
        'nomor_order' => (string) ($o->nomor ?? '-'),
        'paid_number' => (string) ($o->paid_number ?? '-'),
        'orders_total' => (float) ($o->total ?? 0),
        'item_count' => $agg['item_count'],
        'sum_order_items_subtotal' => $agg['sum_subtotal'],
        'sum_qty_x_price' => $agg['sum_qty_price'],
        'disc_used' => $discUsed,
        'cashback' => (float) ($o->cashback ?? 0),
        'service' => (float) ($o->service ?? 0),
        'service_expected_5pct_from_items_base' => $expService,
        'pb1' => (float) ($o->pb1 ?? 0),
        'pb1_expected_10pct_from_items_base_plus_service' => $expPb1,
        'grand_total' => (float) ($o->grand_total ?? 0),
        'grand_expected_from_items' => $expGrand,
        'grand_diff_vs_items_formula' => (float) ($o->grand_total ?? 0) - $expGrand,
    ];
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'reports';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$csv = $dir . DIRECTORY_SEPARATOR . 'orders_total_zero_check_order_items_from_2026-06-26.csv';
$fp = fopen($csv, 'w');
if ($fp !== false) {
    if (!empty($out)) {
        fputcsv($fp, array_keys($out[0]));
        foreach ($out as $r) {
            fputcsv($fp, $r);
        }
    }
    fclose($fp);
}

echo 'Total order total=0: ' . count($out) . PHP_EOL;
echo 'CSV: ' . $csv . PHP_EOL . PHP_EOL;

foreach ($out as $i => $r) {
    echo sprintf(
        "%d) %s | %s | %s | item_subtotal=%.2f | service=%.2f (exp %.2f) | pb1=%.2f (exp %.2f) | grand=%.2f | grand_exp=%.2f | diff=%.2f\n",
        $i + 1,
        $r['tanggal'],
        $r['outlet'],
        $r['nomor_order'],
        $r['sum_order_items_subtotal'],
        $r['service'],
        (float) $r['service_expected_5pct_from_items_base'],
        $r['pb1'],
        (float) $r['pb1_expected_10pct_from_items_base_plus_service'],
        $r['grand_total'],
        (float) $r['grand_expected_from_items'],
        (float) $r['grand_diff_vs_items_formula']
    );
}
