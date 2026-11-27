<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$toDate = '2026-11-25';
$after = '2026-11-26 00:00:00';

echo "=== Non-GR causes for Rekap FJ drift (recv_date <= {$toDate}, changes after {$after}) ===\n\n";

// FO prices updated after 26, linked to Food GR recv<=25
$foPriceChanged = DB::table('food_floor_order_items as fo')
    ->join('delivery_orders as do', 'do.floor_order_id', '=', 'fo.floor_order_id')
    ->join('outlet_food_good_receives as gr', 'gr.delivery_order_id', '=', 'do.id')
    ->join('outlet_food_good_receive_items as gri', function ($j) {
        $j->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
            ->on('gri.item_id', '=', 'fo.item_id');
    })
    ->whereNull('gr.deleted_at')
    ->whereDate('gr.receive_date', '<=', $toDate)
    ->where('fo.updated_at', '>=', $after)
    ->distinct('fo.id')
    ->count('fo.id');
echo "1) FO item price rows updated >=26 linked to Food GR recv<=25: {$foPriceChanged}\n";

// Food GR lines still falling back to FO (unit_price null) - already know 0 globally, confirm in range
$nullUpFood = 0;
if (Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')) {
    $nullUpFood = DB::table('outlet_food_good_receive_items as gri')
        ->join('outlet_food_good_receives as gr', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
        ->whereNull('gr.deleted_at')
        ->whereDate('gr.receive_date', '<=', $toDate)
        ->whereNull('gri.unit_price')
        ->count();
}
echo "2) Food GR lines recv<=25 with NULL unit_price: {$nullUpFood}\n";

$nullUpSerial = 0;
if (Schema::hasColumn('outlet_serial_receive_items', 'unit_price')) {
    $nullUpSerial = DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
        ->whereNull('h.deleted_at')
        ->whereDate('h.receive_date', '<=', $toDate)
        ->whereNull('si.unit_price')
        ->count();
}
echo "3) Serial GR lines recv<=25 with NULL unit_price: {$nullUpSerial}\n";

// Soft-deleted GR after 26 with recv<=25
$foodDel = DB::table('outlet_food_good_receives')
    ->whereNotNull('deleted_at')
    ->where('deleted_at', '>=', $after)
    ->whereDate('receive_date', '<=', $toDate)
    ->count();
$serialDel = DB::table('outlet_serial_receive_headers')
    ->whereNotNull('deleted_at')
    ->where('deleted_at', '>=', $after)
    ->whereDate('receive_date', '<=', $toDate)
    ->count();
echo "4) Soft-deleted after 26, recv<=25 — Food: {$foodDel}, Serial: {$serialDel}\n";

// Item master remapping after 26
$itemWh = DB::table('items')->where('updated_at', '>=', $after)->whereNotNull('warehouse_division_id')->count();
$itemSc = DB::table('items')->where('updated_at', '>=', $after)->whereNotNull('sub_category_id')->count();
echo "5) Items updated >=26 (any): warehouse/subcat touch count proxy={$itemWh} updated rows (items.updated_at>=26)=".DB::table('items')->where('updated_at', '>=', $after)->count()."\n";

// Compare Ampera Main Store FULL rekap formula (food+serial) recv<=25
// Food
$foodPrice = Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')
    ? 'COALESCE(i.unit_price, fo.price, 0)'
    : 'COALESCE(fo.price, 0)';

$foodAmt = DB::table('outlet_food_good_receives as gr')
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
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('gr.receive_date', '<=', $toDate)
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
    ->value('amount');

$serialPrice = Schema::hasColumn('outlet_serial_receive_items', 'unit_price')
    ? "COALESCE(si.unit_price, CASE
        WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)*COALESCE(it.medium_conversion_qty,1)
        WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)
        ELSE COALESCE(si.cost_small,0) END)"
    : 'COALESCE(si.cost_small,0)';

$serialAmt = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '<=', $toDate)
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
    ->value('amount');

echo "\n6) Ampera Main Store NOW (recv<=25): Food=".number_format((float)$foodAmt,0,',','.')
    .' Serial='.number_format((float)$serialAmt,0,',','.')
    .' Total='.number_format((float)$foodAmt+(float)$serialAmt,0,',','.')."\n";
echo "   Yellow Ampera Main Store (from sheet): 472.819.156\n";
echo "   Today Ampera Main Store (from sheet): 480.305.693\n";

// Check if yellow/today match ANY common filter end dates for Ampera MS
foreach (['2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27'] as $end) {
    $s = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->whereNull('h.deleted_at')
        ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
        ->whereDate('h.receive_date', '<=', $end)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
        ->value('amount');
    $f = DB::table('outlet_food_good_receives as gr')
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
        ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
        ->whereDate('gr.receive_date', '<=', $end)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
        ->value('amount');
    $tot = (float)$s + (float)$f;
    echo "   to={$end}: Food=".number_format((float)$f,0,',','.').' Serial='.number_format((float)$s,0,',','.').' Total='.number_format($tot,0,',','.')."\n";
}

// Also try month Nov only from 2026-11-01
echo "\n7) Same with from=2026-11-01:\n";
foreach (['2026-11-25', '2026-11-26', '2026-11-27'] as $end) {
    $s = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->whereNull('h.deleted_at')
        ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
        ->whereDate('h.receive_date', '>=', '2026-11-01')
        ->whereDate('h.receive_date', '<=', $end)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
        ->value('amount');
    $f = DB::table('outlet_food_good_receives as gr')
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
        ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
        ->whereDate('gr.receive_date', '>=', '2026-11-01')
        ->whereDate('gr.receive_date', '<=', $end)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
        ->value('amount');
    $tot = (float)$s + (float)$f;
    $mark = '';
    if (abs($tot - 472819156) < 1) $mark = ' << MATCH YELLOW';
    if (abs($tot - 480305693) < 1) $mark = ' << MATCH TODAY';
    echo "   01..{$end}: Total=".number_format($tot,0,',','.')."{$mark}\n";
}
