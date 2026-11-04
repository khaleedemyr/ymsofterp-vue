<?php
/**
 * Trace Official Cost vs Excel (MK + MS + RF/WRF) — Asian Grill BEC Oct 2026
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dateFrom = $argv[1] ?? '2026-10-01';
$dateTo = $argv[2] ?? '2026-10-31';
$outletName = $argv[3] ?? 'ASIAN GRILL BEC';

$outlet = DB::table('tbl_data_outlet')->where('nama_outlet', 'like', '%'.$outletName.'%')->first();
if (!$outlet) {
    fwrite(STDERR, "Outlet not found\n");
    exit(1);
}
$outletId = (int) $outlet->id_outlet;
$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$excluded = ['STATIONARY', 'MARKETING', 'CHEMICAL'];

$foodPrice = 'COALESCE(fo.price, 0)';
$serialPrice = '(CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)';

echo "Outlet [{$outletId}] {$outlet->nama_outlet} | {$dateFrom}..{$dateTo}\n\n";

$bucketSql = function (string $qtyExpr, string $priceExpr) {
    return [
        DB::raw("SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as main_kitchen"),
        DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND UPPER(TRIM(sc.name)) NOT IN ('CHEMICAL','STATIONARY','MARKETING') THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as main_store"),
        DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND UPPER(TRIM(sc.name)) = 'CHEMICAL' THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as chemical"),
        DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND UPPER(TRIM(sc.name)) = 'STATIONARY' THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as stationary"),
        DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND UPPER(TRIM(sc.name)) = 'MARKETING' THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as marketing"),
        DB::raw("SUM({$qtyExpr} * ({$priceExpr})) as line_total"),
        DB::raw("SUM(CASE WHEN UPPER(TRIM(sc.name)) NOT IN ('CHEMICAL','STATIONARY','MARKETING') THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as non_mcs_all_wh"),
        DB::raw("SUM(CASE WHEN w.name IS NULL AND UPPER(TRIM(sc.name)) NOT IN ('CHEMICAL','STATIONARY','MARKETING') THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as no_wh_non_mcs"),
        DB::raw("SUM(CASE WHEN w.name IS NOT NULL AND w.name NOT IN ('MK1 Hot Kitchen','MK2 Cold Kitchen','MAIN STORE') AND UPPER(TRIM(sc.name)) NOT IN ('CHEMICAL','STATIONARY','MARKETING') THEN {$qtyExpr} * ({$priceExpr}) ELSE 0 END) as other_wh_non_mcs"),
    ];
};

// Rekap-style Food GR (all status, WH required for main buckets — match Rekap)
$food = DB::table('outlet_food_good_receives as gr')
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
    ->select($bucketSql('i.received_qty', $foodPrice))
    ->first();

$gsr = (object) [
    'main_kitchen' => 0, 'main_store' => 0, 'chemical' => 0, 'stationary' => 0, 'marketing' => 0,
    'line_total' => 0, 'non_mcs_all_wh' => 0, 'no_wh_non_mcs' => 0, 'other_wh_non_mcs' => 0,
];
if (Schema::hasTable('outlet_serial_receive_headers')) {
    $gsr = DB::table('outlet_serial_receive_headers as h')
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
        ->select($bucketSql('si.qty', $serialPrice))
        ->first();
}

$mk = (float) $food->main_kitchen + (float) $gsr->main_kitchen;
$ms = (float) $food->main_store + (float) $gsr->main_store;
$chem = (float) $food->chemical + (float) $gsr->chemical;
$stat = (float) $food->stationary + (float) $gsr->stationary;
$mkt = (float) $food->marketing + (float) $gsr->marketing;
$otherWh = (float) $food->other_wh_non_mcs + (float) $gsr->other_wh_non_mcs;
$line = (float) $food->line_total + (float) $gsr->line_total;

echo "=== REKAP FJ buckets (all status, WH required) ===\n";
echo "Main Kitchen : {$fmt($mk)}\n";
echo "Main Store   : {$fmt($ms)}\n";
echo "Chemical     : {$fmt($chem)}\n";
echo "Stationary   : {$fmt($stat)}\n";
echo "Marketing    : {$fmt($mkt)}\n";
echo "Other WH non-MCS : {$fmt($otherWh)}\n";
echo "MK+MS        : {$fmt($mk + $ms)}\n";
echo "Line total   : {$fmt($line)}\n\n";

// Official Cost style completed non-MCS (no WH filter)
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
if (Schema::hasTable('outlet_serial_receive_headers')) {
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

$rfHeader = (float) DB::table('retail_food')
    ->where('outlet_id', $outletId)
    ->whereDate('transaction_date', '>=', $dateFrom)
    ->whereDate('transaction_date', '<=', $dateTo)
    ->where('status', 'approved')
    ->sum('total_amount');

// Retail Warehouse Sales (customer linked to this outlet)
$rws = (float) DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->where('c.id_outlet', (string) $outletId)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) >= ?', [$dateFrom])
    ->whereRaw('COALESCE(rws.sale_date, DATE(rws.created_at)) <= ?', [$dateTo])
    ->sum('rws.total_amount');

// Retail Food + RWS item subtotals without MCS filter
$rfAll = (float) DB::table('retail_food as rf')
    ->join('retail_food_items as rfi', 'rf.id', '=', 'rfi.retail_food_id')
    ->where('rf.outlet_id', $outletId)
    ->whereDate('rf.transaction_date', '>=', $dateFrom)
    ->whereDate('rf.transaction_date', '<=', $dateTo)
    ->where('rf.status', 'approved')
    ->sum('rfi.subtotal');

echo "=== OFFICIAL COST (system) ===\n";
echo "Food GR completed non-MCS : {$fmt($ocFood)}\n";
echo "GSR completed non-MCS     : {$fmt($ocGsr)}\n";
echo "GR+GSR non-MCS            : {$fmt($ocFood + $ocGsr)}\n";
echo "Retail Food (mapped)      : {$fmt($ocRetail)}\n";
echo "TOTAL Official            : {$fmt($ocFood + $ocGsr + $ocRetail)}\n\n";

echo "=== RF / WRF candidates ===\n";
echo "Retail Food header        : {$fmt($rfHeader)}\n";
echo "Retail Food all items     : {$fmt($rfAll)}\n";
echo "Retail Warehouse Sales    : {$fmt($rws)}\n";
echo "RF header + RWS           : {$fmt($rfHeader + $rws)}\n";
echo "RF items + RWS            : {$fmt($rfAll + $rws)}\n\n";

$excelMk = 6046281.0;
$excelMs = 18875874.0;
$excelRf = 3457654.20;
$excelTotal = 28379809.06;
$systemTotal = $ocFood + $ocGsr + $ocRetail;

echo "=== EXCEL vs SYSTEM ===\n";
echo "Excel MK                  : {$fmt($excelMk)} | Rekap MK {$fmt($mk)} | diff {$fmt($mk - $excelMk)}\n";
echo "Excel MS                  : {$fmt($excelMs)} | Rekap MS {$fmt($ms)} | diff {$fmt($ms - $excelMs)}\n";
echo "Excel RF&WRF              : {$fmt($excelRf)}\n";
echo "  vs Official RF          : {$fmt($ocRetail)} | diff {$fmt($excelRf - $ocRetail)}\n";
echo "  vs RF+RWS               : {$fmt($rfHeader + $rws)} | diff {$fmt($excelRf - ($rfHeader + $rws))}\n";
echo "Excel Total               : {$fmt($excelTotal)}\n";
echo "System Official           : {$fmt($systemTotal)}\n";
echo "DIFF Excel - System       : {$fmt($excelTotal - $systemTotal)}\n\n";

echo "=== Hypothetical formulas ===\n";
echo "MK+MS+OfficialRF          : {$fmt($mk + $ms + $ocRetail)}\n";
echo "MK+MS+RF header           : {$fmt($mk + $ms + $rfHeader)}\n";
echo "MK+MS+RF+RWS              : {$fmt($mk + $ms + $rfHeader + $rws)}\n";
echo "Official GR/GSR + RF+RWS  : {$fmt($ocFood + $ocGsr + $rfHeader + $rws)}\n";
echo "Official GR/GSR + ExcelRF : {$fmt($ocFood + $ocGsr + $excelRf)}\n";

echo "\nDone.\n";
