<?php
/**
 * Detail retail sales yang muncul di Outlet Payment tapi tidak di Rekap FJ.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outletId = (int) ($argv[1] ?? 0);
$date = $argv[2] ?? '2026-06-15';
if ($outletId <= 0) {
    fwrite(STDERR, "Usage: php scripts/trace_retail_in_outlet_payment.php OUTLET_ID DATE\n");
    exit(1);
}

$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
echo "Outlet: [{$outletId}] {$outlet->nama_outlet} | {$date}\n\n";

$rows = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
    ->where('c.id_outlet', (string) $outletId)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereNull('op.id')
    ->whereDate('rws.created_at', '>=', $date)
    ->whereDate('rws.created_at', '<=', $date)
    ->select(
        'rws.id',
        'rws.number',
        'rws.created_at',
        'rws.total_amount',
        'rws.status',
        'w.name as warehouse_name',
        'c.name as customer_name'
    )
    ->orderBy('rws.created_at')
    ->get();

if ($rows->isEmpty()) {
    echo "Tidak ada retail unpaid pada tanggal ini.\n";
    exit(0);
}

$total = 0.0;
foreach ($rows as $r) {
    $total += (float) $r->total_amount;
    echo "  #{$r->id} {$r->number} | {$r->created_at} | Rp " . number_format((float) $r->total_amount, 2) . " | WH: {$r->warehouse_name} | {$r->customer_name}\n";

    $items = DB::table('retail_warehouse_sale_items as ri')
        ->join('items as it', 'ri.item_id', '=', 'it.id')
        ->where('ri.retail_warehouse_sale_id', $r->id)
        ->select('it.name', 'ri.qty', 'ri.price', 'ri.subtotal')
        ->get();
    foreach ($items as $it) {
        echo "      - {$it->name} | qty {$it->qty} x " . number_format((float) $it->price, 2) . " = " . number_format((float) $it->subtotal, 2) . "\n";
    }
    echo "\n";
}

echo "TOTAL RETAIL: Rp " . number_format($total, 2) . "\n";
