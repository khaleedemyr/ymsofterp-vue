<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$delta = 7486537.0;
$serialPrice = "COALESCE(si.unit_price, CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)*COALESCE(it.medium_conversion_qty,1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)
    ELSE COALESCE(si.cost_small,0) END)";

$rows = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '=', '2026-11-26')
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->groupBy('h.id', 'h.number', 'h.created_at', 'u.nama_lengkap')
    ->orderBy('h.created_at')
    ->select(
        'h.number',
        'h.created_at',
        'u.nama_lengkap as gr_by',
        DB::raw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
    )
    ->get();

echo "Ampera MS GSR by header on 26 Nov:\n";
$running = 480305693.0; // today
// Simulate: if yellow = today minus GRs created after some cutoff on 26
foreach ($rows as $r) {
    echo "{$r->number} | {$r->created_at} | {$r->gr_by} | Rp ".number_format((float)$r->amount,0,',','.')."\n";
}

echo "\nFind cutoff where removing GSR created AFTER time ≈ yellow:\n";
$today = 480305693.0;
$amounts = $rows->map(fn ($r) => [(string)$r->created_at, (float)$r->amount, $r->number])->all();
// unique times
$times = $rows->pluck('created_at')->unique()->values();
foreach ($times as $t) {
    $after = $rows->filter(fn ($r) => (string)$r->created_at > (string)$t)->sum(fn ($r) => (float)$r->amount);
    // also try >= next
    $removed = $rows->filter(fn ($r) => (string)$r->created_at >= (string)$t)->sum(fn ($r) => (float)$r->amount);
    $valExclAfter = $today - $rows->filter(fn ($r) => (string)$r->created_at > (string)$t)->sum(fn ($r) => (float)$r->amount);
    $valExclFrom = $today - $removed;
    $m1 = abs($valExclAfter - 472819156) < 2 ? ' << YELLOW (excl after)' : '';
    $m2 = abs($valExclFrom - 472819156) < 2 ? ' << YELLOW (excl from)' : '';
    if ($m1 || $m2 || true) {
        echo "cutoff {$t}: exclAfter=".number_format($valExclAfter,0,',','.')."{$m1} | exclFrom=".number_format($valExclFrom,0,',','.')."{$m2}\n";
    }
}

// Maybe yellow includes food+serial Nov1-25 + only part of 26
// Or yellow used created_at filter somehow? Unlikely.
// Try: Nov1-26 but exclude GSR created on 26 after noon
$candidates = ['2026-11-26 10:00:00','2026-11-26 11:00:00','2026-11-26 12:00:00','2026-11-26 13:00:00','2026-11-26 14:00:00','2026-11-26 15:00:00','2026-11-26 16:00:00','2026-11-26 17:00:00'];
echo "\nToday minus GSR recv=26 created after T:\n";
foreach ($candidates as $t) {
    $rm = $rows->filter(fn ($r) => (string)$r->created_at > $t)->sum(fn ($r) => (float)$r->amount);
    $val = $today - $rm;
    $m = abs($val - 472819156) < 2 ? ' << YELLOW' : '';
    echo "  after {$t}: removed=".number_format($rm,0,',','.')." remain=".number_format($val,0,',','.')."{$m}\n";
}

// Food GR on 26 for Ampera MS?
$foodPrice = 'COALESCE(i.unit_price, fo.price, 0)';
$food26 = DB::table('outlet_food_good_receives as gr')
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
    ->whereDate('gr.receive_date', '=', '2026-11-26')
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
    ->value('amount');
echo "\nFood GR Ampera MS recv=26: Rp ".number_format((float)$food26,0,',','.')."\n";

// Maybe yellow is Nov1-26 BUT as of DB state? Or includes Chemical wrongly?
// Check yellow total outlet Main Store 5611415693 vs today 5636002200 delta
echo "\nTOTAL OUTLET Main Store yellow 5.611.415.693 today 5.636.002.200 delta=".number_format(5636002200-5611415693,0,',','.')."\n";

// Sum all outlets MS Nov1-25 vs Nov1-26
function allOutletMs($from, $to, $serialPrice, $foodPrice) {
    $serial = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->whereNull('h.deleted_at')
        ->where('o.is_outlet', 1)
        ->whereDate('h.receive_date', '>=', $from)
        ->whereDate('h.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
        ->value('amount');
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
        ->where('o.is_outlet', 1)
        ->whereDate('gr.receive_date', '>=', $from)
        ->whereDate('gr.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
        ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
        ->value('amount');
    return (float)$serial + (float)$food;
}

$foodPrice = 'COALESCE(i.unit_price, fo.price, 0)';
foreach (['2026-11-25','2026-11-26'] as $to) {
    $tot = allOutletMs('2026-11-01', $to, $serialPrice, $foodPrice);
    $m = abs($tot - 5611415693) < 5 ? ' << YELLOW MS TOTAL' : (abs($tot - 5636002200) < 5 ? ' << TODAY MS TOTAL' : '');
    echo "All-outlet MS 01..{$to}: ".number_format($tot,0,',','.')."{$m}\n";
}
