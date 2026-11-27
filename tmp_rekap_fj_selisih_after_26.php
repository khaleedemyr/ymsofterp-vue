<?php

/**
 * Selisih Rekap FJ: filter asumsikan sampai 2026-11-25.
 * Cari GR (Serial + Food) yang receive_date <= 25
 * tapi created/updated setelah generate tgl 26 (created_at/updated_at >= 2026-11-26).
 */

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$toDate = '2026-11-25';
$afterPull = '2026-11-26 00:00:00'; // mulai hari generate pertama (kuning)

$focusOutlets = [
    'Justus Steak House Bintaro',
    'Justus Steak House Buah Batu',
    'Justus Steak House Cipete',
    'Justus Steak House Ciwalk',
    'Justus Steak House Dago',
    'Justus Steak House Jawa',
    'Justus Steak House Metro Indah Mall',
    'Justus Steak House Paskal 23',
    'Justus Steakhouse Ampera - Kemang',
    'Justus Steakhouse SMB',
    'Justus Steakhouse The Barn',
    'Tempayan Citarum',
];

$priceExpr = Schema::hasColumn('outlet_serial_receive_items', 'unit_price')
    ? "COALESCE(si.unit_price, CASE
        WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)*COALESCE(it.medium_conversion_qty,1)
        WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)
        ELSE COALESCE(si.cost_small,0) END)"
    : 'COALESCE(si.cost_small,0)';

$bucketExpr = "CASE
    WHEN w.name IN ('MK1 Hot Kitchen','MK2 Cold Kitchen') THEN 'Main Kitchen'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'CHEMICAL' THEN 'Chemical'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'STATIONARY' THEN 'Stationary'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'MARKETING' THEN 'Marketing'
    WHEN UPPER(w.name) = 'MAIN STORE' THEN 'Main Store'
    ELSE CONCAT('Other: ', COALESCE(w.name,'-'))
END";

echo "Asumsi filter Rekap: receive_date <= {$toDate}\n";
echo "Cari perubahan setelah: {$afterPull}\n\n";

// 1) Serial GR: receive_date <= 25, created setelah 26
$serialNew = DB::table('outlet_serial_receive_headers as h')
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
    ->whereDate('h.receive_date', '<=', $toDate)
    ->where('h.created_at', '>=', $afterPull)
    ->whereIn('o.nama_outlet', $focusOutlets)
    ->whereNotNull('w.name')
    ->groupBy(
        'h.id', 'h.number', 'h.receive_date', 'h.created_at', 'u.nama_lengkap', 'o.nama_outlet',
        'do.id', 'do.number', 'do.created_at',
        'fo.id', 'fo.order_number', 'fo.tanggal', 'fo.arrival_date', 'fo.created_at',
        DB::raw($bucketExpr), 'w.name'
    )
    ->orderBy('h.created_at')
    ->select(
        DB::raw("'SERIAL' as gr_type"),
        'o.nama_outlet as outlet',
        DB::raw("{$bucketExpr} as rekap_column"),
        'h.number as gr_number',
        'h.receive_date as gr_receive_date',
        'h.created_at as gr_created_at',
        'u.nama_lengkap as gr_by',
        DB::raw('COALESCE(do.number, MAX(si.delivery_order_number)) as do_number'),
        'do.created_at as do_created_at',
        'fo.order_number as ro_number',
        'fo.tanggal as ro_tanggal',
        'w.name as warehouse',
        DB::raw("ROUND(SUM(si.qty * {$priceExpr}), 2) as amount"),
        DB::raw("'created_after_26' as reason")
    )
    ->get();

echo "=== 1) GSR receive_date<={$toDate} tapi created >= 26 ===\n";
echo 'Count rows: '.$serialNew->count().' | Total Rp '.number_format($serialNew->sum('amount'), 0, ',', '.')."\n";
foreach ($serialNew->take(15) as $r) {
    echo "{$r->gr_number} | recv:{$r->gr_receive_date} | created:{$r->gr_created_at} | {$r->outlet} | {$r->rekap_column} | {$r->gr_by} | DO:{$r->do_number} | RO:{$r->ro_number} | Rp ".number_format((float)$r->amount,0,',','.')."\n";
}

// 2) Serial items updated after 26, header receive_date <= 25, header created before 26
$serialUpdated = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
    ->whereNull('h.deleted_at')
    ->whereDate('h.receive_date', '<=', $toDate)
    ->where('h.created_at', '<', $afterPull)
    ->where(function ($q) use ($afterPull) {
        $q->where('si.updated_at', '>=', $afterPull)
            ->orWhere('h.updated_at', '>=', $afterPull);
    })
    ->whereIn('o.nama_outlet', $focusOutlets)
    ->whereNotNull('w.name')
    ->groupBy('h.id', 'h.number', 'h.receive_date', 'h.created_at', 'h.updated_at', 'u.nama_lengkap', 'o.nama_outlet', DB::raw($bucketExpr))
    ->orderByDesc(DB::raw('MAX(si.updated_at)'))
    ->select(
        'h.number as gr_number',
        'h.receive_date',
        'h.created_at',
        'h.updated_at as header_updated_at',
        'u.nama_lengkap as gr_by',
        'o.nama_outlet as outlet',
        DB::raw("{$bucketExpr} as rekap_column"),
        DB::raw('MAX(si.updated_at) as item_updated_at'),
        DB::raw("ROUND(SUM(si.qty * {$priceExpr}), 2) as amount")
    )
    ->get();

