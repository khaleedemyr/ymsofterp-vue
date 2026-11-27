<?php

/**
 * Kedua generate pakai range 1–26 Nov.
 * Yellow = generate 26 Nov. Hari ini = generate 27 Nov.
 * Cari GR (Serial+Food) yang receive_date masih di 1..26
 * tapi baru di-create setelah cutoff generate kemarin.
 */

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$from = '2026-11-01';
$to = '2026-11-26';
$yellowAmpera = 472819156.0;
$todayAmpera = 480305693.0;
$deltaTarget = $todayAmpera - $yellowAmpera; // 7.486.537

$serialPrice = Schema::hasColumn('outlet_serial_receive_items', 'unit_price')
    ? "COALESCE(si.unit_price, CASE
        WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)*COALESCE(it.medium_conversion_qty,1)
        WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small,0)*COALESCE(it.small_conversion_qty,1)
        ELSE COALESCE(si.cost_small,0) END)"
    : 'COALESCE(si.cost_small,0)';

$foodPrice = Schema::hasColumn('outlet_food_good_receive_items', 'unit_price')
    ? 'COALESCE(i.unit_price, fo.price, 0)'
    : 'COALESCE(fo.price, 0)';

$bucketExpr = "CASE
    WHEN w.name IN ('MK1 Hot Kitchen','MK2 Cold Kitchen') THEN 'Main Kitchen'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'CHEMICAL' THEN 'Chemical'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'STATIONARY' THEN 'Stationary'
    WHEN UPPER(w.name) = 'MAIN STORE' AND UPPER(COALESCE(sc.name,'')) = 'MARKETING' THEN 'Marketing'
    WHEN UPPER(w.name) = 'MAIN STORE' THEN 'Main Store'
    ELSE CONCAT('Other: ', COALESCE(w.name,'-'))
END";

function amperaMainStoreAsOf($from, $to, $createdBefore, $serialPrice, $foodPrice)
{
    $serialQ = DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
        ->whereNull('h.deleted_at')
        ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
        ->whereDate('h.receive_date', '>=', $from)
        ->whereDate('h.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')");
    if ($createdBefore) {
        $serialQ->where('h.created_at', '<=', $createdBefore);
    }
    $serial = (float) $serialQ->selectRaw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")->value('amount');

    $foodQ = DB::table('outlet_food_good_receives as gr')
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
        ->whereDate('gr.receive_date', '>=', $from)
        ->whereDate('gr.receive_date', '<=', $to)
        ->whereRaw("UPPER(w.name)='MAIN STORE'")
        ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')");
    if ($createdBefore) {
        $foodQ->where('gr.created_at', '<=', $createdBefore);
    }
    $food = (float) $foodQ->selectRaw("ROUND(SUM(i.received_qty * {$foodPrice}), 2) as amount")->value('amount');

    return $serial + $food;
}

echo "Cari cutoff created_at di tgl 26 yang bikin Ampera MS = yellow {$yellowAmpera}\n";
$cutoffs = DB::table('outlet_serial_receive_headers as h')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '>=', $from)
    ->whereDate('h.receive_date', '<=', $to)
    ->whereDate('h.created_at', '=', '2026-11-26')
    ->orderBy('h.created_at')
    ->pluck('h.created_at')
    ->unique()
    ->values();

$matchedCutoff = null;
foreach ($cutoffs as $c) {
    $val = amperaMainStoreAsOf($from, $to, (string) $c, $serialPrice, $foodPrice);
    if (abs($val - $yellowAmpera) < 2) {
        echo "MATCH yellow at created_at <= {$c} => ".number_format($val, 0, ',', '.')."\n";
        $matchedCutoff = (string) $c;
        break;
    }
}
if (! $matchedCutoff) {
    // also try hourly
    echo "No exact match on Ampera GSR timestamps. Trying hourly...\n";
    for ($h = 8; $h <= 23; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $c = sprintf('2026-11-26 %02d:%02d:00', $h, $m);
            $val = amperaMainStoreAsOf($from, $to, $c, $serialPrice, $foodPrice);
            if (abs($val - $yellowAmpera) < 2) {
                echo "MATCH yellow at created_at <= {$c} => ".number_format($val, 0, ',', '.')."\n";
                $matchedCutoff = $c;
                break 2;
            }
        }
    }
}

