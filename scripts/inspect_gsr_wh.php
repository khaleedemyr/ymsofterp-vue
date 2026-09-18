<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo 'items: '.implode(', ', Schema::getColumnListing('outlet_serial_receive_items')).PHP_EOL;
echo 'headers: '.implode(', ', Schema::getColumnListing('outlet_serial_receive_headers')).PHP_EOL;

$outletId = 20;
$from = '2026-09-01';
$to = '2026-09-30';

$rows = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
    ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'si.warehouse_outlet_id')
    ->whereNull('h.deleted_at')
    ->where('h.status', 'completed')
    ->where('h.outlet_id', $outletId)
    ->whereBetween(DB::raw('DATE(h.receive_date)'), [$from, $to])
    ->selectRaw('COALESCE(wo.name, "(null)") as wh, COUNT(*) as cnt, SUM(si.qty) as qty')
    ->groupBy('wo.name')
    ->get();
foreach ($rows as $r) {
    echo "GSR wh={$r->wh} cnt={$r->cnt} qty={$r->qty}\n";
}

// also via FO
$rows2 = DB::table('outlet_serial_receive_items as si')
    ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
    ->leftJoin('delivery_orders as do', 'si.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
    ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
    ->whereNull('h.deleted_at')
    ->where('h.status', 'completed')
    ->where('h.outlet_id', $outletId)
    ->whereBetween(DB::raw('DATE(h.receive_date)'), [$from, $to])
    ->selectRaw('COALESCE(wo.name, "(null)") as wh, COUNT(*) as cnt')
    ->groupBy('wo.name')
    ->get();
echo "\nVia FO warehouse:\n";
foreach ($rows2 as $r) {
    echo "FO wh={$r->wh} cnt={$r->cnt}\n";
}