echo "\n=== 2) GSR lama (created < 26, recv<=25) tapi updated >= 26 ===\n";
echo 'Count: '.$serialUpdated->count()."\n";
foreach ($serialUpdated->take(20) as $r) {
    echo "{$r->gr_number} | recv:{$r->receive_date} | created:{$r->created_at} | item_upd:{$r->item_updated_at} | {$r->outlet} | {$r->rekap_column} | Rp ".number_format((float)$r->amount,0,',','.')."\n";
}

// 3) Food GR same pattern
$foodPrice = Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')
    ? 'COALESCE(i.unit_price, fo.price, 0)'
    : 'COALESCE(fo.price, 0)';

$foodNew = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'gr.created_by')
    ->leftJoin('delivery_orders as do', 'do.id', '=', 'gr.delivery_order_id')
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->whereNull('gr.deleted_at')
    ->whereDate('gr.receive_date', '<=', $toDate)
    ->where('gr.created_at', '>=', $afterPull)
    ->whereIn('o.nama_outlet', $focusOutlets)
    ->whereNotNull('w.name')
    ->groupBy(
        'gr.id', 'gr.number', 'gr.receive_date', 'gr.created_at', 'u.nama_lengkap', 'o.nama_outlet',
        'do.number', 'do.created_at', 'ffo.order_number', 'ffo.tanggal',
        DB::raw($bucketExpr), 'w.name'
    )
    ->select(
        'gr.number as gr_number',
        'gr.receive_date',
        'gr.created_at',
        'u.nama_lengkap as gr_by',
        'o.nama_outlet as outlet',
        DB::raw("{$bucketExpr} as rekap_column"),
        'do.number as do_number',
        'ffo.order_number as ro_number',
        'ffo.tanggal as ro_tanggal',
        DB::raw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")
    )
    ->orderBy('gr.created_at')
    ->get();

echo "\n=== 3) Food GR receive_date<={$toDate} tapi created >= 26 ===\n";
echo 'Count: '.$foodNew->count().' | Total Rp '.number_format($foodNew->sum('amount'), 0, ',', '.')."\n";
foreach ($foodNew->take(15) as $r) {
    echo "{$r->gr_number} | recv:{$r->receive_date} | created:{$r->created_at} | {$r->outlet} | {$r->rekap_column} | {$r->gr_by} | DO:{$r->do_number} | RO:{$r->ro_number} | Rp ".number_format((float)$r->amount,0,',','.')."\n";
}

$foodUpdated = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->whereNull('gr.deleted_at')
    ->whereDate('gr.receive_date', '<=', $toDate)
    ->where('gr.created_at', '<', $afterPull)
    ->where(function ($q) use ($afterPull) {
        $q->where('i.updated_at', '>=', $afterPull)
            ->orWhere('gr.updated_at', '>=', $afterPull);
    })
    ->whereIn('o.nama_outlet', $focusOutlets)
    ->distinct()
    ->count('gr.id');

echo "\n=== 4) Food GR lama updated >= 26 (header count): {$foodUpdated}\n";

// 5) Compare Ampera Main Store amount for receive_date<=25 as of "logic":
//    - only headers created before 26 vs all headers with recv<=25
$amperaBefore = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '<=', $toDate)
    ->where('h.created_at', '<', $afterPull)
    ->whereRaw("UPPER(w.name) = 'MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->selectRaw("ROUND(SUM(si.qty * {$priceExpr}), 2) as amount")
    ->value('amount');

$amperaAll = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '<=', $toDate)
    ->whereRaw("UPPER(w.name) = 'MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->selectRaw("ROUND(SUM(si.qty * {$priceExpr}), 2) as amount")
    ->value('amount');

echo "\n=== Cek Ampera Main Store (SERIAL only, receive_date<=25) ===\n";
echo 'Created < 26: Rp '.number_format((float)$amperaBefore, 0, ',', '.')."\n";
echo 'All (termasuk created >=26): Rp '.number_format((float)$amperaAll, 0, ',', '.')."\n";
echo 'Delta: Rp '.number_format((float)$amperaAll - (float)$amperaBefore, 0, ',', '.')."\n";

$stamp = now()->format('Ymd_His');
$out = __DIR__."/storage/app/rekap_fj_selisih_recv_le_25_after_26_{$stamp}.csv";
$fh = fopen($out, 'w');
fputcsv($fh, ['gr_type','outlet','rekap_column','gr_number','gr_receive_date','gr_created_at','gr_by','do_number','do_created_at','ro_number','ro_tanggal','warehouse','amount','reason']);
foreach ($serialNew as $r) {
    fputcsv($fh, [
        'SERIAL', $r->outlet, $r->rekap_column, $r->gr_number, $r->gr_receive_date, $r->gr_created_at, $r->gr_by,
        $r->do_number, $r->do_created_at, $r->ro_number, $r->ro_tanggal, $r->warehouse, $r->amount, 'created_after_26_recv_le_25',
    ]);
}
foreach ($foodNew as $r) {
    fputcsv($fh, [
        'FOOD', $r->outlet, $r->rekap_column, $r->gr_number, $r->receive_date, $r->created_at, $r->gr_by,
        $r->do_number, null, $r->ro_number, $r->ro_tanggal, null, $r->amount, 'created_after_26_recv_le_25',
    ]);
}
fclose($fh);
echo "\nCSV: {$out}\n";