if (! $matchedCutoff) {
    // Show nearest
    $best = null;
    $bestDiff = PHP_FLOAT_MAX;
    foreach ($cutoffs as $c) {
        $val = amperaMainStoreAsOf($from, $to, (string) $c, $serialPrice, $foodPrice);
        $diff = abs($val - $yellowAmpera);
        if ($diff < $bestDiff) {
            $bestDiff = $diff;
            $best = [(string) $c, $val];
        }
    }
    echo 'Nearest: cutoff='.($best[0] ?? '-').' val='.number_format($best[1] ?? 0, 0, ',', '.').' diff='.number_format($bestDiff, 0, ',', '.')."\n";
    echo "Today full: ".number_format(amperaMainStoreAsOf($from, $to, null, $serialPrice, $foodPrice), 0, ',', '.')."\n";
    // Use end of Nov 26 00:00 as fallback? Better use: GRs created after yellow day start that are in range - actually list ALL created after first GSR on 26 that contribute to delta
    $matchedCutoff = '2026-11-26 12:00:00'; // provisional for listing; refine below
    echo "Using provisional cutoff {$matchedCutoff} for listing (will still list all created > that with recv in range)\n";
}

// If no match, find combination of late GSR on 26 that sum to delta
echo "\nDelta target Ampera MS: ".number_format($deltaTarget, 0, ',', '.')."\n";

$lateHeaders = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
    ->whereNull('h.deleted_at')
    ->where('o.nama_outlet', 'Justus Steakhouse Ampera - Kemang')
    ->whereDate('h.receive_date', '>=', $from)
    ->whereDate('h.receive_date', '<=', $to)
    ->where('h.created_at', '>=', '2026-11-26 00:00:00')
    ->whereRaw("UPPER(w.name)='MAIN STORE'")
    ->whereRaw("UPPER(COALESCE(sc.name,'')) NOT IN ('CHEMICAL','STATIONARY','MARKETING')")
    ->groupBy('h.id', 'h.number', 'h.receive_date', 'h.created_at', 'u.nama_lengkap')
    ->orderBy('h.created_at')
    ->select(
        'h.id',
        'h.number',
        'h.receive_date',
        'h.created_at',
        'u.nama_lengkap as gr_by',
        DB::raw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
    )
    ->get();

echo "Ampera MS headers created on/after 26 with recv in 1-26:\n";
$run = 0;
foreach ($lateHeaders as $r) {
    $run += (float) $r->amount;
    $remain = $todayAmpera - $run; // if yellow=today minus all from start of these? wrong
    echo "{$r->number} | recv {$r->receive_date} | created {$r->created_at} | {$r->gr_by} | Rp ".number_format((float)$r->amount,0,',','.')." | cumulative_from_first=".number_format($run,0,',','.')."\n";
}

// Reverse: today - cumulative from end
echo "\nIf yellow = today minus GSR created AFTER time T:\n";
$sorted = $lateHeaders->sortByDesc('created_at')->values();
$removed = 0;
$seenTimes = [];
foreach ($lateHeaders->sortBy('created_at')->values() as $r) {
    // value if we only include headers created_at <= this one
    $val = amperaMainStoreAsOf($from, $to, (string) $r->created_at, $serialPrice, $foodPrice);
    $mark = abs($val - $yellowAmpera) < 2 ? ' << YELLOW' : '';
    if ($mark) {
        echo "created_at <= {$r->created_at} ({$r->number}) => ".number_format($val,0,',','.')."{$mark}\n";
        $matchedCutoff = (string) $r->created_at;
    }
}

// Broader: all outlets - list GRs created after matched cutoff OR after 26 15:00 if unmatched
$cutoff = $matchedCutoff ?: '2026-11-26 15:00:00';
echo "\n=== Listing ALL outlets GR with recv in {$from}..{$to} AND created_at > {$cutoff} ===\n";

$serialRows = DB::table('outlet_serial_receive_headers as h')
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
    ->where('o.is_outlet', 1)
    ->whereDate('h.receive_date', '>=', $from)
    ->whereDate('h.receive_date', '<=', $to)
    ->where('h.created_at', '>', $cutoff)
    ->whereNotNull('w.name')
    ->groupBy(
        'h.id', 'h.number', 'h.receive_date', 'h.created_at', 'u.nama_lengkap', 'o.nama_outlet',
        'do.id', 'do.number', 'do.created_at', 'fo.order_number', 'fo.tanggal', 'fo.arrival_date', 'fo.created_at',
        DB::raw($bucketExpr), 'w.name'
    )
    ->orderBy('h.created_at')
    ->orderBy('o.nama_outlet')
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
        'fo.arrival_date as ro_arrival_date',
        'fo.created_at as ro_created_at',
        'w.name as warehouse',
        DB::raw('COUNT(si.id) as item_lines'),
        DB::raw("ROUND(SUM(si.qty * {$serialPrice}), 2) as amount")
    )
    ->get();

