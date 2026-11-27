<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$yellow = 472819156.0;
$today = 480305693.0;
$metroYellow = 161874835.0; // from first image
$metroToday = 162011251.0; // from second image

$foodPrice = Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')
    ? 'COALESCE(i.unit_price, fo.price, 0)'
    : 'COALESCE(fo.price, 0)';
$serialPrice = Schema::hasColumn('outlet_serial_receive_items', 'unit_price')
    ? "COALESCE(si.unit_price, CASE
        WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)*COALESCE(it.medium_conversion_qty,1)
        WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)
        ELSE COALESCE(si.cost_small,0) END)"
    : 'COALESCE(si.cost_small,0)';

function outletMainStore($outlet, $from, $to, $foodPrice, $serialPrice)
{
    $food = DB::table('outlet_food_good_receives as gr')
        ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
        ->join('items as it', 'i.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
        ->leftJoin('delivery_orders as do', 'do.id', '=', 'gr.delivery_order_id')
        ->leftJoin('food_floor_order_items as fo', function ($j) {
            $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
        })
        ->whereNull('gr.deleted_at')
        ->where('o.nama_outlet', $outlet)
        ->whereDate('gr.receive_date', '>=', $from)
        ->whereDate('gr.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
        ->value('amount');

    $serial = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->whereNull('h.deleted_at')
        ->where('o.nama_outlet', $outlet)
        ->whereDate('h.receive_date', '>=', $from)
        ->whereDate('h.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
        ->value('amount');

    return (float) $food + (float) $serial;
}

$ampera = 'Justus Steakhouse Ampera - Kemang';
$metro = 'Justus Steak House Metro Indah Mall';

echo "Scan Ampera MS for yellow {$yellow}:\n";
for ($d = 1; $d <= 27; $d++) {
    $to = sprintf('2026-11-%02d', $d);
    $tot = outletMainStore($ampera, '2026-11-01', $to, $foodPrice, $serialPrice);
    $mark = abs($tot - $yellow) < 1 ? ' << YELLOW' : (abs($tot - $today) < 1 ? ' << TODAY' : '');
    if ($mark || $d >= 24) {
        echo "  01..{$to}: ".number_format($tot, 0, ',', '.')."{$mark}\n";
    }
}

echo "\nDelta Ampera TODAY-YELLOW: ".number_format($today - $yellow, 0, ',', '.')."\n";
echo "Serial-only Ampera 26 only:\n";
$only26 = outletMainStore($ampera, '2026-11-26', '2026-11-26', $foodPrice, $serialPrice);
echo '  MS amount on recv=26: '.number_format($only26, 0, ',', '.')."\n";
echo '  Compare delta: '.number_format($today - $yellow, 0, ',', '.')."\n";

echo "\nMetro:\n";
foreach (['2026-11-25', '2026-11-26', '2026-11-27'] as $to) {
    $tot = outletMainStore($metro, '2026-11-01', $to, $foodPrice, $serialPrice);
    $mark = abs($tot - $metroYellow) < 1 ? ' << YELLOW' : (abs($tot - $metroToday) < 1 ? ' << TODAY' : '');
    echo "  01..{$to}: ".number_format($tot, 0, ',', '.')."{$mark}\n";
}

// List GSR Ampera receive_date=26 contributing to Main Store
$rows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
    ->leftJoin('delivery_orders as do', 'do.id', '=', 'si.delivery_order_id')
    ->leftJoin('food_floor_orders as fo', 'fo.id', '=', 'do.floor_order_id')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', $ampera)
    ->whereDate('h.receive_date', '=', '2026-11-26')
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->groupBy('h.id', 'h.number', 'h.receive_date', 'h.created_at', 'u.nama_lengkap', 'do.number', 'do.created_at', 'fo.order_number', 'fo.tanggal')
    ->orderBy('h.created_at')
    ->select(
        'h.number as gr_number',
        'h.receive_date',
        'h.created_at',
        'u.nama_lengkap as gr_by',
        'do.number as do_number',
        'do.created_at as do_created_at',
        'fo.order_number as ro_number',
        'fo.tanggal as ro_tanggal',
        DB::raw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
    )
    ->get();

echo "\nAmpera Main Store GSR receive_date=2026-11-26 (penyebab naik ke angka hari ini):\n";
$sum = 0;
foreach ($rows as $r) {
    $sum += (float) $r->amount;
    echo "{$r->gr_number} | created {$r->created_at} | by {$r->gr_by} | DO {$r->do_number} | RO {$r->ro_number} ({$r->ro_tanggal}) | Rp ".number_format((float)$r->amount,0,',','.')."\n";
}
echo 'SUM: Rp '.number_format($sum, 0, ',', '.')."\n";
