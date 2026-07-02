<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FloorOrderItemPriceResolver;
use Illuminate\Support\Facades\DB;

$itemId = 53111;
$item = DB::table('items')->where('id', $itemId)->first();
if (! $item) {
    echo "Onion not found\n";
    exit(1);
}

echo "=== Master item bernama Onion ===\n";
$onionMasters = DB::table('items')
    ->whereRaw('LOWER(TRIM(name)) = ?', ['onion'])
    ->select('id', 'name', 'small_conversion_qty', 'medium_conversion_qty')
    ->orderBy('id')
    ->get();
foreach ($onionMasters as $om) {
    echo "id={$om->id} name={$om->name} small_conv={$om->small_conversion_qty} medium_conv={$om->medium_conversion_qty}\n";
}
echo "\n";

echo "=== Item: {$item->name} (id {$itemId}) ===\n";
echo "medium_conversion_qty: {$item->medium_conversion_qty}\n\n";

echo "=== item_prices ===\n";
$ips = DB::table('item_prices')->where('item_id', $itemId)->get();
foreach ($ips as $p) {
    echo json_encode($p, JSON_UNESCAPED_UNICODE) . "\n";
}

$resolved = FloorOrderItemPriceResolver::resolveLineUnitPrice($itemId, 'Kilogram');
echo "\nResolved Kilogram (medium): " . number_format($resolved, 0, '.', ',') . "\n";

echo "\n=== FO price distribution (Jun 2026) ===\n";
$fo = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $itemId)
    ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
    ->selectRaw('ffoi.price, count(*) as cnt')
    ->groupBy('ffoi.price')
    ->orderByDesc('cnt')
    ->get();
foreach ($fo as $f) {
    echo "price={$f->price} count={$f->cnt}\n";
}

echo "\n=== Sample mismatched FO rows (price != {$resolved}) ===\n";
$rows = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('ffoi.item_id', $itemId)
    ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
    ->where('ffoi.price', '!=', $resolved)
    ->select('ffoi.id', 'ffo.order_number', 'ffo.tanggal', 'o.nama_outlet', 'ffoi.qty', 'ffoi.price', 'ffo.status', 'ffo.fo_mode')
    ->orderByDesc('ffo.tanggal')
    ->limit(15)
    ->get();
foreach ($rows as $r) {
    echo "{$r->tanggal} {$r->order_number} {$r->nama_outlet} qty={$r->qty} price={$r->price} status={$r->status} mode={$r->fo_mode}\n";
}

echo "\nTotal mismatched: " . DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->where('ffoi.item_id', $itemId)
    ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
    ->where('ffoi.price', '!=', $resolved)
    ->count() . "\n";

echo "\n=== Simulasi baris report FJ detail (SMB, Jun 2026) ===\n";
$reportRow = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', 'Justus Steakhouse SMB')
    ->whereBetween('gr.receive_date', ['2026-06-01', '2026-06-30'])
    ->where('it.id', $itemId)
    ->selectRaw('SUM(i.received_qty) as received_qty, AVG(COALESCE(fo.price,0)) as avg_price, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal, COUNT(*) as joined_rows')
    ->first();
if ($reportRow) {
    echo 'received_qty=' . $reportRow->received_qty
        . ' avg_price=' . $reportRow->avg_price
        . ' subtotal=' . $reportRow->subtotal
        . ' joined_rows=' . $reportRow->joined_rows . "\n";
}

echo "\n=== Cek duplikasi Onion per floor_order (SMB, Jun 2026) ===\n";
$dupes = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('o.nama_outlet', 'Justus Steakhouse SMB')
    ->where('ffoi.item_id', $itemId)
    ->whereBetween('ffo.tanggal', ['2026-06-01', '2026-06-30'])
    ->selectRaw('ffoi.floor_order_id, ffo.order_number, ffo.tanggal, COUNT(*) as rows_cnt, SUM(ffoi.qty) as qty_sum, GROUP_CONCAT(ffoi.price ORDER BY ffoi.id SEPARATOR ",") as prices')
    ->groupBy('ffoi.floor_order_id', 'ffo.order_number', 'ffo.tanggal')
    ->havingRaw('COUNT(*) > 1')
    ->orderBy('ffo.tanggal')
    ->get();
if ($dupes->isEmpty()) {
    echo "Tidak ada duplikasi.\n";
} else {
    foreach ($dupes as $d) {
        echo "{$d->tanggal} {$d->order_number} floor_order_id={$d->floor_order_id} rows={$d->rows_cnt} qty_sum={$d->qty_sum} prices={$d->prices}\n";
    }
}