$foodRows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
    ->leftJoin('users as u', 'u.id', '=', 'gr.created_by')
    ->leftJoin('delivery_orders as do', 'do.id', '=', 'gr.delivery_order_id')
    ->leftJoin('food_floor_orders as ffo', 'ffo.id', '=', 'do.floor_order_id')
    // avoid FO multiply: use subquery price already in unit_price
    ->whereNull('gr.deleted_at')
    ->where('o.is_outlet', 1)
    ->whereDate('gr.receive_date', '>=', $from)
    ->whereDate('gr.receive_date', '<=', $to)
    ->where('gr.created_at', '>', $cutoff)
    ->whereNotNull('w.name')
    ->groupBy(
        'gr.id', 'gr.number', 'gr.receive_date', 'gr.created_at', 'u.nama_lengkap', 'o.nama_outlet',
        'do.number', 'do.created_at', 'ffo.order_number', 'ffo.tanggal', 'ffo.arrival_date', 'ffo.created_at',
        DB::raw($bucketExpr), 'w.name'
    )
    ->orderBy('gr.created_at')
    ->select(
        DB::raw("'FOOD' as gr_type"),
        'o.nama_outlet as outlet',
        DB::raw("{$bucketExpr} as rekap_column"),
        'gr.number as gr_number',
        'gr.receive_date as gr_receive_date',
        'gr.created_at as gr_created_at',
        'u.nama_lengkap as gr_by',
        'do.number as do_number',
        'do.created_at as do_created_at',
        'ffo.order_number as ro_number',
        'ffo.tanggal as ro_tanggal',
        'ffo.arrival_date as ro_arrival_date',
        'ffo.created_at as ro_created_at',
        'w.name as warehouse',
        DB::raw('COUNT(i.id) as item_lines'),
        DB::raw('ROUND(SUM(i.received_qty * COALESCE(i.unit_price, 0)), 2) as amount')
    )
    ->get();

$all = $serialRows->concat($foodRows)->sortBy('gr_created_at')->values();

$stamp = now()->format('Ymd_His');
// Format sama seperti rekap_fj_selisih_gr_full_* yang dibuka user
$path = __DIR__."/storage/app/rekap_fj_selisih_gr_full_{$stamp}.csv";
$fh = fopen($path, 'w');
fputcsv($fh, ['outlet','rekap_column','gr_number','gr_receive_date','gr_created_at','gr_by','do_number','do_created_at','ro_number','ro_tanggal','ro_arrival_date','ro_created_at','warehouse','item_lines','amount']);

echo "Cutoff used: {$cutoff}\n";
echo 'Rows: '.$all->count().' | Total amount Rp '.number_format($all->sum('amount'), 0, ',', '.')."\n\n";

foreach ($all as $r) {
    fputcsv($fh, [
        $r->outlet, $r->rekap_column, $r->gr_number, $r->gr_receive_date, $r->gr_created_at, $r->gr_by,
        $r->do_number, $r->do_created_at, $r->ro_number, $r->ro_tanggal, $r->ro_arrival_date, $r->ro_created_at,
        $r->warehouse, $r->item_lines, $r->amount,
    ]);
}
fclose($fh);

// Summary by outlet+column
$sum = [];
foreach ($all as $r) {
    $k = $r->outlet.'|'.$r->rekap_column;
    $sum[$k] = ($sum[$k] ?? 0) + (float) $r->amount;
}
ksort($sum);
echo "Ringkas outlet+kolom (kontribusi setelah cutoff):\n";
foreach ($sum as $k => $v) {
    if ($v <= 0) continue;
    [$o, $c] = explode('|', $k, 2);
    echo str_pad(mb_substr($o, 0, 36), 38).str_pad($c, 14).'Rp '.number_format($v, 0, ',', '.')."\n";
}
echo "\nCSV: {$path}\n";
