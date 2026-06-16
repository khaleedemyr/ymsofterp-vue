<?php
/**
 * READ-ONLY diagnostic: bandingkan Rekap FJ vs Outlet Payment per outlet & tanggal.
 *
 * Usage:
 *   php scripts/compare_rekap_fj_vs_outlet_payment.php --outlet-id=17 --date=2026-06-11
 *   php scripts/compare_rekap_fj_vs_outlet_payment.php --outlet-name="Dago" --date=2026-06-11
 *   php scripts/compare_rekap_fj_vs_outlet_payment.php --outlet-id=17 --date-from=2026-06-11 --date-to=2026-06-11
 *
 * Options:
 *   --outlet-id=N        ID outlet (tbl_data_outlet.id_outlet)
 *   --outlet-name=TEXT   Cari outlet by LIKE nama_outlet (jika --outlet-id tidak diisi)
 *   --date=YYYY-MM-DD    Tanggal tunggal (default: 2026-06-11)
 *   --date-from / --date-to  Rentang tanggal (override --date)
 *   --list-paid          Tampilkan semua GR/GSR yang sudah paid (bukan cuma sample)
 *   --verbose            Tampilkan transaksi yang terpotong limit(50)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
$opts = getopt('', [
    'outlet-id::',
    'outlet-name::',
    'date::',
    'date-from::',
    'date-to::',
    'list-paid',
    'verbose',
    'help',
]);

if (isset($opts['help'])) {
    echo file_get_contents(__FILE__) !== false
        ? preg_replace('/^.*\/\*\*|\*\/.*$/s', '', (string) file_get_contents(__FILE__))
        : "See script header for usage.\n";
    exit(0);
}

$dateFrom = $opts['date-from'] ?? $opts['date'] ?? '2026-06-11';
$dateTo = $opts['date-to'] ?? $opts['date'] ?? $dateFrom;
$listPaid = isset($opts['list-paid']);
$verbose = isset($opts['verbose']);

$outletId = isset($opts['outlet-id']) ? (int) $opts['outlet-id'] : 0;
$outletName = $opts['outlet-name'] ?? null;

if ($outletId <= 0 && $outletName) {
    $matches = DB::table('tbl_data_outlet')
        ->where('nama_outlet', 'like', '%' . $outletName . '%')
        ->get(['id_outlet', 'nama_outlet']);

    if ($matches->isEmpty()) {
        fwrite(STDERR, "Outlet tidak ditemukan untuk nama: {$outletName}\n");
        exit(1);
    }

    echo "=== Outlet cocok ===\n";
    foreach ($matches as $o) {
        echo "  {$o->id_outlet} | {$o->nama_outlet}\n";
    }

    $exact = $matches->first(fn ($o) => stripos($o->nama_outlet, $outletName) !== false);
    $outletId = (int) ($exact?->id_outlet ?? $matches->first()->id_outlet);
}

if ($outletId <= 0) {
    fwrite(STDERR, "Wajib --outlet-id=N atau --outlet-name=TEXT\n");
    exit(1);
}

$outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
if (!$outlet) {
    fwrite(STDERR, "Outlet id {$outletId} tidak ada.\n");
    exit(1);
}

$fmt = static fn (float $n): string => number_format($n, 2, '.', ',');

echo "\n";
echo "============================================================\n";
echo " Rekap FJ vs Outlet Payment — READ ONLY\n";
echo "============================================================\n";
echo "Outlet : [{$outletId}] {$outlet->nama_outlet}\n";
echo "Tanggal: {$dateFrom}" . ($dateFrom !== $dateTo ? " s/d {$dateTo}" : '') . "\n";
echo "Waktu  : " . now()->toDateTimeString() . "\n\n";

// ---------------------------------------------------------------------------
// SQL helpers (selaras ReportHelperTrait + OutletPaymentController)
// ---------------------------------------------------------------------------
$foodPrice = 'COALESCE(fo.price, 0)';
$serialPrice = '(CASE
    WHEN si.unit_id = it.large_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1) * COALESCE(it.medium_conversion_qty, 1)
    WHEN si.unit_id = it.medium_unit_id THEN COALESCE(si.cost_small, 0) * COALESCE(it.small_conversion_qty, 1)
    ELSE COALESCE(si.cost_small, 0)
END)';

$hasGsr = Schema::hasTable('outlet_serial_receive_headers')
    && Schema::hasTable('outlet_serial_receive_items');

// ---------------------------------------------------------------------------
// 1. REKAP FJ
// ---------------------------------------------------------------------------
$rekapFood = (float) DB::table('outlet_food_good_receives as gr')
    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
    ->join('items as it', 'i.item_id', '=', 'it.id')
    ->join('categories as cat', 'it.category_id', '=', 'cat.id')
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

$rekapSerial = 0.0;
if ($hasGsr) {
    $rekapSerial = (float) DB::table('outlet_serial_receive_headers as h')
        ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->join('categories as cat', 'it.category_id', '=', 'cat.id')
        ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
        ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
        ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNotNull('w.name')
        ->sum(DB::raw("si.qty * {$serialPrice}"));
}

$rekapTotal = $rekapFood + $rekapSerial;

// Rekap FJ pivot buckets (sama rekapFjAggregatePivotItemRowsByOutlet)
$pivotRows = collect();
$foodPivot = DB::table('outlet_food_good_receives as gr')
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
    ->select('sc.name as sub_category', 'w.name as warehouse', DB::raw("SUM(i.received_qty * {$foodPrice}) as subtotal"))
    ->groupBy('sc.name', 'w.name')
    ->get();

$pivotRows = $pivotRows->concat($foodPivot);

if ($hasGsr) {
    $serialPivot = DB::table('outlet_serial_receive_headers as h')
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
        ->select('sc.name as sub_category', 'w.name as warehouse', DB::raw("SUM(si.qty * {$serialPrice}) as subtotal"))
        ->groupBy('sc.name', 'w.name')
        ->get();
    $pivotRows = $pivotRows->concat($serialPivot);
}

$buckets = ['main_store' => 0.0, 'main_kitchen' => 0.0, 'chemical' => 0.0, 'stationary' => 0.0, 'marketing' => 0.0, 'line_total' => 0.0];
foreach ($pivotRows as $row) {
    $subtotal = (float) $row->subtotal;
    $warehouse = $row->warehouse ? trim($row->warehouse) : null;
    $subCategory = $row->sub_category ? trim($row->sub_category) : null;
    $buckets['line_total'] += $subtotal;

    if ($warehouse && in_array($warehouse, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'], true)) {
        $buckets['main_kitchen'] += $subtotal;
    } elseif ($warehouse && strtoupper($warehouse) === 'MAIN STORE') {
        if ($subCategory && strtoupper($subCategory) === 'CHEMICAL') {
            $buckets['chemical'] += $subtotal;
        } elseif ($subCategory && strtoupper($subCategory) === 'STATIONARY') {
            $buckets['stationary'] += $subtotal;
        } elseif ($subCategory && strtoupper($subCategory) === 'MARKETING') {
            $buckets['marketing'] += $subtotal;
        } else {
            $buckets['main_store'] += $subtotal;
        }
    }
}

echo "--- REKAP FJ (semua GR, paid + unpaid) ---\n";
echo "  Food GR   : {$fmt($rekapFood)}\n";
echo "  Serial GR : {$fmt($rekapSerial)}\n";
echo "  TOTAL     : {$fmt($rekapTotal)}\n";
echo "  Pivot kolom (seperti laporan):\n";
echo "    Bahan Baku MS : {$fmt($buckets['main_store'])}\n";
echo "    Chemical      : {$fmt($buckets['chemical'])}\n";
echo "    Stationary    : {$fmt($buckets['stationary'])}\n";
echo "    Marketing     : {$fmt($buckets['marketing'])}\n";
echo "    Bahan Baku MK : {$fmt($buckets['main_kitchen'])}\n";
echo "    LINE TOTAL    : {$fmt($buckets['line_total'])}\n\n";

// ---------------------------------------------------------------------------
// 2. OUTLET PAYMENT — unpaid totals
// ---------------------------------------------------------------------------
$allGrTotal = (float) DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as foi', function ($join) {
        $join->on('gri.item_id', '=', 'foi.item_id')->on('do.floor_order_id', '=', 'foi.floor_order_id');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

$unpaidGrTotal = (float) DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as foi', function ($join) {
        $join->on('gri.item_id', '=', 'foi.item_id')->on('do.floor_order_id', '=', 'foi.floor_order_id');
    })
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('gr.id', '=', 'op.gr_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNull('op.id')
    ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

$paidGrTotal = $allGrTotal - $unpaidGrTotal;

$allGrCount = DB::table('outlet_food_good_receives')
    ->where('outlet_id', $outletId)
    ->whereDate('receive_date', '>=', $dateFrom)
    ->whereDate('receive_date', '<=', $dateTo)
    ->whereNull('deleted_at')
    ->count();

$unpaidGrCount = DB::table('outlet_food_good_receives as gr')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('gr.id', '=', 'op.gr_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNull('op.id')
    ->count();

$paidGrCount = $allGrCount - $unpaidGrCount;

$allGsrTotal = 0.0;
$unpaidGsrTotal = 0.0;
$allGsrCount = 0;
$unpaidGsrCount = 0;
$paidGsrTotal = 0.0;

if ($hasGsr) {
    $allGsrTotal = (float) DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $unpaidGsrTotal = (float) DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNull('op.id')
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $paidGsrTotal = $allGsrTotal - $unpaidGsrTotal;

    $allGsrCount = DB::table('outlet_serial_receive_headers')
        ->where('outlet_id', $outletId)
        ->whereDate('receive_date', '>=', $dateFrom)
        ->whereDate('receive_date', '<=', $dateTo)
        ->whereNull('deleted_at')
        ->count();

    $unpaidGsrCount = DB::table('outlet_serial_receive_headers as h')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNull('op.id')
        ->count();
}

// GSR completed only (filter Outlet Payment fetchUnpaidGsrRowsForPayment)
$unpaidGsrCompletedTotal = 0.0;
$unpaidGsrCompletedCount = 0;
if ($hasGsr) {
    $unpaidGsrCompletedTotal = (float) DB::table('outlet_serial_receive_items as si')
        ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNull('op.id')
        ->where('h.status', 'completed')
        ->sum(DB::raw("si.qty * ({$serialPrice})"));

    $unpaidGsrCompletedCount = DB::table('outlet_serial_receive_headers as h')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->whereNull('op.id')
        ->where('h.status', 'completed')
        ->count();
}

// Retail (filter created_at — sama OutletPaymentController)
$retailTotal = (float) DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('c.id_outlet', (string) $outletId)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereNull('op.id')
    ->whereDate('rws.created_at', '>=', $dateFrom)
    ->whereDate('rws.created_at', '<=', $dateTo)
    ->sum('rws.total_amount');

$retailCount = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('c.id_outlet', (string) $outletId)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereNull('op.id')
    ->whereDate('rws.created_at', '>=', $dateFrom)
    ->whereDate('rws.created_at', '<=', $dateTo)
    ->count();

$opUnpaidFull = $unpaidGrTotal + $unpaidGsrCompletedTotal + $retailTotal;

echo "--- OUTLET PAYMENT (unpaid saja, seperti form) ---\n";
echo "  Food GR unpaid     : {$fmt($unpaidGrTotal)} ({$unpaidGrCount} header)\n";
echo "  Food GR paid       : {$fmt($paidGrTotal)} ({$paidGrCount} header)  <- ada di Rekap, tidak di OP\n";
echo "  Serial GR unpaid   : {$fmt($unpaidGsrTotal)} ({$unpaidGsrCount} header, semua status)\n";
echo "  Serial GR unpaid   : {$fmt($unpaidGsrCompletedTotal)} ({$unpaidGsrCompletedCount} header, status=completed) <- filter OP\n";
echo "  Retail unpaid      : {$fmt($retailTotal)} ({$retailCount} sales, filter created_at)\n";
echo "  TOTAL OP (full)    : {$fmt($opUnpaidFull)}  [food + GSR completed + retail]\n\n";

// ---------------------------------------------------------------------------
// 3. GAP analysis
// ---------------------------------------------------------------------------
$gapRekapVsOpFull = $rekapTotal - $opUnpaidFull;
$gapRekapVsOpNoRetail = $rekapTotal - ($unpaidGrTotal + $unpaidGsrCompletedTotal);
$gapPaid = $paidGrTotal + $paidGsrTotal;

echo "--- ANALISIS SELISIH ---\n";
echo "  Rekap FJ total                    : {$fmt($rekapTotal)}\n";
echo "  OP unpaid full (termasuk retail)  : {$fmt($opUnpaidFull)}\n";
echo "  Selisih Rekap - OP full           : {$fmt($gapRekapVsOpFull)}\n";
echo "    (positif = Rekap lebih besar)\n\n";

echo "  Komponen penjelas selisih:\n";
echo "    Paid GR+GSR (Rekap ya, OP tidak): {$fmt($gapPaid)}\n";
echo "    Retail (OP ya, Rekap tidak)     : {$fmt($retailTotal)}  -> menurunkan selisih Rekap-OP\n";
echo "    Rekap - OP tanpa retail         : {$fmt($gapRekapVsOpNoRetail)}\n";
echo "    (harus ~ paid GR+GSR jika rumus sama)\n\n";

$excludedNoWh = (float) DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('items as it', 'gri.item_id', '=', 'it.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as fo', function ($join) {
        $join->on('gri.item_id', '=', 'fo.item_id')->on('fo.floor_order_id', '=', 'do.floor_order_id');
    })
    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNull('w.name')
    ->sum(DB::raw("COALESCE(gri.received_qty * {$foodPrice}, 0)"));

echo "  Item tanpa warehouse (Rekap skip) : {$fmt($excludedNoWh)}\n\n";

// ---------------------------------------------------------------------------
// 4. Simulasi limit(50) di form Outlet Payment
// ---------------------------------------------------------------------------
$uiFoodIds = DB::table('outlet_food_good_receives as gr')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('gr.id', '=', 'op.gr_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('gr.outlet_id', $outletId)
    ->whereDate('gr.receive_date', '>=', $dateFrom)
    ->whereDate('gr.receive_date', '<=', $dateTo)
    ->whereNull('gr.deleted_at')
    ->whereNull('op.id')
    ->orderBy('gr.receive_date', 'desc')
    ->limit(50)
    ->pluck('gr.id');

$uiGsrIds = collect();
if ($hasGsr) {
    $uiGsrIds = DB::table('outlet_serial_receive_headers as h')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('h.outlet_id', $outletId)
        ->whereDate('h.receive_date', '>=', $dateFrom)
        ->whereDate('h.receive_date', '<=', $dateTo)
        ->whereNull('h.deleted_at')
        ->where('h.status', 'completed')
        ->whereNull('op.id')
        ->orderBy('h.receive_date', 'desc')
        ->limit(50)
        ->pluck('h.id');
}

$uiFoodTotal = $uiFoodIds->isEmpty() ? 0.0 : (float) DB::table('outlet_food_good_receive_items as gri')
    ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
    ->leftJoin('food_floor_order_items as foi', function ($join) {
        $join->on('gri.item_id', '=', 'foi.item_id')->on('do.floor_order_id', '=', 'foi.floor_order_id');
    })
    ->whereIn('gr.id', $uiFoodIds)
    ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

$uiGsrTotal = 0.0;
if ($uiGsrIds->isNotEmpty()) {
    $uiGsrTotal = (float) DB::table('outlet_serial_receive_items as si')
        ->join('items as it', 'si.item_id', '=', 'it.id')
        ->whereIn('si.header_id', $uiGsrIds)
        ->sum(DB::raw("si.qty * ({$serialPrice})"));
}

$uiTotal = $uiFoodTotal + $uiGsrTotal + $retailTotal;
$uiCount = $uiFoodIds->count() + $uiGsrIds->count();
$excludedByLimit = $opUnpaidFull - $uiTotal;

echo "--- SIMULASI UI (limit 50 Food GR + limit 50 GSR, seperti getGrList) ---\n";
echo "  Ter-load di form : {$uiCount} transaksi ({$uiFoodIds->count()} Food + {$uiGsrIds->count()} GSR)\n";
echo "  Seharusnya unpaid: " . ($unpaidGrCount + $unpaidGsrCompletedCount) . " ({$unpaidGrCount} Food + {$unpaidGsrCompletedCount} GSR completed)\n";
echo "  Total UI simulasi  : {$fmt($uiTotal)} (+ retail)\n";
echo "  Total OP seharusnya: {$fmt($opUnpaidFull)}\n";
echo "  Terpotong limit    : {$fmt($excludedByLimit)}  <- jika form tampil lebih kecil\n\n";

// ---------------------------------------------------------------------------
// 5. Paid detail
// ---------------------------------------------------------------------------
if ($paidGrCount > 0 || ($hasGsr && ($allGsrCount - $unpaidGsrCount) > 0)) {
    echo "--- TRANSAKSI SUDAH PAID (ikut Rekap, tidak muncul di OP) ---\n";

    if ($paidGrCount > 0) {
        $paidFoodRows = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_payments as op', 'gr.id', '=', 'op.gr_id')
            ->where('gr.outlet_id', $outletId)
            ->whereDate('gr.receive_date', '>=', $dateFrom)
            ->whereDate('gr.receive_date', '<=', $dateTo)
            ->whereNull('gr.deleted_at')
            ->where('op.status', '!=', 'cancelled')
            ->select('gr.id', 'gr.number', 'gr.receive_date', 'op.id as payment_id', 'op.total_amount', 'op.status')
            ->orderBy('gr.receive_date')
            ->get();

        foreach ($paidFoodRows as $row) {
            $amt = (float) DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as foi', function ($join) {
                    $join->on('gri.item_id', '=', 'foi.item_id')->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->where('gr.id', $row->id)
                ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));

            if ($listPaid || $paidFoodRows->count() <= 20) {
                echo "  [Food] {$row->number} | {$row->receive_date} | calc={$fmt($amt)} | op#{$row->payment_id}={$fmt((float)$row->total_amount)} | {$row->status}\n";
            }
        }
        if (!$listPaid && $paidFoodRows->count() > 20) {
            echo "  ... {$paidFoodRows->count()} Food GR paid (pakai --list-paid untuk detail)\n";
        }
    }

    if ($hasGsr && $paidGsrTotal > 0) {
        $paidGsrRows = DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_payments as op', 'h.id', '=', 'op.gsr_id')
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->whereNull('h.deleted_at')
            ->where('op.status', '!=', 'cancelled')
            ->select('h.id', 'h.number', 'h.receive_date', 'op.id as payment_id', 'op.total_amount', 'op.status')
            ->orderBy('h.receive_date')
            ->get();

        foreach ($paidGsrRows as $row) {
            $amt = (float) DB::table('outlet_serial_receive_items as si')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->where('si.header_id', $row->id)
                ->sum(DB::raw("si.qty * ({$serialPrice})"));

            if ($listPaid || $paidGsrRows->count() <= 20) {
                echo "  [GSR]  {$row->number} | {$row->receive_date} | calc={$fmt($amt)} | op#{$row->payment_id}={$fmt((float)$row->total_amount)} | {$row->status}\n";
            }
        }
        if (!$listPaid && $paidGsrRows->count() > 20) {
            echo "  ... {$paidGsrRows->count()} GSR paid (pakai --list-paid untuk detail)\n";
        }
    }
    echo "\n";
} else {
    echo "--- Tidak ada GR/GSR paid pada tanggal ini ---\n\n";
}

// ---------------------------------------------------------------------------
// 6. Verbose: transaksi terpotong limit
// ---------------------------------------------------------------------------
if ($verbose && ($unpaidGrCount > 50 || $unpaidGsrCompletedCount > 50)) {
    echo "--- VERBOSE: transaksi TIDAK ter-load di form (limit 50) ---\n";

    $allUnpaidFoodIds = DB::table('outlet_food_good_receives as gr')
        ->leftJoin('outlet_payments as op', function ($join) {
            $join->on('gr.id', '=', 'op.gr_id')->where('op.status', '!=', 'cancelled');
        })
        ->where('gr.outlet_id', $outletId)
        ->whereDate('gr.receive_date', '>=', $dateFrom)
        ->whereDate('gr.receive_date', '<=', $dateTo)
        ->whereNull('gr.deleted_at')
        ->whereNull('op.id')
        ->orderBy('gr.receive_date', 'desc')
        ->pluck('gr.id');

    $excludedFoodIds = $allUnpaidFoodIds->slice(50);
    foreach ($excludedFoodIds as $gid) {
        $gr = DB::table('outlet_food_good_receives')->where('id', $gid)->first();
        $amt = (float) DB::table('outlet_food_good_receive_items as gri')
            ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as foi', function ($join) {
                $join->on('gri.item_id', '=', 'foi.item_id')->on('do.floor_order_id', '=', 'foi.floor_order_id');
            })
            ->where('gr.id', $gid)
            ->sum(DB::raw('COALESCE(gri.received_qty * foi.price, 0)'));
        echo "  [Food TERPOTONG] {$gr->number} | {$gr->receive_date} | {$fmt($amt)}\n";
    }

    if ($hasGsr && $unpaidGsrCompletedCount > 50) {
        $allUnpaidGsrIds = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('outlet_payments as op', function ($join) {
                $join->on('h.id', '=', 'op.gsr_id')->where('op.status', '!=', 'cancelled');
            })
            ->where('h.outlet_id', $outletId)
            ->whereDate('h.receive_date', '>=', $dateFrom)
            ->whereDate('h.receive_date', '<=', $dateTo)
            ->whereNull('h.deleted_at')
            ->whereNull('op.id')
            ->where('h.status', 'completed')
            ->orderBy('h.receive_date', 'desc')
            ->pluck('h.id');

        foreach ($allUnpaidGsrIds->slice(50) as $hid) {
            $h = DB::table('outlet_serial_receive_headers')->where('id', $hid)->first();
            $amt = (float) DB::table('outlet_serial_receive_items as si')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->where('si.header_id', $hid)
                ->sum(DB::raw("si.qty * ({$serialPrice})"));
            echo "  [GSR TERPOTONG]  {$h->number} | {$h->receive_date} | {$fmt($amt)}\n";
        }
    }
    echo "\n";
}

// ---------------------------------------------------------------------------
// 7. Kesimpulan otomatis
// ---------------------------------------------------------------------------
echo "============================================================\n";
echo " KESIMPULAN\n";
echo "============================================================\n";

if (abs($gapPaid) > 1 && abs($gapPaid - $gapRekapVsOpNoRetail) < 1000) {
    echo "=> Selisih utama kemungkinan GR/GSR yang SUDAH DIBAYAR (~{$fmt($gapPaid)}).\n";
    echo "   Rekap FJ menghitung semua; Outlet Payment hanya unpaid.\n";
} elseif (abs($excludedByLimit) > 1 && abs($excludedByLimit) > abs($gapPaid)) {
    echo "=> Selisih utama kemungkinan LIMIT 50 di form Outlet Payment (~{$fmt($excludedByLimit)} terpotong).\n";
    echo "   Form tidak memuat semua transaksi unpaid. Pakai --verbose untuk daftar.\n";
} elseif (abs($gapRekapVsOpFull + $retailTotal - $gapPaid) < 1000) {
    echo "=> Selisih = paid GR/GSR ({$fmt($gapPaid)}) dikurangi retail ({$fmt($retailTotal)}).\n";
} elseif (abs($rekapTotal - ($allGrTotal + $allGsrTotal)) < 1) {
    echo "=> Rekap FJ dan OP (tanpa filter paid) sudah selaras. Cek unpaid vs paid & limit form.\n";
} else {
    echo "=> Periksa output di atas; ada perbedaan filter warehouse/kategori atau data.\n";
}

echo "\nSelesai.\n";