echo "\n=== Cek FO Onion dengan harga 169.040 (all time) ===\n";
$bad169 = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('ffoi.item_id', $itemId)
    ->where('ffoi.price', 169040)
    ->select('ffo.tanggal', 'ffo.order_number', 'o.nama_outlet', 'ffoi.qty', 'ffoi.price', 'ffo.fo_mode', 'ffo.status')
    ->orderByDesc('ffo.tanggal')
    ->limit(20)
    ->get();
echo 'count=' . $bad169->count() . "\n";
foreach ($bad169 as $b) {
    echo "{$b->tanggal} {$b->order_number} {$b->nama_outlet} qty={$b->qty} price={$b->price} mode={$b->fo_mode} status={$b->status}\n";
}

echo "\n=== Duplikasi Onion per floor_order (all time, semua outlet) ===\n";
$allDupes = DB::table('food_floor_order_items as ffoi')
    ->join('food_floor_orders as ffo', 'ffo.id', '=', 'ffoi.floor_order_id')
    ->join('tbl_data_outlet as o', 'o.id_outlet', '=', 'ffo.id_outlet')
    ->where('ffoi.item_id', $itemId)
    ->selectRaw("ffoi.floor_order_id, ffo.tanggal, ffo.order_number, o.nama_outlet, ffo.fo_mode, ffo.status, COUNT(*) as rows_cnt, SUM(ffoi.qty) as qty_sum, GROUP_CONCAT(ffoi.price ORDER BY ffoi.id SEPARATOR ',') as prices")
    ->groupBy('ffoi.floor_order_id', 'ffo.tanggal', 'ffo.order_number', 'o.nama_outlet', 'ffo.fo_mode', 'ffo.status')
    ->havingRaw('COUNT(*) > 1')
    ->orderByDesc('ffo.tanggal')
    ->limit(50)
    ->get();
echo 'count=' . $allDupes->count() . "\n";
foreach ($allDupes as $d) {
    echo "{$d->tanggal} {$d->order_number} {$d->nama_outlet} rows={$d->rows_cnt} qty_sum={$d->qty_sum} prices={$d->prices} mode={$d->fo_mode} status={$d->status}\n";
}

echo "\n=== Deteksi agregat report Onion dengan harga tidak wajar (> 50.000) ===\n";
$weird = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')
            ->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->where('it.id', $itemId)
    ->whereNull('gr.deleted_at')
    ->selectRaw("o.nama_outlet, DATE_FORMAT(gr.receive_date, '%Y-%m') as ym, SUM(i.received_qty) as received_qty, AVG(COALESCE(fo.price,0)) as avg_price, SUM(i.received_qty * COALESCE(fo.price,0)) as subtotal, COUNT(*) as joined_rows")
    ->groupBy('o.nama_outlet', DB::raw("DATE_FORMAT(gr.receive_date, '%Y-%m')"))
    ->havingRaw('AVG(COALESCE(fo.price,0)) > 50000')
    ->orderByDesc(DB::raw("DATE_FORMAT(gr.receive_date, '%Y-%m')"))
    ->limit(30)
    ->get();
echo 'count=' . $weird->count() . "\n";
foreach ($weird as $w) {
    echo "{$w->ym} {$w->nama_outlet} qty={$w->received_qty} avg_price={$w->avg_price} subtotal={$w->subtotal} joined_rows={$w->joined_rows}\n";
}

echo "\n=== Cek GR Serial Onion (SMB, Jun 2026) ===\n";
$serialRows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->where('it.id', $itemId)
    ->where('o.nama_outlet', 'Justus Steakhouse SMB')
    ->whereBetween('h.receive_date', ['2026-06-01', '2026-06-30'])
    ->selectRaw("h.receive_date, h.id as header_id, COALESCE(u.name,'-') as unit_name, si.qty, si.cost_small, (CASE WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0) * COALESCE(it.small_conversion_qty,1) * COALESCE(it.medium_conversion_qty,1) WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0) * COALESCE(it.small_conversion_qty,1) ELSE COALESCE(si.cost_small,0) END) as effective_price")
    ->orderBy('h.receive_date')
    ->limit(20)
    ->get();
echo 'count=' . $serialRows->count() . "\n";
foreach ($serialRows as $s) {
    echo "{$s->receive_date} header={$s->header_id} unit={$s->unit_name} qty={$s->qty} cost_small={$s->cost_small} effective_price={$s->effective_price}\n";
}
