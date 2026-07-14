<?php
/**
 * Analisa lengkap OP vs Rekap FJ — Bintaro 12 Jul 2026
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$outletId = 23;
$date = '2026-07-12';
$foodPrice = 'COALESCE(fo.price, 0)';
$serialPrice = "(CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)";

$fmt = fn ($n) => number_format((float) $n, 0, ',', '.');

echo "=== TRACE Justus Steak House Bintaro | {$date} ===\n\n";

// 1) Food GR (OGR)
$ogrRows = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->groupBy('gr.id', 'gr.number')
    ->select('gr.id', 'gr.number', DB::raw("SUM(i.received_qty * {$foodPrice}) as total"))
    ->get();

$ogrSum = $ogrRows->sum('total');
echo "1) FOOD GR (OGR) — dipakai OP & Rekap FJ\n";
foreach ($ogrRows as $r) {
    echo "   {$r->number}: {$fmt($r->total)}\n";
}
echo "   SUBTOTAL OGR: {$fmt($ogrSum)}\n\n";

// 2) Serial GSR — OP (semua item)
$gsrOp = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', $date)
    ->whereNull('h.deleted_at')
    ->where('h.status', 'completed')
    ->groupBy('h.id', 'h.number')
    ->select('h.id', 'h.number', DB::raw("SUM(si.qty * {$serialPrice}) as total"))
    ->orderBy('h.number')
    ->get();

$gsrOpSum = $gsrOp->sum('total');
echo "2) SERIAL GSR — rumus OP (semua item, tidak cek warehouse item)\n";
foreach ($gsrOp as $r) {
    echo "   {$r->number}: {$fmt($r->total)}\n";
}
echo "   SUBTOTAL GSR OP: {$fmt($gsrOpSum)}\n\n";

// 3) Serial GSR — Rekap FJ (w.name not null via item warehouse_division)
$gsrRekap = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', $date)
    ->whereNull('h.deleted_at')
    ->whereNotNull('w.name')
    ->groupBy('h.id', 'h.number')
    ->select('h.id', 'h.number', DB::raw("SUM(si.qty * {$serialPrice}) as total"))
    ->orderBy('h.number')
    ->get();

$gsrRekapSum = $gsrRekap->sum('total');
echo "3) SERIAL GSR — rumus Rekap FJ (hanya item punya warehouse)\n";
foreach ($gsrRekap as $r) {
    echo "   {$r->number}: {$fmt($r->total)}\n";
}
echo "   SUBTOTAL GSR REKAP: {$fmt($gsrRekapSum)}\n";
echo "   SELISIH GSR (OP - Rekap): {$fmt($gsrOpSum - $gsrRekapSum)}\n\n";

// Items in OP GSR but excluded from Rekap (no warehouse)
$excl = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', $date)
    ->whereNull('h.deleted_at')
    ->whereNull('w.name')
    ->select(
        'h.number',
        'it.id as item_id',
        'it.name as item_name',
        'it.warehouse_division_id',
        'u.name as unit',
        'si.qty',
        'si.cost_small',
        DB::raw("({$serialPrice}) as unit_price"),
        DB::raw("si.qty * ({$serialPrice}) as subtotal")
    )
    ->orderByDesc(DB::raw("si.qty * ({$serialPrice})"))
    ->get();

echo "4) ITEM GSR masuk OP tapi TIDAK masuk Rekap FJ (warehouse_division/warehouse kosong)\n";
$exclSum = 0;
foreach ($excl as $e) {
    $exclSum += (float) $e->subtotal;
    echo "   {$e->number} | #{$e->item_id} {$e->item_name} | qty {$e->qty} {$e->unit} x {$fmt($e->unit_price)} = {$fmt($e->subtotal)} | wd={$e->warehouse_division_id}\n";
}
echo "   SUBTOTAL EXCLUDED: {$fmt($exclSum)}\n\n";

// Rekap pivot categories for Bintaro
$foodPivot = DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($j) {
        $j->on('i.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', $date)
    ->whereNull('gr.deleted_at')
    ->whereNotNull('w.name')
    ->select('w.name as warehouse', 'sc.name as sub_category', DB::raw("SUM(i.received_qty * {$foodPrice}) as total"))
    ->groupBy('w.name', 'sc.name')
    ->get();

$serialPivot = DB::table('outlet_serial_receive_headers as h')
    ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
    ->join('items as it', 'si.item_id', '=', 'it.id')
    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('h.outlet_id', $outletId)
    ->whereDate('h.receive_date', $date)
    ->whereNull('h.deleted_at')
    ->whereNotNull('w.name')
    ->select('w.name as warehouse', 'sc.name as sub_category', DB::raw("SUM(si.qty * {$serialPrice}) as total"))
    ->groupBy('w.name', 'sc.name')
    ->get();

$agg = ['main_kitchen' => 0, 'main_store' => 0, 'chemical' => 0, 'stationary' => 0, 'marketing' => 0, 'line_total' => 0, 'other' => 0];
foreach ($foodPivot->concat($serialPivot) as $row) {
    $sub = (float) $row->total;
    $wh = trim((string) $row->warehouse);
    $sc = strtoupper(trim((string) $row->sub_category));
    $agg['line_total'] += $sub;
    if (in_array($wh, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'], true)) {
        $agg['main_kitchen'] += $sub;
    } elseif (strtoupper($wh) === 'MAIN STORE') {
        if ($sc === 'CHEMICAL') $agg['chemical'] += $sub;
        elseif ($sc === 'STATIONARY') $agg['stationary'] += $sub;
        elseif ($sc === 'MARKETING') $agg['marketing'] += $sub;
        else $agg['main_store'] += $sub;
    } else {
        $agg['other'] += $sub;
    }
}

echo "5) REKAP FJ STYLE PIVOT (Food + Serial, hanya item ber-warehouse)\n";
foreach ($agg as $k => $v) {
    echo "   {$k}: {$fmt($v)}\n";
}
echo "\n";

// Retail
$retail = (float) DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->where(function ($q) use ($outletId) {
        $q->where('c.id_outlet', (string) $outletId)->orWhere('c.id_outlet', $outletId);
    })
    ->whereDate('rws.created_at', $date)
    ->where('rws.status', 'completed')
    ->sum('rws.total_amount');

echo "6) RETAIL WAREHOUSE SALE\n";
echo "   TOTAL: {$fmt($retail)}\n";
echo "   (di Rekap FJ: laporan terpisah / TIDAK digabung ke line outlet pivot print)\n\n";

$opTotal = $ogrSum + $gsrOpSum + $retail;
$rekapMain = $agg['line_total'];

echo "=== RINGKASAN ===\n";
echo "Screenshot OP:                 40.982.016\n";
echo "Hitung OP (OGR+GSR+Retail):    {$fmt($opTotal)}\n";
echo "Screenshot Rekap FJ line:      39.929.216\n";
echo "Hitung Rekap FJ (GR+GSR wh):   {$fmt($rekapMain)}\n";
echo "Selisih OP - Rekap:            {$fmt($opTotal - $rekapMain)}\n";
echo "  - karena Retail:             {$fmt($retail)}\n";
echo "  - karena GSR tanpa WH item:  {$fmt($exclSum)}\n";
echo "  - lain-lain:                 {$fmt(($opTotal - $rekapMain) - $retail - $exclSum)}\n";
