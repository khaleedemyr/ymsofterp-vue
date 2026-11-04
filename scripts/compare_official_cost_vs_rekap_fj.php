<?php
/**
 * Bandingkan Official Cost vs Rekap FJ (non-MCS) + Retail Food.
 *
 * Usage:
 *   php scripts/compare_official_cost_vs_rekap_fj.php --outlet-name="Alam Sutera" --date-from=2026-10-01 --date-to=2026-10-31
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$opts = getopt('', ['outlet-id::', 'outlet-name::', 'date-from::', 'date-to::']);
$dateFrom = $opts['date-from'] ?? '2026-10-01';
$dateTo = $opts['date-to'] ?? '2026-10-31';
$outletId = isset($opts['outlet-id']) ? (int) $opts['outlet-id'] : 0;
$outletName = $opts['outlet-name'] ?? 'Alam Sutera';

if ($outletId <= 0) {
    $matches = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%'.$outletName.'%')->get(['id_outlet', 'nama_outlet']);
    foreach ($matches as $o) {
        echo "  {$o->id_outlet} | {$o->nama_outlet}\n";
    }
    $outletId = (int) ($matches->first()->id_outlet ?? 0);
}
$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
if (!$outlet) {
    fwrite(STDERR, "Outlet not found\n");
    exit(1);
}

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$excluded = ['STATIONARY', 'MARKETING', 'CHEMICAL'];
$foodPrice = 'COALESCE(fo.price, 0)';
$serialPrice = '(CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)';
$hasGsr = Schema::hasTable('outlet_serial_receive_headers');

echo "============================================================\n";
echo " Official Cost vs Rekap FJ + Retail Food\n";
echo " Outlet : [{$outletId}] {$outlet->nama_outlet}\n";
echo " Periode: {$dateFrom} s/d {$dateTo}\n";
echo "============================================================\n\n";

// --- Official Cost components ---
$ocFood = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->where('gr.status', 'completed')
    ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
    ->sum(DB::raw("i.received_qty * {$foodPrice}"));

$ocGsr = 0.0;
if ($hasGsr) {
    $ocGsr = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->where('h.status', 'completed')
        ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
        ->sum(DB::raw("si.qty * ({$serialPrice})"));
}

$itemNameMap = DB::table('items as im')
    ->select(DB::raw('MIN(im.id) as item_id'), DB::raw('TRIM(im.name) as item_name_key'))
    ->groupBy(DB::raw('TRIM(im.name)'));

$ocRetail = (float) DB::table('retail_food as rf')
    ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
    ->joinSub($itemNameMap, 'map_item', function ($join) {
        $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
    })
    ->join('items as it', 'map_item.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->where('rf.outlet_id', $outletId)
    ->whereDate('rf.transaction_date', '>=', $dateFrom)
    ->whereDate('rf.transaction_date', '<=', $dateTo)
    ->where('rf.status', 'approved')
    ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
    ->sum('rfi.subtotal');

$ocTotal = $ocFood + $ocGsr + $ocRetail;

echo "--- OFFICIAL COST (Cost Report) ---\n";
echo "  Food GR completed non-MCS : {$fmt($ocFood)}\n";
echo "  GSR completed non-MCS     : {$fmt($ocGsr)}\n";
echo "  Retail Food mapped non-MCS: {$fmt($ocRetail)}\n";
echo "  TOTAL                     : {$fmt($ocTotal)}\n\n";

// --- Rekap FJ (all statuses, require warehouse, include MCS in line_total) ---
$rekapFoodAll = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNotNull('w.name')
    ->sum(DB::raw("i.received_qty * {$foodPrice}"));

$rekapFoodNonMcs = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNotNull('w.name')
    ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
    ->sum(DB::raw("i.received_qty * {$foodPrice}"));

$rekapFoodCompletedNonMcs = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->where('gr.status', 'completed')
    ->whereNotNull('w.name')
    ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
    ->sum(DB::raw("i.received_qty * {$foodPrice}"));

$rekapFoodNoWhNonMcsCompleted = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->where('gr.status', 'completed')
    ->whereNull('w.name')
    ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
    ->sum(DB::raw("i.received_qty * {$foodPrice}"));

$rekapGsrAll = 0.0;
$rekapGsrNonMcs = 0.0;
$rekapGsrCompletedNonMcs = 0.0;
$rekapGsrNoWhCompletedNonMcs = 0.0;
if ($hasGsr) {
    $rekapGsrAll = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNotNull('w.name')
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $rekapGsrNonMcs = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNotNull('w.name')
        ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $rekapGsrCompletedNonMcs = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->where('h.status', 'completed')
        ->whereNotNull('w.name')
        ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $rekapGsrNoWhCompletedNonMcs = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->where('h.status', 'completed')
        ->whereNull('w.name')
        ->whereRaw('UPPER(TRIM(sc.name)) NOT IN (?, ?, ?)', $excluded)
        ->sum(DB::raw("si.qty * ({$serialPrice})"));
}

$mcsFood = $rekapFoodAll - $rekapFoodNonMcs;
$mcsGsr = $rekapGsrAll - $rekapGsrNonMcs;
$rekapLine = $rekapFoodAll + $rekapGsrAll;
$rekapNonMcs = $rekapFoodNonMcs + $rekapGsrNonMcs;

echo "--- REKAP FJ (outlet GR+GSR) ---\n";
echo "  Food GR all (line)        : {$fmt($rekapFoodAll)}\n";
echo "  GSR all (line)            : {$fmt($rekapGsrAll)}\n";
echo "  LINE TOTAL               : {$fmt($rekapLine)}\n";
echo "  MCS Food (Chem/Stat/Mkt)  : {$fmt($mcsFood)}\n";
echo "  MCS GSR                   : {$fmt($mcsGsr)}\n";
echo "  LINE - MCS                : {$fmt($rekapNonMcs)}\n";
echo "  Food completed+WH+nonMCS  : {$fmt($rekapFoodCompletedNonMcs)}\n";
echo "  GSR completed+WH+nonMCS   : {$fmt($rekapGsrCompletedNonMcs)}\n";
echo "  Food completed NO WH      : {$fmt($rekapFoodNoWhNonMcsCompleted)}  <- Official YA, Rekap TIDAK\n";
echo "  GSR completed NO WH       : {$fmt($rekapGsrNoWhCompletedNonMcs)}  <- Official YA, Rekap TIDAK\n\n";

// Non-completed that Rekap includes
$rekapFoodNonCompletedNonMcs = $rekapFoodNonMcs - $rekapFoodCompletedNonMcs - $rekapFoodNoWhNonMcsCompleted;
// Actually completedNonMcs only has WH; nonMcs has WH all statuses.
// nonCompleted with WH = nonMcs - completedNonMcs
$foodNonCompletedWh = $rekapFoodNonMcs - $rekapFoodCompletedNonMcs;
$gsrNonCompletedWh = $rekapGsrNonMcs - $rekapGsrCompletedNonMcs;
echo "  Food non-completed (WH)   : {$fmt($foodNonCompletedWh)}  <- Rekap YA, Official TIDAK\n";
echo "  GSR non-completed (WH)    : {$fmt($gsrNonCompletedWh)}  <- Rekap YA, Official TIDAK\n\n";

// Retail Food variants
$rfHeader = (float) DB::table('retail_food')
    ->where('outlet_id', $outletId)
    ->whereDate('transaction_date', '>=', $dateFrom)
    ->whereDate('transaction_date', '<=', $dateTo)
    ->where('status', 'approved')
    ->sum('total_amount');

$rfAllItems = (float) DB::table('retail_food as rf')
    ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
    ->where('rf.outlet_id', $outletId)
    ->whereDate('rf.transaction_date', '>=', $dateFrom)
    ->whereDate('rf.transaction_date', '<=', $dateTo)
    ->where('rf.status', 'approved')
    ->sum('rfi.subtotal');

$rfUnmapped = (float) DB::table('retail_food as rf')
    ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
    ->leftJoinSub($itemNameMap, 'map_item', function ($join) {
        $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
    })
    ->where('rf.outlet_id', $outletId)
    ->whereDate('rf.transaction_date', '>=', $dateFrom)
    ->whereDate('rf.transaction_date', '<=', $dateTo)
    ->where('rf.status', 'approved')
    ->whereNull('map_item.item_id')
    ->sum('rfi.subtotal');

$rfMappedMcs = (float) DB::table('retail_food as rf')
    ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
    ->joinSub($itemNameMap, 'map_item', function ($join) {
        $join->on(DB::raw('TRIM(rfi.item_name)'), '=', DB::raw('map_item.item_name_key'));
    })
    ->join('items as it', 'map_item.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->where('rf.outlet_id', $outletId)
    ->whereDate('rf.transaction_date', '>=', $dateFrom)
    ->whereDate('rf.transaction_date', '<=', $dateTo)
    ->where('rf.status', 'approved')
    ->whereRaw('UPPER(TRIM(sc.name)) IN (?, ?, ?)', $excluded)
    ->sum('rfi.subtotal');

echo "--- RETAIL FOOD ---\n";
echo "  Header total_amount       : {$fmt($rfHeader)}  (biasa dipakai report RF)\n";
echo "  All item subtotals        : {$fmt($rfAllItems)}\n";
echo "  Unmapped item names       : {$fmt($rfUnmapped)}  <- Report RF YA, Official TIDAK\n";
echo "  Mapped MCS                : {$fmt($rfMappedMcs)}  <- Report RF YA, Official TIDAK\n";
echo "  Official Retail Food      : {$fmt($ocRetail)}\n\n";

$userSide = $rekapLine + $rfHeader;
$userSideAdj = $rekapNonMcs + $ocRetail;
$gap = $ocTotal - $userSide;
$gapAdj = $ocTotal - $userSideAdj;

echo "--- SELISIH ---\n";
echo "  User bandingkan: Rekap LINE + RF header = {$fmt($userSide)}\n";
echo "  Official Cost                         = {$fmt($ocTotal)}\n";
echo "  Selisih Official - (Rekap+RF header)  = {$fmt($gap)}\n\n";
echo "  Adjusted: (Rekap-MCS) + Official RF   = {$fmt($userSideAdj)}\n";
echo "  Selisih Official - adjusted           = {$fmt($gapAdj)}\n\n";

echo "Komponen penjelas (positif = Official lebih besar):\n";
echo "  + completed NO warehouse              : {$fmt($rekapFoodNoWhNonMcsCompleted + $rekapGsrNoWhCompletedNonMcs)}\n";
echo "  - non-completed di Rekap              : {$fmt(-($foodNonCompletedWh + $gsrNonCompletedWh))}\n";
echo "  - MCS di Rekap line                   : {$fmt(-($mcsFood + $mcsGsr))}\n";
echo "  - RF unmapped                         : {$fmt(-$rfUnmapped)} (jika user pakai RF header/items)\n";
echo "  - RF MCS mapped                       : {$fmt(-$rfMappedMcs)}\n";
echo "  RF header - Official RF               : {$fmt($rfHeader - $ocRetail)}\n";

echo "\nDone.\n";
