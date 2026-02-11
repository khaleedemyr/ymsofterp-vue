<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\RetailNonFood;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisition;
use App\Exports\OrderDetailExport;
use App\Exports\ItemEngineeringExport;
use App\Exports\ItemEngineeringMultiSheetExport;
use App\Exports\ItemEngineeringSheetExport;
use App\Exports\ModifierEngineeringSheetExport;
use App\Exports\SalesPivotPerOutletSubCategoryExport;
use App\Exports\SalesPivotSpecialExport;
use App\Exports\ReportSalesAllItemAllOutletExport;
use App\Exports\ReportRekapDiskonPromoExport;
use App\Exports\ReportRekapDiskonGlobalExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function reportSalesPerCategory(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as c', 'it.category_id', '=', 'c.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->select(
                'w.name as gudang',
                DB::raw('MONTH(gr.receive_date) as bulan'),
                DB::raw('YEAR(gr.receive_date) as tahun'),
                'c.name as category',
                DB::raw('SUM(i.received_qty * fo.price) as nilai')
            );

        // Filter
        if ($request->filled('warehouse')) {
            $query->where('w.name', $request->warehouse);
        }
        if ($request->filled('category')) {
            $query->where('c.name', $request->category);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('gr.receive_date', $request->tahun);
        }
        if ($request->filled('bulan')) {
            $query->whereMonth('gr.receive_date', $request->bulan);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('c.name', 'like', $search);
            });
        }

        $query->groupBy('w.name', DB::raw('MONTH(gr.receive_date)'), DB::raw('YEAR(gr.receive_date)'), 'c.name')
            ->orderBy('w.name')
            ->orderBy(DB::raw('YEAR(gr.receive_date)'))
            ->orderBy(DB::raw('MONTH(gr.receive_date)'))
            ->orderBy('c.name');

        $perPage = $request->input('perPage', 25);
        $page = $request->input('page', 1);
        $data = collect($query->get());

        // Manual pagination (karena groupBy)
        $total = $data->count();
        $paginated = $data->slice(($page - 1) * $perPage, $perPage)->values();

        // Data filter
        $warehouses = DB::table('warehouses')->select('name')->orderBy('name')->get();
        $categories = DB::table('categories')->select('name')->orderBy('name')->get();
        $years = DB::table('outlet_food_good_receives')->select(DB::raw('DISTINCT YEAR(receive_date) as tahun'))->orderBy('tahun', 'desc')->pluck('tahun');

        return Inertia::render('Report/ReportSalesPerCategory', [
            'report' => $paginated,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'years' => $years,
            'filters' => [
                'search' => $request->search,
                'warehouse' => $request->warehouse,
                'category' => $request->category,
                'tahun' => $request->tahun,
                'bulan' => $request->bulan,
                'perPage' => $perPage,
                'page' => $page,
            ],
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
        ]);
    }

    public function reportSalesPerTanggal(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->select(
                'w.name as gudang',
                DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y") as tanggal'),
                DB::raw('SUM(i.received_qty * fo.price) as nilai')
            );

        // Filter
        if ($request->filled('warehouse')) {
            $query->where('w.name', $request->warehouse);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('gr.receive_date', $request->tahun);
        }
        if ($request->filled('bulan')) {
            $query->whereMonth('gr.receive_date', $request->bulan);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere(DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y")'), 'like', $search);
            });
        }

        $query->groupBy('w.name', DB::raw('gr.receive_date'))
            ->orderBy('w.name')
            ->orderBy(DB::raw('gr.receive_date'));

        $perPage = $request->input('perPage', 25);
        $page = $request->input('page', 1);
        $data = collect($query->get());

        $total = $data->count();
        $paginated = $data->slice(($page - 1) * $perPage, $perPage)->values();

        $warehouses = DB::table('warehouses')->select('name')->orderBy('name')->get();
        $years = DB::table('outlet_food_good_receives')->select(DB::raw('DISTINCT YEAR(receive_date) as tahun'))->orderBy('tahun', 'desc')->pluck('tahun');
        $months = range(1, 12);

        return Inertia::render('Report/ReportSalesPerTanggal', [
            'report' => $paginated,
            'warehouses' => $warehouses,
            'years' => $years,
            'months' => $months,
            'filters' => [
                'search' => $request->search,
                'warehouse' => $request->warehouse,
                'tahun' => $request->tahun,
                'bulan' => $request->bulan,
                'perPage' => $perPage,
                'page' => $page,
            ],
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
        ]);
    }

    /**
     * Rekap diskon dari order_promos (status=active), join orders & promos.
     */
    public function reportRekapDiskon(Request $request)
    {
        $query = DB::table('order_promos as op')
            ->where('op.status', 'active')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->leftJoin('tbl_data_outlet as outlet', 'op.kode_outlet', '=', 'outlet.qr_code')
            ->select(
                'op.id as order_promo_id',
                'op.order_id',
                'op.promo_id',
                'op.kode_outlet',
                DB::raw('COALESCE(outlet.nama_outlet, op.kode_outlet) as outlet_name'),
                'op.created_at as order_promo_created_at',
                'o.nomor as order_nomor',
                'o.paid_number',
                'o.created_at as order_created_at',
                'o.total as order_total',
                'o.discount as order_discount',
                'o.grand_total as order_grand_total',
                'p.name as promo_name',
                'p.code as promo_code',
                'p.type as promo_type',
                'p.value as promo_value',
                'p.discount_type as promo_discount_type',
                'p.max_discount as promo_max_discount'
            );

        if ($request->filled('date_from')) {
            $query->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $query->where('op.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('p.name', 'like', $search)
                    ->orWhere('p.code', 'like', $search);
            });
        }

        $query->orderBy('o.created_at', 'desc')->orderBy('op.id', 'desc');

        $perPage = (int) $request->input('perPage', 25);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $detail = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // Hitung jumlah promo per order (untuk alokasi diskon proporsional)
        $orderPromoCounts = DB::table('order_promos')
            ->where('status', 'active')
            ->whereIn('order_id', $detail->pluck('order_id')->unique())
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id');

        // Summary per promo: jumlah pemakaian & total diskon (alokasi proporsional)
        $summaryQuery = DB::table('order_promos as op')
            ->where('op.status', 'active')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->select(
                'p.id as promo_id',
                'p.name as promo_name',
                'p.code as promo_code',
                'p.type as promo_type',
                'p.value as promo_value',
                'p.discount_type as promo_discount_type',
                'op.order_id',
                'o.discount as order_discount'
            );

        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $summaryQuery->where('op.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('p.name', 'like', $search)
                    ->orWhere('p.code', 'like', $search);
            });
        }

        $summaryRows = $summaryQuery->get();

        // Jumlah promo per order (untuk seluruh data summary)
        $allOrderPromoCounts = DB::table('order_promos')
            ->where('status', 'active')
            ->whereIn('order_id', $summaryRows->pluck('order_id')->unique())
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id');

        $summaryByPromo = [];
        foreach ($summaryRows as $row) {
            $key = $row->promo_id;
            if (!isset($summaryByPromo[$key])) {
                $summaryByPromo[$key] = [
                    'promo_id' => $row->promo_id,
                    'promo_name' => $row->promo_name,
                    'promo_code' => $row->promo_code,
                    'promo_type' => $row->promo_type,
                    'promo_value' => $row->promo_value,
                    'promo_discount_type' => $row->promo_discount_type ?? null,
                    'jumlah_pemakaian' => 0,
                    'total_discount' => 0,
                ];
            }
            $summaryByPromo[$key]['jumlah_pemakaian']++;
            $cnt = $allOrderPromoCounts[$row->order_id] ?? 1;
            $summaryByPromo[$key]['total_discount'] += ($row->order_discount ?? 0) / $cnt;
        }
        $summary = array_values($summaryByPromo);

        // Daftar outlet untuk filter: gabungan dari order_promos dan orders (manual discount)
        $outletsRaw = DB::table('order_promos as op')
            ->where('op.status', 'active')
            ->leftJoin('tbl_data_outlet as outlet', 'op.kode_outlet', '=', 'outlet.qr_code')
            ->select('op.kode_outlet', DB::raw('COALESCE(outlet.nama_outlet, op.kode_outlet) as nama_outlet'))
            ->distinct();
        $outletsGlobal = DB::table('orders as o')
            ->whereNotNull('o.manual_discount_amount')
            ->where('o.manual_discount_amount', '>', 0)
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->select('o.kode_outlet', DB::raw('COALESCE(outlet.nama_outlet, o.kode_outlet) as nama_outlet'))
            ->distinct()
            ->union($outletsRaw)
            ->orderBy('nama_outlet')
            ->get();
        $outlets = $outletsGlobal->unique('kode_outlet')->values()->map(function ($r) {
            return ['value' => $r->kode_outlet, 'label' => $r->nama_outlet ?? $r->kode_outlet ?? '(kosong)'];
        })->values()->all();

        // --- Tab 2: Diskon Bank & Lainnya (diskon global dari orders.manual_discount_amount) ---
        $globalQuery = DB::table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->whereNotNull('o.manual_discount_amount')
            ->where('o.manual_discount_amount', '>', 0)
            ->select(
                'o.id',
                'o.nomor',
                'o.paid_number',
                'o.created_at',
                'o.kode_outlet',
                DB::raw('COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name'),
                'o.grand_total',
                'o.manual_discount_amount',
                'o.manual_discount_reason'
            );

        if ($request->filled('date_from')) {
            $globalQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $globalQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $globalQuery->where('o.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $globalQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('o.manual_discount_reason', 'like', $search);
            });
        }

        $globalQuery->orderBy('o.created_at', 'desc');
        $totalGlobal = $globalQuery->count();
        $perPageGlobal = (int) $request->input('perPageGlobal', 25);
        $pageGlobal = (int) $request->input('pageGlobal', 1);
        $detailGlobal = (clone $globalQuery)->offset(($pageGlobal - 1) * $perPageGlobal)->limit($perPageGlobal)->get();

        // Summary diskon global: total transaksi & total nominal (query terpisah dengan filter sama)
        $summaryGlobalQuery = DB::table('orders as o')
            ->whereNotNull('o.manual_discount_amount')
            ->where('o.manual_discount_amount', '>', 0);
        if ($request->filled('date_from')) {
            $summaryGlobalQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryGlobalQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $summaryGlobalQuery->where('o.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryGlobalQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('o.manual_discount_reason', 'like', $search);
            });
        }
        $summaryGlobalRow = $summaryGlobalQuery->selectRaw('COUNT(*) as total_transaksi, COALESCE(SUM(o.manual_discount_amount), 0) as total_nominal')->first();
        $summaryGlobal = [
            'total_transaksi' => (int) ($summaryGlobalRow->total_transaksi ?? 0),
            'total_nominal' => (float) ($summaryGlobalRow->total_nominal ?? 0),
        ];

        return Inertia::render('Report/ReportRekapDiskon', [
            'detail' => $detail,
            'summary' => $summary,
            'detailGlobal' => $detailGlobal,
            'summaryGlobal' => $summaryGlobal,
            'outlets' => $outlets,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'kode_outlet' => $request->kode_outlet,
                'search' => $request->search,
                'perPage' => $perPage,
                'page' => $page,
                'perPageGlobal' => $perPageGlobal,
                'pageGlobal' => $pageGlobal,
            ],
            'total' => $total,
            'totalGlobal' => $totalGlobal,
            'perPage' => $perPage,
            'page' => $page,
            'perPageGlobal' => $perPageGlobal,
            'pageGlobal' => $pageGlobal,
        ]);
    }

    /**
     * API: detail order + items untuk modal (report rekap diskon).
     */
    public function getOrderDetailRekapDiskon(Request $request, string $orderId)
    {
        $order = DB::table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->where('o.id', $orderId)
            ->select('o.*', DB::raw('outlet.nama_outlet as nama_outlet'))
            ->first();
        if (!$order) {
            return response()->json(['error' => 'Order tidak ditemukan'], 404);
        }
        $order = (array) $order;
        $order['items'] = DB::table('order_items')
            ->where('order_id', $orderId)
            ->select('id', 'item_id', 'item_name', 'qty', 'price', 'subtotal', 'notes', 'b1g1_promo_id', 'b1g1_status')
            ->get()
            ->map(function ($i) {
                return (array) $i;
            })
            ->all();
        $order['payments'] = DB::table('order_payment')
            ->where('order_id', $orderId)
            ->select('payment_code', 'payment_type', 'amount', 'change')
            ->get()
            ->map(function ($p) {
                return (array) $p;
            })
            ->all();
        $promo = DB::table('order_promos as op')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->where('op.order_id', $orderId)
            ->where('op.status', 'active')
            ->select('p.id', 'p.name', 'p.code', 'p.type', 'p.value')
            ->first();
        $order['promo'] = $promo ? (array) $promo : null;
        return response()->json($order);
    }

    /**
     * Export Rekap Diskon - Tab Diskon Promo (semua data sesuai filter, tanpa pagination).
     */
    public function exportRekapDiskonPromo(Request $request)
    {
        $query = DB::table('order_promos as op')
            ->where('op.status', 'active')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->leftJoin('tbl_data_outlet as outlet', 'op.kode_outlet', '=', 'outlet.qr_code')
            ->select(
                'op.id as order_promo_id',
                'op.order_id',
                'op.promo_id',
                'op.kode_outlet',
                DB::raw('COALESCE(outlet.nama_outlet, op.kode_outlet) as outlet_name'),
                'op.created_at as order_promo_created_at',
                'o.nomor as order_nomor',
                'o.paid_number',
                'o.created_at as order_created_at',
                'o.total as order_total',
                'o.discount as order_discount',
                'o.grand_total as order_grand_total',
                'p.name as promo_name',
                'p.code as promo_code',
                'p.type as promo_type',
                'p.value as promo_value',
                'p.discount_type as promo_discount_type',
                'p.max_discount as promo_max_discount'
            );
        if ($request->filled('date_from')) {
            $query->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $query->where('op.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('p.name', 'like', $search)
                    ->orWhere('p.code', 'like', $search);
            });
        }
        $query->orderBy('o.created_at', 'desc')->orderBy('op.id', 'desc');
        $detail = $query->get();

        $orderIds = $detail->pluck('order_id')->unique()->values()->all();
        $orderPromoCounts = $orderIds ? DB::table('order_promos')
            ->where('status', 'active')
            ->whereIn('order_id', $orderIds)
            ->selectRaw('order_id, COUNT(*) as cnt')
            ->groupBy('order_id')
            ->pluck('cnt', 'order_id')->all() : [];

        foreach ($detail as $row) {
            $row->allocated_discount = isset($orderPromoCounts[$row->order_id])
                ? ($row->order_discount ?? 0) / $orderPromoCounts[$row->order_id]
                : ($row->order_discount ?? 0);
        }

        $summaryQuery = DB::table('order_promos as op')
            ->where('op.status', 'active')
            ->join('orders as o', 'op.order_id', '=', 'o.id')
            ->join('promos as p', 'op.promo_id', '=', 'p.id')
            ->select(
                'p.id as promo_id',
                'p.name as promo_name',
                'p.code as promo_code',
                'p.type as promo_type',
                'p.value as promo_value',
                'p.discount_type as promo_discount_type',
                'op.order_id',
                'o.discount as order_discount'
            );
        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $summaryQuery->where('op.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('p.name', 'like', $search)
                    ->orWhere('p.code', 'like', $search);
            });
        }
        $summaryRows = $summaryQuery->get();
        $allOrderPromoCounts = $summaryRows->pluck('order_id')->unique()->isEmpty()
            ? []
            : DB::table('order_promos')
                ->where('status', 'active')
                ->whereIn('order_id', $summaryRows->pluck('order_id')->unique())
                ->selectRaw('order_id, COUNT(*) as cnt')
                ->groupBy('order_id')
                ->pluck('cnt', 'order_id')->all();
        $summaryByPromo = [];
        foreach ($summaryRows as $row) {
            $key = $row->promo_id;
            if (! isset($summaryByPromo[$key])) {
                $summaryByPromo[$key] = [
                    'promo_id' => $row->promo_id,
                    'promo_name' => $row->promo_name,
                    'promo_code' => $row->promo_code,
                    'promo_type' => $row->promo_type,
                    'promo_value' => $row->promo_value,
                    'promo_discount_type' => $row->promo_discount_type ?? null,
                    'jumlah_pemakaian' => 0,
                    'total_discount' => 0,
                ];
            }
            $summaryByPromo[$key]['jumlah_pemakaian']++;
            $cnt = $allOrderPromoCounts[$row->order_id] ?? 1;
            $summaryByPromo[$key]['total_discount'] += ($row->order_discount ?? 0) / $cnt;
        }
        $summary = array_values($summaryByPromo);

        $export = new ReportRekapDiskonPromoExport(
            $detail,
            $summary,
            $request->input('date_from', ''),
            $request->input('date_to', ''),
            $request->input('search', '')
        );
        return $export->toResponse($request);
    }

    /**
     * Export Rekap Diskon - Tab Diskon Bank & Lainnya (semua data sesuai filter).
     */
    public function exportRekapDiskonGlobal(Request $request)
    {
        $globalQuery = DB::table('orders as o')
            ->leftJoin('tbl_data_outlet as outlet', 'o.kode_outlet', '=', 'outlet.qr_code')
            ->whereNotNull('o.manual_discount_amount')
            ->where('o.manual_discount_amount', '>', 0)
            ->select(
                'o.id',
                'o.nomor',
                'o.paid_number',
                'o.created_at',
                'o.kode_outlet',
                DB::raw('COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name'),
                'o.grand_total',
                'o.manual_discount_amount',
                'o.manual_discount_reason'
            );
        if ($request->filled('date_from')) {
            $globalQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $globalQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $globalQuery->where('o.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $globalQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('o.manual_discount_reason', 'like', $search);
            });
        }
        $globalQuery->orderBy('o.created_at', 'desc');
        $detailGlobal = $globalQuery->get();

        $summaryGlobalQuery = DB::table('orders as o')
            ->whereNotNull('o.manual_discount_amount')
            ->where('o.manual_discount_amount', '>', 0);
        if ($request->filled('date_from')) {
            $summaryGlobalQuery->whereDate('o.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $summaryGlobalQuery->whereDate('o.created_at', '<=', $request->date_to);
        }
        if ($request->filled('kode_outlet')) {
            $summaryGlobalQuery->where('o.kode_outlet', $request->kode_outlet);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $summaryGlobalQuery->where(function ($q) use ($search) {
                $q->where('o.nomor', 'like', $search)
                    ->orWhere('o.paid_number', 'like', $search)
                    ->orWhere('o.manual_discount_reason', 'like', $search);
            });
        }
        $summaryGlobalRow = $summaryGlobalQuery->selectRaw('COUNT(*) as total_transaksi, COALESCE(SUM(o.manual_discount_amount), 0) as total_nominal')->first();
        $summaryGlobal = [
            'total_transaksi' => (int) ($summaryGlobalRow->total_transaksi ?? 0),
            'total_nominal' => (float) ($summaryGlobalRow->total_nominal ?? 0),
        ];

        $export = new ReportRekapDiskonGlobalExport(
            $detailGlobal,
            $summaryGlobal,
            $request->input('date_from', ''),
            $request->input('date_to', ''),
            $request->input('search', '')
        );
        return $export->toResponse($request);
    }

    public function reportSalesAllItemAllOutlet(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y") as tanggal'),
                'w.name as gudang',
                'o.nama_outlet as outlet',
                'it.name as nama_barang',
                DB::raw('SUM(i.received_qty) as qty'),
                'u.name as unit',
                'fo.price as harga',
                DB::raw('SUM(i.received_qty * fo.price) as subtotal')
            );

        if ($request->filled('gudang')) {
            $query->where('w.name', $request->gudang);
        }
        if ($request->filled('outlet')) {
            $query->where('o.nama_outlet', $request->outlet);
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('gr.receive_date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('gr.receive_date', '<=', $request->dateTo);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('it.name', 'like', $search);
            });
        }

        $query->groupBy(
            DB::raw('gr.receive_date'),
            'w.name',
            'o.nama_outlet',
            'it.name',
            'u.name',
            'fo.price'
        )
        ->orderBy('gr.receive_date')
        ->orderBy('w.name')
        ->orderBy('o.nama_outlet')
        ->orderBy('it.name');

        // Build GR Supplier query with same select structure
        $supplierQuery = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y") as tanggal'),
                DB::raw('COALESCE(w.name, "MAIN STORE") as gudang'),
                'o.nama_outlet as outlet',
                'it.name as nama_barang',
                DB::raw('SUM(i.qty_received) as qty'),
                'u.name as unit',
                DB::raw('COALESCE(fo.price, 0) as harga'),
                DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
            );

        if ($request->filled('gudang')) {
            $supplierQuery->where(function($q) use ($request) {
                $q->where('w.name', $request->gudang)
                  ->orWhereNull('w.name');
            });
        }
        if ($request->filled('outlet')) {
            $supplierQuery->where('o.nama_outlet', $request->outlet);
        }
        if ($request->filled('dateFrom')) {
            $supplierQuery->whereDate('gr.receive_date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $supplierQuery->whereDate('gr.receive_date', '<=', $request->dateTo);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $supplierQuery->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('it.name', 'like', $search);
            });
        }

        $supplierQuery->groupBy(
            DB::raw('gr.receive_date'),
            DB::raw('COALESCE(w.name, "MAIN STORE")'),
            'o.nama_outlet',
            'it.name',
            'u.name',
            DB::raw('COALESCE(fo.price, 0)')
        );

        // Merge both datasets in memory for pagination
        $data = collect($query->get())->merge(collect($supplierQuery->get()))
            ->sortBy([['tanggal', 'asc'], ['gudang', 'asc'], ['outlet', 'asc'], ['nama_barang', 'asc']])
            ->values();
        $perPage = $request->input('perPage', 25);
        $page = $request->input('page', 1);

        $total = $data->count();
        $paginated = $data->slice(($page - 1) * $perPage, $perPage)->values();

        $warehouses = DB::table('warehouses')->select('name')->orderBy('name')->get();
        $outlets = DB::table('tbl_data_outlet')->select('nama_outlet')->orderBy('nama_outlet')->get();

        return Inertia::render('Report/ReportSalesAllItemAllOutlet', [
            'report' => $paginated,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
            'filters' => [
                'search' => $request->search,
                'gudang' => $request->gudang,
                'outlet' => $request->outlet,
                'dateFrom' => $request->dateFrom,
                'dateTo' => $request->dateTo,
                'perPage' => $perPage,
                'page' => $page,
            ],
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
        ]);
    }

    public function exportSalesAllItemAllOutlet(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y") as tanggal'),
                'w.name as gudang',
                'o.nama_outlet as outlet',
                'it.name as nama_barang',
                DB::raw('SUM(i.received_qty) as qty'),
                'u.name as unit',
                'fo.price as harga',
                DB::raw('SUM(i.received_qty * fo.price) as subtotal')
            );

        if ($request->filled('gudang')) {
            $query->where('w.name', $request->gudang);
        }
        if ($request->filled('outlet')) {
            $query->where('o.nama_outlet', $request->outlet);
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('gr.receive_date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('gr.receive_date', '<=', $request->dateTo);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('it.name', 'like', $search);
            });
        }

        $query->groupBy(
            DB::raw('gr.receive_date'),
            'w.name',
            'o.nama_outlet',
            'it.name',
            'u.name',
            'fo.price'
        )
        ->orderBy('gr.receive_date')
        ->orderBy('w.name')
        ->orderBy('o.nama_outlet')
        ->orderBy('it.name');

        // Build GR Supplier query and merge
        $supplierQuery = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                DB::raw('DATE_FORMAT(gr.receive_date, "%d %M %Y") as tanggal'),
                DB::raw('COALESCE(w.name, "MAIN STORE") as gudang'),
                'o.nama_outlet as outlet',
                'it.name as nama_barang',
                DB::raw('SUM(i.qty_received) as qty'),
                'u.name as unit',
                DB::raw('COALESCE(fo.price, 0) as harga'),
                DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
            );

        if ($request->filled('gudang')) {
            $supplierQuery->where(function($q) use ($request) {
                $q->where('w.name', $request->gudang)
                  ->orWhereNull('w.name');
            });
        }
        if ($request->filled('outlet')) {
            $supplierQuery->where('o.nama_outlet', $request->outlet);
        }
        if ($request->filled('dateFrom')) {
            $supplierQuery->whereDate('gr.receive_date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $supplierQuery->whereDate('gr.receive_date', '<=', $request->dateTo);
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $supplierQuery->where(function($q) use ($search) {
                $q->where('w.name', 'like', $search)
                  ->orWhere('o.nama_outlet', 'like', $search)
                  ->orWhere('it.name', 'like', $search);
            });
        }

        $supplierQuery->groupBy(
            DB::raw('gr.receive_date'),
            DB::raw('COALESCE(w.name, "MAIN STORE")'),
            'o.nama_outlet',
            'it.name',
            'u.name',
            DB::raw('COALESCE(fo.price, 0)')
        )
        ->orderBy('gr.receive_date')
        ->orderBy('gudang')
        ->orderBy('o.nama_outlet')
        ->orderBy('it.name');

        $data = collect($query->get())->merge(collect($supplierQuery->get()))
            ->sortBy([['tanggal', 'asc'], ['gudang', 'asc'], ['outlet', 'asc'], ['nama_barang', 'asc']])
            ->values();

        $filters = [
            'search' => $request->search,
            'gudang' => $request->gudang,
            'outlet' => $request->outlet,
            'dateFrom' => $request->dateFrom,
            'dateTo' => $request->dateTo,
        ];

        $fileName = 'Report_Penjualan_All_Item_All_Outlet_' . date('Y-m-d_H-i-s') . '.xlsx';
        $export = new ReportSalesAllItemAllOutletExport($data, $filters);
        $export->fileName = $fileName;
        return $export;
    }

    public function reportSalesPivotPerOutletSubCategory(Request $request)
    {
        // Ambil semua sub kategori dengan show_pos = '0'
        $subCategories = DB::table('sub_categories')
            ->where('show_pos', '0')
            ->orderBy('name')
            ->get();

        // Query untuk outlet_food_good_receives (sama dengan Report Rekap FJ)
        $query1 = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('sc.show_pos', '0')
            ->select(
                'o.nama_outlet as customer',
                'o.is_outlet',
                'sc.name as sub_category',
                DB::raw('SUM(i.received_qty * fo.price) as nilai')
            );

        // Filter tanggal - gunakan date range seperti Report Rekap FJ
        if ($request->filled('from')) {
            $query1->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query1->whereDate('gr.receive_date', '<=', $request->to);
        }
        // Fallback untuk filter tanggal tunggal (backward compatibility)
        if ($request->filled('tanggal') && !$request->filled('from') && !$request->filled('to')) {
            $query1->whereDate('gr.receive_date', $request->tanggal);
        }
        
        // Filter GR yang belum dihapus
        $query1->whereNull('gr.deleted_at');

        $report1 = $query1
            ->groupBy('o.nama_outlet', 'o.is_outlet', 'sc.name')
            ->orderBy('o.is_outlet', 'desc')
            ->orderBy('o.nama_outlet')
            ->orderBy('sc.name')
            ->get();

        // Query untuk good_receive_outlet_suppliers (sama dengan Report Rekap FJ)
        $query2 = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('sc.show_pos', '0')
            ->select(
                'o.nama_outlet as customer',
                'o.is_outlet',
                'sc.name as sub_category',
                DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as nilai')
            );

        // Filter tanggal untuk query2
        if ($request->filled('from')) {
            $query2->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query2->whereDate('gr.receive_date', '<=', $request->to);
        }
        // Fallback untuk filter tanggal tunggal
        if ($request->filled('tanggal') && !$request->filled('from') && !$request->filled('to')) {
            $query2->whereDate('gr.receive_date', $request->tanggal);
        }

        $report2 = $query2
            ->groupBy('o.nama_outlet', 'o.is_outlet', 'sc.name')
            ->orderBy('o.is_outlet', 'desc')
            ->orderBy('o.nama_outlet')
            ->orderBy('sc.name')
            ->get();

        // Gabungkan kedua report dan group by outlet + sub_category
        $combinedData = collect();
        $outletData = [];
        
        // Process report1 (outlet_food_good_receives)
        foreach ($report1 as $row) {
            $key = $row->customer . '_' . $row->sub_category;
            if (!isset($outletData[$key])) {
                $outletData[$key] = [
                    'customer' => $row->customer,
                    'is_outlet' => $row->is_outlet,
                    'sub_category' => $row->sub_category,
                    'nilai' => 0
                ];
            }
            $outletData[$key]['nilai'] += $row->nilai;
        }
        
        // Process report2 (good_receive_outlet_suppliers)
        foreach ($report2 as $row) {
            $key = $row->customer . '_' . $row->sub_category;
            if (!isset($outletData[$key])) {
                $outletData[$key] = [
                    'customer' => $row->customer,
                    'is_outlet' => $row->is_outlet,
                    'sub_category' => $row->sub_category,
                    'nilai' => 0
                ];
            }
            $outletData[$key]['nilai'] += $row->nilai;
        }

        // Bentuk pivot array
        $pivot = [];
        foreach ($outletData as $row) {
            $customer = $row['customer'];
            if (!isset($pivot[$customer])) {
                $pivot[$customer] = [
                    'customer' => $customer,
                    'is_outlet' => $row['is_outlet'],
                    'line_total' => 0,
                ];
            }
            $pivot[$customer][$row['sub_category']] = $row['nilai'];
            $pivot[$customer]['line_total'] += $row['nilai'];
        }

        // Pastikan semua sub kategori ada kolomnya
        foreach ($pivot as $customer => &$row) {
            foreach ($subCategories as $sc) {
                if (!isset($row[$sc->name])) {
                    $row[$sc->name] = 0;
                }
            }
        }
        unset($row);

        // Group data berdasarkan is_outlet (sama dengan Report Rekap FJ)
        $groupedReport = [
            'outlets' => array_values(array_filter($pivot, function($row) {
                return $row['is_outlet'] == 1;
            })),
            'nonOutlets' => array_values(array_filter($pivot, function($row) {
                return $row['is_outlet'] != 1;
            }))
        ];

        // Kirim ke frontend
        return Inertia::render('Report/ReportSalesPivotPerOutletSubCategory', [
            'subCategories' => $subCategories,
            'report' => $groupedReport,
            'filters' => [
                'from' => $request->from,
                'to' => $request->to,
                'tanggal' => $request->tanggal, // Backward compatibility
            ],
        ]);
    }

    public function exportSalesPivotPerOutletSubCategory(Request $request)
    {
        try {
            $from = $request->input('from');
            $to = $request->input('to');
            $tanggal = $request->input('tanggal'); // Backward compatibility
            
            if (!$from || !$to) {
                if (!$tanggal) {
                    return response()->json(['error' => 'Rentang tanggal atau tanggal harus diisi'], 400);
                }
                // Fallback ke tanggal tunggal
                $from = $tanggal;
                $to = $tanggal;
            }
            
            // Ambil semua sub kategori dengan show_pos = '0'
            $subCategories = DB::table('sub_categories')
                ->where('show_pos', '0')
                ->orderBy('name')
                ->get();

            // Query untuk outlet_food_good_receives (sama dengan Report Rekap FJ)
            $query1 = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('sc.show_pos', '0')
                ->select(
                    'o.nama_outlet as customer',
                    'o.is_outlet',
                    'sc.name as sub_category',
                    DB::raw('SUM(i.received_qty * fo.price) as nilai')
                );

            // Filter tanggal
            $query1->whereDate('gr.receive_date', '>=', $from);
            $query1->whereDate('gr.receive_date', '<=', $to);
            $query1->whereNull('gr.deleted_at');
            
            $report1 = $query1
                ->groupBy('o.nama_outlet', 'o.is_outlet', 'sc.name')
                ->orderBy('o.is_outlet', 'desc')
                ->orderBy('o.nama_outlet')
                ->orderBy('sc.name')
                ->get();

            // Query untuk good_receive_outlet_suppliers (sama dengan Report Rekap FJ)
            $query2 = DB::table('good_receive_outlet_suppliers as gr')
                ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('sc.show_pos', '0')
                ->select(
                    'o.nama_outlet as customer',
                    'o.is_outlet',
                    'sc.name as sub_category',
                    DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as nilai')
                );

            // Filter tanggal
            $query2->whereDate('gr.receive_date', '>=', $from);
            $query2->whereDate('gr.receive_date', '<=', $to);
            
            $report2 = $query2
                ->groupBy('o.nama_outlet', 'o.is_outlet', 'sc.name')
                ->orderBy('o.is_outlet', 'desc')
                ->orderBy('o.nama_outlet')
                ->orderBy('sc.name')
                ->get();

            // Gabungkan kedua query
            $combinedData = $report1->concat($report2);

            // Bentuk pivot array
            $pivot = [];
            foreach ($combinedData as $row) {
                $customer = $row->customer;
                if (!isset($pivot[$customer])) {
                    $pivot[$customer] = (object)[
                        'customer' => $customer,
                        'is_outlet' => $row->is_outlet,
                        'line_total' => 0,
                    ];
                }
                $pivot[$customer]->{$row->sub_category} = ($pivot[$customer]->{$row->sub_category} ?? 0) + $row->nilai;
            }
            
            // Hitung line_total dan pastikan semua sub kategori ada kolomnya
            foreach ($pivot as $customer => $row) {
                $row->line_total = 0;
                foreach ($subCategories as $sc) {
                    if (!isset($row->{$sc->name})) {
                        $row->{$sc->name} = 0;
                    }
                    $row->line_total += $row->{$sc->name};
                }
            }

            return new SalesPivotPerOutletSubCategoryExport(array_values($pivot), $subCategories, $from . ' - ' . $to);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }

    public function reportSalesPivotSpecial(Request $request)
    {
        // Use same logic as detail: group by item first, then sum by outlet (to avoid double counting)
        // FIX: Ubah JOIN delivery_orders menjadi LEFT JOIN karena ada delivery_order yang sudah dihapus
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                'o.nama_outlet as customer',
                'o.is_outlet',
                'it.id as item_id',
                'it.name as item_name',
                'sc.name as sub_category',
                'w.name as warehouse',
                DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as item_subtotal')
            );

        if ($request->filled('from')) {
            $query->whereDate('gr.receive_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('gr.receive_date', '<=', $request->to);
        }
        
        // Filter GR yang belum dihapus
        $query->whereNull('gr.deleted_at');
        
        // Filter only items with valid warehouse (to ensure proper categorization)
        $query->whereNotNull('w.name');

        // Group by item first (like detail) to avoid double counting
        $report1Items = $query->groupBy('o.nama_outlet', 'o.is_outlet', 'it.id', 'it.name', 'sc.name', 'w.name')
            ->get();

        // Now aggregate by outlet (same logic as detail)
        $report1 = [];
        foreach ($report1Items as $item) {
            $key = $item->customer;
            if (!isset($report1[$key])) {
                $report1[$key] = (object)[
                    'customer' => $item->customer,
                    'is_outlet' => $item->is_outlet,
                    'main_kitchen' => 0,
                    'main_store' => 0,
                    'chemical' => 0,
                    'stationary' => 0,
                    'marketing' => 0,
                    'line_total' => 0
                ];
            }
            
            $subtotal = $item->item_subtotal;
            $warehouse = $item->warehouse ? trim($item->warehouse) : null;
            $subCategory = $item->sub_category ? trim($item->sub_category) : null;
            
            // Categorize by warehouse and sub-category (same logic as detail)
            if ($warehouse && in_array($warehouse, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'])) {
                $report1[$key]->main_kitchen += $subtotal;
            } elseif ($warehouse && strtoupper($warehouse) === 'MAIN STORE') {
                if ($subCategory && strtoupper($subCategory) === 'CHEMICAL') {
                    $report1[$key]->chemical += $subtotal;
                } elseif ($subCategory && strtoupper($subCategory) === 'STATIONARY') {
                    $report1[$key]->stationary += $subtotal;
                } elseif ($subCategory && strtoupper($subCategory) === 'MARKETING') {
                    $report1[$key]->marketing += $subtotal;
                } else {
                    $report1[$key]->main_store += $subtotal;
                }
            } else {
                // If warehouse is null or doesn't match, still add to line_total but don't categorize
                // This shouldn't happen normally, but handle it gracefully
            }
            
            $report1[$key]->line_total += $subtotal;
        }
        
        $report1 = collect(array_values($report1))->sortByDesc('is_outlet')->sortBy('customer')->values();

        // Convert report1 to outletData format (only GR, no GR Supplier to match detail calculation)
        $outletData = [];
        foreach ($report1 as $row) {
            $key = $row->customer;
            if (!isset($outletData[$key])) {
                $outletData[$key] = [
                    'customer' => $row->customer,
                    'is_outlet' => $row->is_outlet,
                    'main_kitchen' => 0,
                    'main_store' => 0,
                    'chemical' => 0,
                    'stationary' => 0,
                    'marketing' => 0,
                    'line_total' => 0
                ];
            }
            $outletData[$key]['main_kitchen'] += $row->main_kitchen;
            $outletData[$key]['main_store'] += $row->main_store;
            $outletData[$key]['chemical'] += $row->chemical;
            $outletData[$key]['stationary'] += $row->stationary;
            $outletData[$key]['marketing'] += $row->marketing;
            $outletData[$key]['line_total'] += $row->line_total;
        }
        
        // Convert to objects
        $combinedReport = collect();
        foreach ($outletData as $outlet) {
            $obj = new \stdClass();
            $obj->customer = $outlet['customer'];
            $obj->is_outlet = $outlet['is_outlet'];
            $obj->main_kitchen = $outlet['main_kitchen'];
            $obj->main_store = $outlet['main_store'];
            $obj->chemical = $outlet['chemical'];
            $obj->stationary = $outlet['stationary'];
            $obj->marketing = $outlet['marketing'];
            $obj->line_total = $outlet['line_total'];
            $combinedReport->push($obj);
        }

        // Get all active outlets (status='A' and is_outlet=1)
        $allActiveOutlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->where('is_outlet', 1)
            ->select('nama_outlet as customer', 'is_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Merge data to include all active outlets
        $mergedReport = collect();
        
        // Group data outlet
        $outletData = $combinedReport->keyBy('customer');
        
        // Process all active outlets
        foreach ($allActiveOutlets as $outlet) {
            $outletRow = $outletData->get($outlet->customer);
            
            $mergedRow = new \stdClass();
            $mergedRow->customer = $outlet->customer;
            $mergedRow->is_outlet = $outlet->is_outlet;
            
            // Use data if exists, otherwise use 0
            $mergedRow->main_kitchen = $outletRow ? $outletRow->main_kitchen : 0;
            $mergedRow->main_store = $outletRow ? $outletRow->main_store : 0;
            $mergedRow->chemical = $outletRow ? $outletRow->chemical : 0;
            $mergedRow->stationary = $outletRow ? $outletRow->stationary : 0;
            $mergedRow->marketing = $outletRow ? $outletRow->marketing : 0;
            $mergedRow->line_total = $outletRow ? $outletRow->line_total : 0;
            
            $mergedReport->push($mergedRow);
        }
        
        // Sort the merged report
        $report = $mergedReport->sortByDesc('is_outlet')->sortBy('customer')->values();

        // Query untuk retail warehouse sales dengan filter yang benar
        // Gunakan warehouse dari item (warehouse_division), bukan dari rws.warehouse_id
        $retailQuery = DB::table('retail_warehouse_sales as rws')
            ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwsi.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNotNull('w.name') // Filter only items with valid warehouse (to ensure proper categorization)
            ->select(
                'c.name as customer',
                DB::raw("SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN rwsi.subtotal ELSE 0 END) as main_kitchen"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN rwsi.subtotal ELSE 0 END) as main_store"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN rwsi.subtotal ELSE 0 END) as chemical"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN rwsi.subtotal ELSE 0 END) as stationary"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN rwsi.subtotal ELSE 0 END) as marketing"),
                DB::raw("SUM(rwsi.subtotal) as line_total")
            );

        if ($request->filled('from')) {
            $retailQuery->whereDate('rws.created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $retailQuery->whereDate('rws.created_at', '<=', $request->to);
        }

        $retailReport = $retailQuery->groupBy('c.name')
            ->orderBy('c.name')
            ->get();

        // Query untuk warehouse sales (penjualan antar gudang)
        $warehouseQuery = DB::table('warehouse_sales as ws')
            ->join('warehouse_sale_items as wsi', 'ws.id', '=', 'wsi.warehouse_sale_id')
            ->join('warehouses as w', 'ws.target_warehouse_id', '=', 'w.id')
            ->join('items as it', 'wsi.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->join('warehouses as w_source', 'wd.warehouse_id', '=', 'w_source.id')
            ->select(
                'w.name as customer',
                DB::raw("SUM(CASE WHEN w_source.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN wsi.total ELSE 0 END) as main_kitchen"),
                DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN wsi.total ELSE 0 END) as main_store"),
                DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN wsi.total ELSE 0 END) as chemical"),
                DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN wsi.total ELSE 0 END) as stationary"),
                DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN wsi.total ELSE 0 END) as marketing"),
                DB::raw("SUM(wsi.total) as line_total")
            );

        if ($request->filled('from')) {
            $warehouseQuery->whereDate('ws.date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $warehouseQuery->whereDate('ws.date', '<=', $request->to);
        }
        
        // Filter warehouse sales yang belum dihapus
        $warehouseQuery->whereNull('ws.deleted_at');

        $warehouseReport = $warehouseQuery->groupBy('w.name')
            ->orderBy('w.name')
            ->get();

        return Inertia::render('Report/ReportSalesPivotSpecial', [
            'report' => $report,
            'retailReport' => $retailReport,
            'warehouseReport' => $warehouseReport,
            'filters' => [
                'from' => $request->from,
                'to' => $request->to,
            ],
        ]);
    }

    public function exportSalesPivotSpecial(Request $request)
    {
        try {
            $from = $request->input('from');
            $to = $request->input('to');
            
            if (!$from || !$to) {
                return response()->json(['error' => 'Rentang tanggal harus diisi'], 400);
            }
            
            // Use EXACTLY the same logic as reportSalesPivotSpecial method
            // Use same logic as detail: group by item first, then sum by outlet (to avoid double counting)
            // FIX: Ubah JOIN delivery_orders menjadi LEFT JOIN karena ada delivery_order yang sudah dihapus
            $query = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->select(
                    'o.nama_outlet as customer',
                    'o.is_outlet',
                    'it.id as item_id',
                    'it.name as item_name',
                    'sc.name as sub_category',
                    'w.name as warehouse',
                    DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as item_subtotal')
                );

            if ($request->filled('from')) {
                $query->whereDate('gr.receive_date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $query->whereDate('gr.receive_date', '<=', $request->to);
            }
            
            // Filter GR yang belum dihapus
            $query->whereNull('gr.deleted_at');
            
            // Filter only items with valid warehouse (to ensure proper categorization)
            $query->whereNotNull('w.name');

            // Group by item first (like detail) to avoid double counting
            $report1Items = $query->groupBy('o.nama_outlet', 'o.is_outlet', 'it.id', 'it.name', 'sc.name', 'w.name')
                ->get();

            // Now aggregate by outlet (same logic as detail)
            $report1 = [];
            foreach ($report1Items as $item) {
                $key = $item->customer;
                if (!isset($report1[$key])) {
                    $report1[$key] = (object)[
                        'customer' => $item->customer,
                        'is_outlet' => $item->is_outlet,
                        'main_kitchen' => 0,
                        'main_store' => 0,
                        'chemical' => 0,
                        'stationary' => 0,
                        'marketing' => 0,
                        'line_total' => 0
                    ];
                }
                
                $subtotal = $item->item_subtotal;
                $warehouse = $item->warehouse ? trim($item->warehouse) : null;
                $subCategory = $item->sub_category ? trim($item->sub_category) : null;
                
                // Categorize by warehouse and sub-category (same logic as detail)
                if ($warehouse && in_array($warehouse, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'])) {
                    $report1[$key]->main_kitchen += $subtotal;
                } elseif ($warehouse && strtoupper($warehouse) === 'MAIN STORE') {
                    if ($subCategory && strtoupper($subCategory) === 'CHEMICAL') {
                        $report1[$key]->chemical += $subtotal;
                    } elseif ($subCategory && strtoupper($subCategory) === 'STATIONARY') {
                        $report1[$key]->stationary += $subtotal;
                    } elseif ($subCategory && strtoupper($subCategory) === 'MARKETING') {
                        $report1[$key]->marketing += $subtotal;
                    } else {
                        $report1[$key]->main_store += $subtotal;
                    }
                } else {
                    // If warehouse is null or doesn't match, still add to line_total but don't categorize
                    // This shouldn't happen normally, but handle it gracefully
                }
                
                $report1[$key]->line_total += $subtotal;
            }
            
            $report1 = collect(array_values($report1))->sortByDesc('is_outlet')->sortBy('customer')->values();

            // Convert report1 to outletData format (only GR, no GR Supplier to match detail calculation)
            $outletData = [];
            foreach ($report1 as $row) {
                $key = $row->customer;
                if (!isset($outletData[$key])) {
                    $outletData[$key] = [
                        'customer' => $row->customer,
                        'is_outlet' => $row->is_outlet,
                        'main_kitchen' => 0,
                        'main_store' => 0,
                        'chemical' => 0,
                        'stationary' => 0,
                        'marketing' => 0,
                        'line_total' => 0
                    ];
                }
                $outletData[$key]['main_kitchen'] += $row->main_kitchen;
                $outletData[$key]['main_store'] += $row->main_store;
                $outletData[$key]['chemical'] += $row->chemical;
                $outletData[$key]['stationary'] += $row->stationary;
                $outletData[$key]['marketing'] += $row->marketing;
                $outletData[$key]['line_total'] += $row->line_total;
            }
            
            // Convert to objects
            $combinedReport = collect();
            foreach ($outletData as $outlet) {
                $obj = new \stdClass();
                $obj->customer = $outlet['customer'];
                $obj->is_outlet = $outlet['is_outlet'];
                $obj->main_kitchen = $outlet['main_kitchen'];
                $obj->main_store = $outlet['main_store'];
                $obj->chemical = $outlet['chemical'];
                $obj->stationary = $outlet['stationary'];
                $obj->marketing = $outlet['marketing'];
                $obj->line_total = $outlet['line_total'];
                $combinedReport->push($obj);
            }

            // Get all active outlets (status='A' and is_outlet=1)
            $allActiveOutlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->select('nama_outlet as customer', 'is_outlet')
                ->orderBy('nama_outlet')
                ->get();

            // Merge data to include all active outlets
            $mergedReport = collect();
            
            // Group data outlet
            $outletData = $combinedReport->keyBy('customer');
            
            // Process all active outlets
            foreach ($allActiveOutlets as $outlet) {
                $outletRow = $outletData->get($outlet->customer);
                
                $mergedRow = new \stdClass();
                $mergedRow->customer = $outlet->customer;
                $mergedRow->is_outlet = $outlet->is_outlet;
                
                // Use data if exists, otherwise use 0
                $mergedRow->main_kitchen = $outletRow ? $outletRow->main_kitchen : 0;
                $mergedRow->main_store = $outletRow ? $outletRow->main_store : 0;
                $mergedRow->chemical = $outletRow ? $outletRow->chemical : 0;
                $mergedRow->stationary = $outletRow ? $outletRow->stationary : 0;
                $mergedRow->marketing = $outletRow ? $outletRow->marketing : 0;
                $mergedRow->line_total = $outletRow ? $outletRow->line_total : 0;
                
                $mergedReport->push($mergedRow);
            }
            
            // Sort the merged report
            $report = $mergedReport->sortByDesc('is_outlet')->sortBy('customer')->values();

            // Query untuk retail warehouse sales dengan filter yang benar
            // Gunakan warehouse dari item (warehouse_division), bukan dari rws.warehouse_id
            $retailQuery = DB::table('retail_warehouse_sales as rws')
                ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->join('items as it', 'rwsi.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->whereNotNull('w.name') // Filter only items with valid warehouse (to ensure proper categorization)
                ->select(
                    'c.name as customer',
                    DB::raw("SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN rwsi.subtotal ELSE 0 END) as main_kitchen"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN rwsi.subtotal ELSE 0 END) as main_store"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN rwsi.subtotal ELSE 0 END) as chemical"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN rwsi.subtotal ELSE 0 END) as stationary"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN rwsi.subtotal ELSE 0 END) as marketing"),
                    DB::raw("SUM(rwsi.subtotal) as line_total")
                );

            if ($request->filled('from')) {
                $retailQuery->whereDate('rws.created_at', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $retailQuery->whereDate('rws.created_at', '<=', $request->to);
            }

            $retailReport = $retailQuery->groupBy('c.name')
                ->orderBy('c.name')
                ->get();

            // Query untuk warehouse sales (penjualan antar gudang)
            $warehouseQuery = DB::table('warehouse_sales as ws')
                ->join('warehouse_sale_items as wsi', 'ws.id', '=', 'wsi.warehouse_sale_id')
                ->join('warehouses as w', 'ws.target_warehouse_id', '=', 'w.id')
                ->join('items as it', 'wsi.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->join('warehouses as w_source', 'wd.warehouse_id', '=', 'w_source.id')
                ->select(
                    'w.name as customer',
                    DB::raw("SUM(CASE WHEN w_source.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN wsi.total ELSE 0 END) as main_kitchen"),
                    DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN wsi.total ELSE 0 END) as main_store"),
                    DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN wsi.total ELSE 0 END) as chemical"),
                    DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN wsi.total ELSE 0 END) as stationary"),
                    DB::raw("SUM(CASE WHEN w_source.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN wsi.total ELSE 0 END) as marketing"),
                    DB::raw("SUM(wsi.total) as line_total")
                );

            if ($request->filled('from')) {
                $warehouseQuery->whereDate('ws.date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $warehouseQuery->whereDate('ws.date', '<=', $request->to);
            }
            
            // Filter warehouse sales yang belum dihapus
            $warehouseQuery->whereNull('ws.deleted_at');

            $warehouseReport = $warehouseQuery->groupBy('w.name')
                ->orderBy('w.name')
                ->get();

            return new SalesPivotSpecialExport($report, $from . ' - ' . $to, $retailReport, $warehouseReport);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }

    public function reportGoodReceiveOutlet(Request $request)
    {
        // Wajib pilih tanggal
        if (!$request->filled('tanggal')) {
            return Inertia::render('Report/ReportGoodReceiveOutlet', [
                'outlets' => [],
                'items' => [],
                'filters' => [
                    'tanggal' => $request->tanggal,
                ],
            ]);
        }

        $tanggal = $request->tanggal;

        // Ambil semua outlet aktif
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();

        // Ambil data GR per item, unit, outlet pada tanggal (dari outlet_food_good_receives)
        $data1 = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereDate('gr.receive_date', $tanggal)
            ->whereNull('gr.deleted_at')
            ->select(
                'it.id as item_id',
                'it.name as item_name',
                'u.name as unit_name',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('SUM(i.received_qty) as qty'),
                DB::raw("'outlet' as source")
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

        // Ambil data GR per item, unit, outlet pada tanggal (dari good_receive_outlet_suppliers)
        $data2 = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereDate('gr.receive_date', $tanggal)
            ->select(
                'it.id as item_id',
                'it.name as item_name',
                'u.name as unit_name',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('SUM(i.qty_received) as qty'),
                DB::raw("'supplier' as source")
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

        // Gabungkan kedua data
        $data = $data1->concat($data2);

        // Bentuk pivot: items = [{item_name, unit_name, outlet_name => qty, ...}]
        $pivot = [];
        foreach ($data as $row) {
            $key = $row->item_id . '|' . $row->unit_name;
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'item_name' => $row->item_name,
                    'unit_name' => $row->unit_name,
                ];
            }
            // Jika sudah ada data untuk outlet ini, tambahkan qty
            if (isset($pivot[$key][$row->nama_outlet])) {
                $pivot[$key][$row->nama_outlet] += $row->qty;
            } else {
                $pivot[$key][$row->nama_outlet] = $row->qty;
            }
        }

        return Inertia::render('Report/ReportGoodReceiveOutlet', [
            'outlets' => $outlets,
            'items' => array_values($pivot),
            'filters' => [
                'tanggal' => $tanggal,
            ],
        ]);
    }

    public function exportGoodReceiveOutlet(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        $tanggal = $request->tanggal;

        // Ambil semua outlet aktif
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();

        // Ambil data GR per item, unit, outlet pada tanggal (dari outlet_food_good_receives)
        $data1 = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereDate('gr.receive_date', $tanggal)
            ->whereNull('gr.deleted_at')
            ->select(
                'it.id as item_id',
                'it.name as item_name',
                'u.name as unit_name',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('SUM(i.received_qty) as qty'),
                DB::raw("'outlet' as source")
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

        // Ambil data GR per item, unit, outlet pada tanggal (dari good_receive_outlet_suppliers)
        $data2 = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereDate('gr.receive_date', $tanggal)
            ->select(
                'it.id as item_id',
                'it.name as item_name',
                'u.name as unit_name',
                'o.id_outlet',
                'o.nama_outlet',
                DB::raw('SUM(i.qty_received) as qty'),
                DB::raw("'supplier' as source")
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

        // Gabungkan kedua data
        $data = $data1->concat($data2);

        // Bentuk pivot: items = [{item_name, unit_name, outlet_name => qty, ...}]
        $pivot = [];
        foreach ($data as $row) {
            $key = $row->item_id . '|' . $row->unit_name;
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'item_name' => $row->item_name,
                    'unit_name' => $row->unit_name,
                ];
            }
            // Jika sudah ada data untuk outlet ini, tambahkan qty
            if (isset($pivot[$key][$row->nama_outlet])) {
                $pivot[$key][$row->nama_outlet] += $row->qty;
            } else {
                $pivot[$key][$row->nama_outlet] = $row->qty;
            }
        }

        $items = array_values($pivot);

        // Prepare data for export
        $exportData = [];
        foreach ($items as $item) {
            $row = [
                'Nama Items' => $item['item_name'],
                'Unit' => $item['unit_name'],
            ];
            
            foreach ($outlets as $outlet) {
                $row[$outlet->nama_outlet] = isset($item[$outlet->nama_outlet]) ? number_format($item[$outlet->nama_outlet], 2) : '';
            }
            
            $exportData[] = $row;
        }

        $filename = 'Report_Good_Receive_Outlet_' . date('Y-m-d', strtotime($tanggal)) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\GoodReceiveOutletExport($exportData, $outlets),
            $filename
        );
    }

    public function salesPivotOutletDetail(Request $request)
    {
        $request->validate([
            'outlet' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        
        // Query untuk outlet_food_good_receives (sama dengan Report Rekap FJ)
        $query1 = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('o.nama_outlet', $request->outlet)
            ->whereDate('gr.receive_date', '>=', $request->from)
            ->whereDate('gr.receive_date', '<=', $request->to)
            ->whereNull('gr.deleted_at') // Filter GR yang belum dihapus
            ->select(
                'cat.name as category',
                'sc.name as sub_category',
                'it.name as item_name',
                'i.received_qty',
                'u.name as unit',
                'fo.price',
                DB::raw('(i.received_qty * fo.price) as subtotal')
            )
            ->orderBy('cat.name')
            ->orderBy('sc.name')
            ->orderBy('it.name')
            ->get();
            
        // Query untuk good_receive_outlet_suppliers (sama dengan Report Rekap FJ)
        $query2 = DB::table('good_receive_outlet_suppliers as gr')
            ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('o.nama_outlet', $request->outlet)
            ->whereDate('gr.receive_date', '>=', $request->from)
            ->whereDate('gr.receive_date', '<=', $request->to)
            ->select(
                'cat.name as category',
                'sc.name as sub_category',
                'it.name as item_name',
                'i.qty_received as received_qty',
                'u.name as unit',
                DB::raw('COALESCE(fo.price, 0) as price'),
                DB::raw('(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
            )
            ->orderBy('cat.name')
            ->orderBy('sc.name')
            ->orderBy('it.name')
            ->get();
            
        // Gabungkan kedua query
        $allItems = $query1->concat($query2);
        
        // Group by sub_category
        $grouped = [];
        foreach ($allItems as $item) {
            $subCat = $item->sub_category;
            if (!isset($grouped[$subCat])) $grouped[$subCat] = [];
            $grouped[$subCat][] = $item;
        }
        
        return response()->json($grouped);
    }

    /**
     * Retail Sales Detail: Shows detailed items for retail warehouse sales
     */
    public function retailSalesDetail(Request $request)
    {
        $request->validate([
            'customer' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $items = DB::table('retail_warehouse_sales as rws')
            ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->join('items as it', 'rwsi.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('c.name', $request->customer)
            ->whereDate('rws.created_at', '>=', $request->from)
            ->whereDate('rws.created_at', '<=', $request->to)
            ->select(
                'cat.name as category',
                'sc.name as sub_category',
                'it.name as item_name',
                'rwsi.qty',
                'rwsi.unit',
                'rwsi.price',
                'rwsi.subtotal',
                'rws.number as sale_number',
                'rws.created_at as sale_date'
            )
            ->orderBy('cat.name')
            ->orderBy('sc.name')
            ->orderBy('it.name')
            ->get();

        // Group by sub_category
        $grouped = [];
        foreach ($items as $item) {
            $subCat = $item->sub_category;
            if (!isset($grouped[$subCat])) $grouped[$subCat] = [];
            $grouped[$subCat][] = $item;
        }
        
        return response()->json($grouped);
    }

    /**
     * Warehouse Sales Detail: Shows detailed items for warehouse sales
     */
    public function warehouseSalesDetail(Request $request)
    {
        $request->validate([
            'customer' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $items = DB::table('warehouse_sales as ws')
            ->join('warehouse_sale_items as wsi', 'ws.id', '=', 'wsi.warehouse_sale_id')
            ->join('warehouses as w', 'ws.target_warehouse_id', '=', 'w.id')
            ->join('items as it', 'wsi.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->where('w.name', $request->customer)
            ->whereDate('ws.date', '>=', $request->from)
            ->whereDate('ws.date', '<=', $request->to)
            ->whereNull('ws.deleted_at') // Filter warehouse sales yang belum dihapus
            ->select(
                'sc.name as category',
                'sc.name as sub_category',
                'it.name as item_name',
                'wsi.qty_small',
                'wsi.qty_medium',
                'wsi.qty_large',
                'wsi.price',
                'wsi.total',
                'ws.number as sale_number',
                'ws.date as sale_date'
            )
            ->orderBy('sc.name')
            ->orderBy('it.name')
            ->get();

        // Group by sub_category
        $grouped = [];
        foreach ($items as $item) {
            $subCat = $item->sub_category;
            if (!isset($grouped[$subCat])) $grouped[$subCat] = [];
            $grouped[$subCat][] = $item;
        }
        
        return response()->json($grouped);
    }

    /**
     * Sales Report Simple: filter by outlet and date range
     */
    public function reportSalesSimple(Request $request)
    {
        $outlet = $request->input('outlet');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

       // \Log::info('DEBUG FILTER', [
       //     'outlet' => $outlet,
       //     'dateFrom' => $dateFrom,
       //     'dateTo' => $dateTo,
       // ]);

        $query = \DB::table('orders')
            ->select([
                'orders.id',
                'orders.nomor',
                'orders.table',
                'orders.pax',
                'orders.total',
                'orders.discount',
                'orders.cashback',
                'orders.dpp',
                'orders.pb1',
                'orders.service',
                'orders.commfee',
                'orders.rounding',
                'orders.grand_total',
                'orders.status',
                'orders.created_at',
                'orders.kode_outlet',
                'tdo.nama_outlet',
                'orders.manual_discount_amount',
                'orders.manual_discount_reason',
                'orders.waiters',
                'orders.mode',
            ])
            ->leftJoin('tbl_data_outlet as tdo', 'orders.kode_outlet', '=', 'tdo.qr_code');

        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        }
        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }
        // Opsional: filter status jika dikirim
        if ($request->has('status') && $request->status) {
            $query->where('orders.status', $request->status);
        }

        $orders = $query->orderBy('orders.created_at')->get();
        //\Log::info('DEBUG ORDERS COUNT', ['count' => $orders->count()]);
        

        
        // DEBUG: Log sample orders dengan commfee
        $sampleOrders = $orders->take(5);
       // \Log::info('DEBUG SAMPLE ORDERS', [
         //   'sample_orders' => $sampleOrders->map(function($order) {
         //       return [
         //           'id' => $order->id,
         //           'nomor' => $order->nomor,
         //           'commfee' => $order->commfee,
         //           'rounding' => $order->rounding,
         //           'grand_total' => $order->grand_total,
         //       ];
         //   })->toArray()
        //]);
        
        // DEBUG: Log total commfee dan rounding
       // \Log::info('DEBUG COMMFEE ROUNDING TOTALS', [
       //     'total_commfee' => $orders->sum('commfee'),
       //     'total_rounding' => $orders->sum('rounding'),
       //     'orders_with_commfee' => $orders->where('commfee', '>', 0)->count(),
      //      'orders_with_rounding' => $orders->where('rounding', '>', 0)->count(),
     //   ]);

        // Tambahkan items dan promo ke setiap order
        foreach ($orders as $order) {
            // Items
            $order->items = \DB::table('order_items')
                ->leftJoin('items', 'order_items.item_id', '=', 'items.id')
                ->where('order_items.order_id', $order->id)
                ->select([
                    'order_items.id',
                    'order_items.item_id',
                    'items.name as item_name',
                    'order_items.qty',
                    'order_items.price',
                    'order_items.subtotal',
                    'order_items.modifiers',
                    'order_items.notes'
                ])
                ->get();
            // Promo
            $promo = \DB::table('order_promos as op')
                ->join('promos as p', 'op.promo_id', '=', 'p.id')
                ->where('op.order_id', $order->id)
                ->select('p.id', 'p.name', 'p.code', 'p.type', 'p.value')
                ->first();
            $order->promo = $promo;
            // Payments
            $order->payments = \DB::table('order_payment')
                ->where('order_id', $order->id)
                ->select(['payment_code', 'payment_type', 'amount', 'change'])
                ->get();
        }

        // Summary
        $summary = [
            // 1. Sales (+): sum(total) from orders
            'total_sales' => $orders->sum('total'),
            // 2. Disc (-): sum(discount atau manual_discount_amount, tidak keduanya) from orders
            'total_discount' => $orders->sum(function($order) {
                $discount = floatval($order->discount ?? 0);
                $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                
                // Debug log
              //  \Log::info('DEBUG DISCOUNT CALCULATION', [
              //      'order_id' => $order->id,
              //      'nomor' => $order->nomor,
             //       'discount_raw' => $order->discount,
              //      'manual_discount_raw' => $order->manual_discount_amount,
               //     'discount_parsed' => $discount,
               //     'manual_discount_parsed' => $manualDiscount
                //]);
                
                // Jika keduanya > 0, ambil yang terbesar
                if ($discount > 0 && $manualDiscount > 0) {
                    return max($discount, $manualDiscount);
                }
                // Jika hanya salah satu yang > 0, gunakan yang ada
                return $discount + $manualDiscount;
            }),
            // 3. Cashback: sum(cashback) from orders
            'total_cashback' => $orders->sum('cashback'),
            // 4. Net Sales: sum(total) - sum(discount) - sum(cashback)
            'net_sales' => $orders->sum('total') - $orders->sum(function($order) {
                $discount = intval($order->discount ?? 0);
                $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                
                // Jika keduanya > 0, ambil yang terbesar
                if ($discount > 0 && $manualDiscount > 0) {
                    return max($discount, $manualDiscount);
                }
                // Jika hanya salah satu yang > 0, gunakan yang ada
                return $discount + $manualDiscount;
            }) - $orders->sum('cashback'),
            // 5. pb1: sum(pb1) from orders
            'total_pb1' => $orders->sum('pb1'),
            // 6. service: sum(service) from orders
            'total_service' => $orders->sum('service'),
            // 7. commfee: sum(commfee) from orders
            'total_commfee' => $orders->sum('commfee'),
            // 8. rounding: sum(rounding) from orders
            'total_rounding' => $orders->sum('rounding'),
            // 9. Grand total: sum(grand_total) from orders
            'grand_total' => $orders->sum('grand_total'),
            // 10. jumlah pax: sum(pax) from orders
            'total_pax' => $orders->sum('pax'),
            // 11. avg check: sum(grand_total)/sum(pax) from orders
            'avg_check' => $orders->sum('pax') > 0 ? round($orders->sum('grand_total') / $orders->sum('pax')) : 0,
            // Existing fields (if needed)
            'total_order' => $orders->count(),
            'total_promo_discount' => $orders->sum(function($order) {
                $discount = intval($order->discount ?? 0);
                $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                
                // Jika keduanya > 0, ambil yang terbesar
                if ($discount > 0 && $manualDiscount > 0) {
                    return max($discount, $manualDiscount);
                }
                // Jika hanya salah satu yang > 0, gunakan yang ada
                return $discount + $manualDiscount;
            }),
        ];

        // Breakdown per hari
        $perDay = $orders->groupBy(function($o) {
            return \Carbon\Carbon::parse($o->created_at)->format('Y-m-d');
        })->map(function($group) {
            return [
                // Sales (+): sum(total) from orders per day
                'total_sales' => $group->sum('total'),
                'total_order' => $group->count(),
                'total_pax' => $group->sum('pax'),
                'total_discount' => $group->sum(function($order) {
                    $discount = intval($order->discount ?? 0);
                    $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                    
                    // Jika keduanya > 0, ambil yang terbesar
                    if ($discount > 0 && $manualDiscount > 0) {
                        return max($discount, $manualDiscount);
                    }
                    // Jika hanya salah satu yang > 0, gunakan yang ada
                    return $discount + $manualDiscount;
                }),
                'total_cashback' => $group->sum('cashback'),
                'total_service' => $group->sum('service'),
                'total_pb1' => $group->sum('pb1'),
                'total_commfee' => $group->sum('commfee'),
                'total_rounding' => $group->sum('rounding'),
                'total_promo_discount' => $group->sum(function($order) {
                    $discount = intval($order->discount ?? 0);
                    $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                    
                    // Jika keduanya > 0, ambil yang terbesar
                    if ($discount > 0 && $manualDiscount > 0) {
                        return max($discount, $manualDiscount);
                    }
                    // Jika hanya salah satu yang > 0, gunakan yang ada
                    return $discount + $manualDiscount;
                }),
                // Tambahkan net_sales, grand_total, avg_check jika perlu
                'net_sales' => $group->sum('total') - $group->sum(function($order) {
                    $discount = intval($order->discount ?? 0);
                    $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                    
                    // Jika keduanya > 0, ambil yang terbesar
                    if ($discount > 0 && $manualDiscount > 0) {
                        return max($discount, $manualDiscount);
                    }
                    // Jika hanya salah satu yang > 0, gunakan yang ada
                    return $discount + $manualDiscount;
                }) - $group->sum('cashback'),
                'grand_total' => $group->sum('grand_total'),
                'avg_check' => $group->sum('pax') > 0 ? round($group->sum('grand_total') / $group->sum('pax')) : 0,
            ];
        });

        return response()->json([
            'summary' => $summary,
            'per_day' => $perDay,
            'orders' => $orders,
        ]);
    }

    /**
     * API: Get all active outlets (for dropdown)
     */
    public function apiOutlets()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json(['error' => 'User tidak terautentikasi'], 401);
            }
            
            // Cek apakah tabel exists
            if (!\Schema::hasTable('tbl_data_outlet')) {
                \Log::error('Table tbl_data_outlet does not exist');
                return response()->json(['error' => 'Tabel outlet tidak ditemukan'], 500);
            }
            
            $query = \DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->whereNotNull('nama_outlet')
                ->where('nama_outlet', '!=', '');
            
            // Jika user bukan superuser (id_outlet != 1), hanya tampilkan outlet mereka sendiri
            if ($user->id_outlet != 1) {
                $query->where('id_outlet', $user->id_outlet);
            }
            
            $outlets = $query->get(['id_outlet as id', 'nama_outlet as name', 'qr_code', 'region_id']);
            
            \Log::info('apiOutlets called', [
                'user_id' => $user->id,
                'user_outlet_id' => $user->id_outlet,
                'outlets_count' => $outlets->count(),
                'outlets' => $outlets->toArray()
            ]);
            
            return response()->json(['outlets' => $outlets]);
        } catch (\Exception $e) {
            \Log::error('Error in apiOutlets', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Get all regions
     */
    public function apiRegions()
    {
        try {
            $regions = \DB::table('regions')
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();
            
            return response()->json(['regions' => $regions]);
        } catch (\Exception $e) {
            \Log::error('Error in apiRegions', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Get qr_code and outlet name for current user's outlet
     */
    public function myOutletQr()
    {
        $user = auth()->user();
        $qr_code = null;
        $outlet_name = null;
        
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $user->id_outlet)->first();
            if ($outlet) {
                $qr_code = $outlet->qr_code;
                $outlet_name = $outlet->nama_outlet;
            }
        }
        
        return response()->json([
            'qr_code' => $qr_code,
            'outlet_name' => $outlet_name
        ]);
    }

    public function reportItemEngineering(Request $request)
    {
        $outlet = $request->input('outlet');
        $region = $request->input('region');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Query untuk items dengan category
        $query = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select([
                'order_items.item_name',
                'categories.name as category_name',
                \DB::raw('SUM(order_items.qty) as qty_terjual'),
                \DB::raw('MAX(order_items.price) as harga_jual'),
                \DB::raw('SUM(order_items.qty * order_items.price) as subtotal'),
            ]);
        
        // Filter by outlet or region
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        } elseif ($region) {
            // If region is selected, get all outlets in that region
            $outletCodes = \DB::table('tbl_data_outlet')
                ->where('region_id', $region)
                ->pluck('qr_code');
            $query->whereIn('orders.kode_outlet', $outletCodes);
        }
        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }
        $query->groupBy('order_items.item_name', 'categories.name')
            ->orderBy('categories.name')
            ->orderByDesc('qty_terjual');

        $items = $query->get();
        $grand_total = $items->sum('subtotal');

        // Group items by category
        $itemsByCategory = $items->groupBy('category_name')->map(function($categoryItems) {
            return [
                'items' => $categoryItems,
                'total_qty' => $categoryItems->sum('qty_terjual'),
                'total_subtotal' => $categoryItems->sum('subtotal'),
            ];
        });

        // MODIFIER ENGINEERING
        $orderItems = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(['order_items.modifiers', 'order_items.qty'])
            ->when($outlet, function($q) use ($outlet) {
                $q->where('orders.kode_outlet', $outlet);
            })
            ->when($region && !$outlet, function($q) use ($region) {
                // If region is selected and no specific outlet, get all outlets in that region
                $outletCodes = \DB::table('tbl_data_outlet')
                    ->where('region_id', $region)
                    ->pluck('qr_code');
                $q->whereIn('orders.kode_outlet', $outletCodes);
            })
            ->when($dateFrom, function($q) use ($dateFrom) {
                $q->whereDate('orders.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($q) use ($dateTo) {
                $q->whereDate('orders.created_at', '<=', $dateTo);
            })
            ->get();
        $modifierMap = [];
        foreach ($orderItems as $oi) {
            if (!$oi->modifiers) continue;
            $mods = json_decode($oi->modifiers, true);
            if (!is_array($mods)) continue;
            foreach ($mods as $group) {
                if (is_array($group)) {
                    foreach ($group as $name => $qty) {
                        if (!isset($modifierMap[$name])) $modifierMap[$name] = 0;
                        $modifierMap[$name] += $qty;
                    }
                }
            }
        }
        $modifiers = [];
        foreach ($modifierMap as $name => $qty) {
            $modifiers[] = [ 'name' => $name, 'qty' => $qty ];
        }
        usort($modifiers, function($a, $b) { return $b['qty'] <=> $a['qty']; });

        return response()->json([
            'items' => $items, 
            'items_by_category' => $itemsByCategory,
            'modifiers' => $modifiers, 
            'grand_total' => $grand_total
        ]);
    }

    /**
     * Receiving Sheet Report: Shows daily cost vs sales comparison
     */
    public function reportReceivingSheet(Request $request)
    {
        $outlet = $request->input('outlet');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get user's outlet if not HO user
        $user = auth()->user();
        if ($user->id_outlet != 1) {
            $outlet = $user->id_outlet;
        }

        // Get outlet QR code for sales query
        $outletQrCode = null;
        if ($outlet) {
            $outletQrCode = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outlet)
                ->value('qr_code');
        }

        // Query for cost data (GR items with floor order prices)
        $costQuery = DB::table('outlet_food_good_receives as ofgr')
            ->join('outlet_food_good_receive_items as ofgri', 'ofgr.id', '=', 'ofgri.outlet_food_good_receive_id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as fpl', 'do.packing_list_id', '=', 'fpl.id')
            ->join('food_floor_orders as ffo', 'fpl.food_floor_order_id', '=', 'ffo.id')
            ->join('food_floor_order_items as ffoi', function($join) {
                $join->on('ffoi.floor_order_id', '=', 'ffo.id')
                     ->on('ffoi.item_id', '=', 'ofgri.item_id');
            })
            ->select(
                'ofgr.receive_date as tanggal',
                DB::raw('SUM(ofgri.received_qty * ffoi.price) as cost')
            );

        // Apply filters
        if ($outlet) {
            $costQuery->where('ofgr.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $costQuery->whereDate('ofgr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $costQuery->whereDate('ofgr.receive_date', '<=', $dateTo);
        }

        $costData = $costQuery
            ->whereNull('ofgr.deleted_at')
            ->groupBy('ofgr.receive_date')
            ->get()
            ->keyBy('tanggal');

        // Query for retail_food cost (per tanggal & outlet)
        $retailFoodQuery = DB::table('retail_food')
            ->select('transaction_date as tanggal', DB::raw('SUM(total_amount) as retail_cost'))
            ->where('status', 'approved')
            ->whereNull('deleted_at');
        if ($outlet) {
            $retailFoodQuery->where('outlet_id', $outlet);
        }
        if ($dateFrom) {
            $retailFoodQuery->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $retailFoodQuery->whereDate('transaction_date', '<=', $dateTo);
        }
        $retailFoodData = $retailFoodQuery
            ->groupBy('transaction_date')
            ->get()
            ->keyBy('tanggal');

        // Query for sales data (daily total from orders)
        $salesQuery = DB::table('orders')
            ->select(
                DB::raw('DATE(created_at) as tanggal'),
                DB::raw('SUM(grand_total) as omzet')
            );

        // Apply filters
        if ($outletQrCode) {
            $salesQuery->where('kode_outlet', $outletQrCode);
        }
        if ($dateFrom) {
            $salesQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('created_at', '<=', $dateTo);
        }

        $salesData = $salesQuery
            ->where('status', 'paid')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('tanggal');

        // Query for pembelanjaan ke supplier langsung (per tanggal & outlet)
        $supplierDirectQuery = DB::table('good_receive_outlet_supplier_items as gri')
            ->join('good_receive_outlet_suppliers as gr', 'gri.good_receive_id', '=', 'gr.id')
            ->select('gr.receive_date as tanggal', DB::raw('SUM(gri.qty_received * gri.price) as supplier_cost'));
        if ($outlet) {
            $supplierDirectQuery->where('gr.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $supplierDirectQuery->whereDate('gr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $supplierDirectQuery->whereDate('gr.receive_date', '<=', $dateTo);
        }
        $supplierDirectData = $supplierDirectQuery
            ->groupBy('gr.receive_date')
            ->get()
            ->keyBy('tanggal');

        // Query pembelanjaan per warehouse per tanggal
        $warehouseSpendQuery = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as fpl', 'do.packing_list_id', '=', 'fpl.id')
            ->join('food_floor_orders as ffo', 'fpl.food_floor_order_id', '=', 'ffo.id')
            ->join('food_floor_order_items as ffoi', function($join) {
                $join->on('ffoi.floor_order_id', '=', 'ffo.id')
                     ->on('ffoi.item_id', '=', 'ofgri.item_id');
            })
            ->join('warehouses as w', 'ofgr.warehouse_outlet_id', '=', 'w.id')
            ->select(
                'ofgr.receive_date as tanggal',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                DB::raw('SUM(ofgri.received_qty * ffoi.price) as total')
            );
        if ($outlet) {
            $warehouseSpendQuery->where('ofgr.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $warehouseSpendQuery->whereDate('ofgr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $warehouseSpendQuery->whereDate('ofgr.receive_date', '<=', $dateTo);
        }
        $warehouseSpendData = $warehouseSpendQuery
            ->whereNull('ofgr.deleted_at')
            ->groupBy('ofgr.receive_date', 'w.id', 'w.name')
            ->get();

        // Ambil daftar warehouse yang muncul di data
        $warehouses = $warehouseSpendData->map(function($row) {
            return [
                'id' => $row->warehouse_id,
                'name' => $row->warehouse_name
            ];
        })->unique('id')->values();

        // Index warehouse spend per tanggal per warehouse_id
        $warehouseSpendByDate = [];
        foreach ($warehouseSpendData as $row) {
            $date = $row->tanggal;
            $wid = $row->warehouse_id;
            if (!isset($warehouseSpendByDate[$date])) $warehouseSpendByDate[$date] = [];
            $warehouseSpendByDate[$date][$wid] = $row->total;
        }

        // Query pembelanjaan per supplier per tanggal
        $supplierSpendQuery = DB::table('good_receive_outlet_supplier_items as gri')
            ->join('good_receive_outlet_suppliers as gr', 'gri.good_receive_id', '=', 'gr.id')
            ->join('suppliers as s', 'gr.ro_supplier_id', '=', 's.id')
            ->select('gr.receive_date as tanggal', 's.id as supplier_id', 's.name as supplier_name', DB::raw('SUM(gri.qty_received * gri.price) as total'));
        if ($outlet) {
            $supplierSpendQuery->where('gr.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $supplierSpendQuery->whereDate('gr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $supplierSpendQuery->whereDate('gr.receive_date', '<=', $dateTo);
        }
        $supplierSpendData = $supplierSpendQuery
            ->groupBy('gr.receive_date', 's.id', 's.name')
            ->get();

        // Ambil daftar supplier yang muncul di data
        $suppliers = $supplierSpendData->map(function($row) {
            return [
                'id' => $row->supplier_id,
                'name' => $row->supplier_name
            ];
        })->unique('id')->values();

        // Index supplier spend per tanggal per supplier_id
        $supplierSpendByDate = [];
        foreach ($supplierSpendData as $row) {
            $date = $row->tanggal;
            $sid = $row->supplier_id;
            if (!isset($supplierSpendByDate[$date])) $supplierSpendByDate[$date] = [];
            $supplierSpendByDate[$date][$sid] = $row->total;
        }

        // Combine data and calculate percentage
        $report = [];
        $allDates = collect($costData->keys())
            ->merge($salesData->keys())
            ->merge($retailFoodData->keys())
            ->merge($supplierDirectData->keys())
            ->merge(collect($warehouseSpendByDate)->keys())
            ->merge(collect($supplierSpendByDate)->keys())
            ->unique()->sort();

        foreach ($allDates as $date) {
            $cost = ($costData->get($date)?->cost ?? 0)
                + ($retailFoodData->get($date)?->retail_cost ?? 0)
                + ($supplierDirectData->get($date)?->supplier_cost ?? 0);
            $omzet = $salesData->get($date)?->omzet ?? 0;
            $persentase = $omzet > 0 ? ($cost / $omzet) * 100 : 0;
            $row = [
                'tanggal' => $date,
                'omzet' => $omzet,
                'persentase_cost' => round($persentase, 2),
                'cost' => $cost,
                'retail_food' => $retailFoodData->get($date)?->retail_cost ?? 0,
                'supplier_direct' => $supplierDirectData->get($date)?->supplier_cost ?? 0,
            ];
            // Tambahkan pembelanjaan per warehouse
            foreach ($warehouses as $wh) {
                $row['warehouse_' . $wh['id']] = $warehouseSpendByDate[$date][$wh['id']] ?? 0;
            }
            // Tambahkan pembelanjaan per supplier
            foreach ($suppliers as $sp) {
                $row['supplier_' . $sp['id']] = $supplierSpendByDate[$date][$sp['id']] ?? 0;
            }
            $report[] = $row;
        }

        // Sort by date descending
        $report = collect($report)->sortByDesc('tanggal')->values();

        // Get outlets for filter
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('Report/ReceivingSheet', [
            'report' => $report,
            'outlets' => $outlets,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'filters' => [
                'outlet' => $outlet,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'user' => $user,
        ]);
    }

    public function exportOrderDetail(Request $request)
    {
        $outlet = $request->input('outlet');
        $date = $request->input('date');
        // Query orders for the given date and outlet
        $query = \DB::table('orders')
            ->select([
                'orders.id',
                'orders.nomor',
                'orders.table',
                'orders.pax',
                'orders.total',
                'orders.discount',
                'orders.cashback',
                'orders.service',
                'orders.pb1',
                'orders.grand_total',
                'orders.status',
            ]);
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        }
        if ($date) {
            $query->whereDate('orders.created_at', $date);
        }
        $orders = $query->orderBy('orders.created_at')->get();
        return new OrderDetailExport($orders, $date);
    }

    public function exportItemEngineering(Request $request)
    {
        $outlet = $request->input('outlet');
        $region = $request->input('region');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $outletName = null;
        if ($outlet) {
            $outletName = \DB::table('tbl_data_outlet')->where('qr_code', $outlet)->value('nama_outlet');
        }
        
        // Query untuk items dengan category (sama seperti reportItemEngineering)
        $query = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('items', 'order_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select([
                'order_items.item_name',
                'categories.name as category_name',
                \DB::raw('SUM(order_items.qty) as qty_terjual'),
                \DB::raw('MAX(order_items.price) as harga_jual'),
                \DB::raw('SUM(order_items.qty * order_items.price) as subtotal'),
            ]);
        // Filter by outlet or region
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
        } elseif ($region) {
            // If region is selected, get all outlets in that region
            $outletCodes = \DB::table('tbl_data_outlet')
                ->where('region_id', $region)
                ->pluck('qr_code');
            $query->whereIn('orders.kode_outlet', $outletCodes);
        }
        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }
        $query->groupBy('order_items.item_name', 'categories.name')
            ->orderBy('categories.name')
            ->orderByDesc('qty_terjual');
        $items = $query->get();
        
        // Get modifiers (same logic as in reportItemEngineering)
        $orderItems = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(['order_items.modifiers', 'order_items.qty'])
            ->when($outlet, function($q) use ($outlet) {
                $q->where('orders.kode_outlet', $outlet);
            })
            ->when($region && !$outlet, function($q) use ($region) {
                // If region is selected and no specific outlet, get all outlets in that region
                $outletCodes = \DB::table('tbl_data_outlet')
                    ->where('region_id', $region)
                    ->pluck('qr_code');
                $q->whereIn('orders.kode_outlet', $outletCodes);
            })
            ->when($dateFrom, function($q) use ($dateFrom) {
                $q->whereDate('orders.created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($q) use ($dateTo) {
                $q->whereDate('orders.created_at', '<=', $dateTo);
            })
            ->get();
        $modifierMap = [];
        foreach ($orderItems as $oi) {
            if (!$oi->modifiers) continue;
            $mods = json_decode($oi->modifiers, true);
            if (!is_array($mods)) continue;
            foreach ($mods as $group) {
                if (is_array($group)) {
                    foreach ($group as $name => $qty) {
                        if (!isset($modifierMap[$name])) $modifierMap[$name] = 0;
                        $modifierMap[$name] += $qty;
                    }
                }
            }
        }
        $modifiers = [];
        foreach ($modifierMap as $name => $qty) {
            $modifiers[] = [ 'name' => $name, 'qty' => $qty ];
        }
        usort($modifiers, function($a, $b) { return $b['qty'] <=> $a['qty']; });
        return new ItemEngineeringMultiSheetExport($items, $modifiers, $outletName, $dateFrom, $dateTo);
    }

    public function apiOutletExpenses(Request $request)
    {
        \Log::info('apiOutletExpenses called', [
            'outlet_id' => $request->input('outlet_id'),
            'date' => $request->input('date'),
        ]);
        
        $user = auth()->user();
        $outletId = $request->input('outlet_id');
        $date = $request->input('date');
        
        // Validasi: user hanya bisa mengakses data outlet mereka sendiri, kecuali superuser (id_outlet = 1)
        if ($user->id_outlet != 1 && $user->id_outlet != $outletId) {
            \Log::warning('apiOutletExpenses: unauthorized access attempt', [
                'user_id_outlet' => $user->id_outlet,
                'requested_outlet_id' => $outletId,
            ]);
            return response()->json([
                'retail_food' => [],
                'retail_non_food' => [],
            ]);
        }
        
        // Retail Food - hanya yang cash
        $retailFoods = \App\Models\RetailFood::with(['items', 'invoices'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->where('payment_method', 'cash')
            ->get()
            ->map(function($rf) {
                return [
                    'id' => $rf->id,
                    'retail_number' => $rf->retail_number,
                    'transaction_date' => $rf->transaction_date,
                    'total_amount' => $rf->total_amount,
                    'notes' => $rf->notes,
                    'items' => $rf->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name ?? $item->nama_barang,
                            'qty' => $item->qty,
                            'harga_barang' => $item->harga_barang,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'invoices' => $rf->invoices->map(function($inv) {
                        return [
                            'file_path' => $inv->file_path
                        ];
                    }),
                ];
            });
        // Retail Non Food - hanya yang payment_method bukan contra_bon
        $retailNonFoods = \App\Models\RetailNonFood::with(['items', 'invoices', 'categoryBudget'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->where(function($q) {
                $q->where('payment_method', '!=', 'contra_bon')
                  ->orWhereNull('payment_method');
            })
            ->get()
            ->map(function($rnf) use ($outletId) {
                $budgetInfo = null;
                
                // Get budget info if category_budget_id exists (with error handling)
                // Use same logic as Purchase Requisition Ops
                try {
                    if ($rnf->category_budget_id) {
                        $categoryId = $rnf->category_budget_id;
                        $transactionDate = \Carbon\Carbon::parse($rnf->transaction_date);
                        $year = $transactionDate->year;
                        $month = $transactionDate->month;
                        
                        // Get date range for the month
                        $dateFrom = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
                        $dateTo = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
                        
                        // Get category budget (same as Purchase Requisition Ops)
                        $category = \App\Models\PurchaseRequisitionCategory::find($categoryId);
                        
                        if ($category) {
                            // Use same logic as Purchase Requisition Ops
                            $isGlobalBudget = $category->isGlobalBudget();
                            $budgetType = $isGlobalBudget ? 'GLOBAL' : 'PER_OUTLET';
                            $categoryBudget = $category->budget_limit ?? 0;
                            
                            // Get PR IDs in this category for the month (same as Purchase Requisition Ops)
                            // Support both old structure (category at PR level) and new structure (category at items level)
                            $prIds = \DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                                ->where('pr.is_held', false)
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            // Get PO IDs linked to PRs in this category
                            $poIdsInCategory = !empty($prIds) ? \DB::table('purchase_order_ops_items as poi')
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poi.source_id', $prIds)
                                ->distinct()
                                ->pluck('poi.purchase_order_ops_id')
                                ->toArray() : [];
                            
                            // Get paid amount from non_food_payments (BUDGET IS MONTHLY - filter by payment_date)
                            // IMPORTANT: Only count NFP with status 'paid' (not 'approved')
                            $paidAmountFromPo = !empty($poIdsInCategory) ? \DB::table('non_food_payments as nfp')
                                ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                                ->where('nfp.status', 'paid')
                                ->where('nfp.status', '!=', 'cancelled')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->sum('nfp.amount') : 0;
                            
                            // Get Retail Non Food amounts (BUDGET IS MONTHLY - filter by transaction_date)
                            $retailNonFoodApproved = \App\Models\RetailNonFood::where('category_budget_id', $category->id)
                                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                                ->where('status', 'approved')
                                ->sum('total_amount');
                            
                            // Get unpaid PR data (same as Purchase Requisition Ops)
                            $prIdsForUnpaid = \DB::table('purchase_requisitions as pr')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('pr.id', '=', 'poi.source_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('non_food_payments as nfp', 'pr.id', '=', 'nfp.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED'])
                                ->where('pr.is_held', false)
                                ->whereNull('poo.id')
                                ->whereNull('nfp.id')
                                ->distinct()
                                ->pluck('pr.id')
                                ->toArray();
                            
                            $allPrs = \App\Models\PurchaseRequisition::whereIn('id', $prIdsForUnpaid)->get();
                            $prUnpaidAmount = 0;
                            foreach ($allPrs as $pr) {
                                $prUnpaidAmount += $pr->amount;
                            }
                            
                            // Get unpaid PO data (same as Purchase Requisition Ops)
                            $allPOs = \DB::table('purchase_order_ops as poo')
                                ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', function($join) {
                                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                                         ->where(function($q) {
                                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                               ->orWhere(function($q2) {
                                                   $q2->whereNull('poi.pr_ops_item_id')
                                                      ->whereColumn('poi.item_name', 'pri.item_name');
                                               });
                                         });
                                })
                                ->leftJoin('non_food_payments as nfp', 'poo.id', '=', 'nfp.purchase_order_ops_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->where('poi.source_type', 'purchase_requisition_ops')
                                ->whereIn('poo.status', ['submitted', 'approved'])
                                ->whereNull('nfp.id')
                                ->groupBy('poo.id')
                                ->select('poo.id as po_id', \DB::raw('SUM(poi.total) as po_total'))
                                ->get();
                            
                            $poUnpaidAmount = 0;
                            foreach ($allPOs as $po) {
                                $poUnpaidAmount += $po->po_total ?? 0;
                            }
                            
                            // Calculate unpaid NFP (same as Purchase Requisition Ops)
                            // Case 1: NFP langsung dari PR
                            $nfpUnpaidFromPr = \DB::table('non_food_payments as nfp')
                                ->join('purchase_requisitions as pr', 'nfp.purchase_requisition_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->whereNull('nfp.purchase_order_ops_id')
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            // Case 2: NFP melalui PO
                            $nfpUnpaidFromPo = \DB::table('non_food_payments as nfp')
                                ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                                ->leftJoin('purchase_order_ops_items as poi', function($join) {
                                    $join->on('poo.id', '=', 'poi.purchase_order_ops_id')
                                         ->where('poi.source_type', '=', 'purchase_requisition_ops');
                                })
                                ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                                ->leftJoin('purchase_requisition_items as pri', function($join) {
                                    $join->on('pr.id', '=', 'pri.purchase_requisition_id')
                                         ->where(function($q) {
                                             $q->whereColumn('poi.pr_ops_item_id', 'pri.id')
                                               ->orWhere(function($q2) {
                                                   $q2->whereNull('poi.pr_ops_item_id')
                                                      ->whereColumn('poi.item_name', 'pri.item_name');
                                               });
                                         });
                                })
                                ->where(function($q) use ($category) {
                                    $q->where('pr.category_id', $category->id)
                                      ->orWhere('pri.category_id', $category->id);
                                })
                                ->whereYear('pr.created_at', $year)
                                ->whereMonth('pr.created_at', $month)
                                ->where('pr.is_held', false)
                                ->whereNotNull('nfp.purchase_order_ops_id') // NFP melalui PO
                                ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                                ->whereIn('nfp.status', ['pending', 'approved'])
                                ->where('nfp.status', '!=', 'cancelled')
                                ->sum('nfp.amount');
                            
                            $nfpUnpaidAmount = $nfpUnpaidFromPr + $nfpUnpaidFromPo;
                            
                            // Total used = Paid (from non_food_payments 'paid' + RNF 'approved') + Unpaid (PR + PO + NFP 'approved')
                            $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
                            $unpaidAmount = $prUnpaidAmount + $poUnpaidAmount + $nfpUnpaidAmount;
                            $categoryUsedAmount = $paidAmount + $unpaidAmount;
                            
                            $budgetInfo = [
                                'budget_type' => $budgetType,
                                'current_year' => $year,
                                'current_month' => $month,
                                'category_budget' => $categoryBudget,
                                'category_used_amount' => $categoryUsedAmount,
                                'category_remaining_amount' => $categoryBudget - $categoryUsedAmount,
                                'paid_amount' => $paidAmount,
                                'unpaid_amount' => $unpaidAmount,
                                'retail_non_food_approved' => $retailNonFoodApproved,
                                'category_name' => $category->name,
                                'division_name' => $category->division ?? null,
                            ];
                        } else {
                            $budgetInfo = null;
                        }
                    } else {
                        // If category_budget_id exists but category not found, still return basic info
                        $budgetInfo = [
                            'budget_type' => 'TOTAL',
                            'budget_amount' => 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'category_used_amount' => 0,
                            'remaining_budget' => 0,
                            'category_name' => null,
                        ];
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the transaction
                    \Log::error('Error calculating budget info for RNF: ' . $e->getMessage(), [
                        'rnf_id' => $rnf->id,
                        'category_budget_id' => $rnf->category_budget_id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Still return budget_info with null values if category_budget_id exists
                    if ($rnf->category_budget_id) {
                        $budgetInfo = [
                            'budget_type' => 'TOTAL',
                            'budget_amount' => 0,
                            'paid_amount' => 0,
                            'unpaid_amount' => 0,
                            'category_used_amount' => 0,
                            'remaining_budget' => 0,
                            'category_name' => null,
                            'error' => 'Error calculating budget info',
                        ];
                    } else {
                        $budgetInfo = null;
                    }
                }
                
                return [
                    'id' => $rnf->id,
                    'retail_number' => $rnf->retail_number,
                    'transaction_date' => $rnf->transaction_date,
                    'total_amount' => $rnf->total_amount,
                    'notes' => $rnf->notes,
                    'category_budget_id' => $rnf->category_budget_id,
                    'budget_info' => $budgetInfo,
                    'items' => $rnf->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                    'invoices' => $rnf->invoices->map(function($inv) {
                        return [
                            'file_path' => $inv->file_path
                        ];
                    }),
                ];
            });
        // Log budget info for debugging
        $retailNonFoodsWithBudget = $retailNonFoods->filter(function($rnf) {
            return !empty($rnf['budget_info']);
        });
        
        \Log::info('apiOutletExpenses result', [
            'retail_food_count' => $retailFoods->count(),
            'retail_non_food_count' => $retailNonFoods->count(),
            'retail_non_food_with_budget_count' => $retailNonFoodsWithBudget->count(),
            'retail_food_ids' => $retailFoods->pluck('id'),
            'retail_non_food_ids' => $retailNonFoods->pluck('id'),
            'retail_non_food_budget_info' => $retailNonFoods->map(function($rnf) {
                return [
                    'id' => $rnf['id'],
                    'retail_number' => $rnf['retail_number'],
                    'category_budget_id' => $rnf['category_budget_id'] ?? null,
                    'has_budget_info' => !empty($rnf['budget_info']),
                    'budget_info' => $rnf['budget_info'] ?? null,
                ];
            }),
        ]);
        
        return response()->json([
            'retail_food' => $retailFoods,
            'retail_non_food' => $retailNonFoods,
        ]);
    }

    /**
     * Get FJ Detail for specific customer
     */
    public function fjDetail(Request $request)
    {
        $request->validate([
            'customer' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $customer = $request->customer;
        $from = $request->from;
        $to = $request->to;

        // Helper function to get GR data from outlet_food_good_receives (sama dengan rekap FJ)
        $getGRData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $query = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('o.nama_outlet', $customer)
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to)
                ->whereNull('gr.deleted_at'); // Filter GR yang belum dihapus

            // Apply warehouse condition
            if (is_array($warehouseCondition)) {
                $query->whereIn('w.name', $warehouseCondition);
            } else {
                $query->where('w.name', $warehouseCondition);
            }

            // Apply sub-category condition if provided
            if ($subCategoryCondition) {
                if (is_array($subCategoryCondition)) {
                    $query->whereIn('sc.name', $subCategoryCondition);
                } else {
                    $query->where('sc.name', $subCategoryCondition);
                }
            }

            // Apply exclude sub-categories if provided
            if ($excludeSubCategories) {
                $query->whereNotIn('sc.name', $excludeSubCategories);
            }

            return $query->select(
                    'it.name as item_name',
                    'cat.name as category',
                    'u.name as unit',
                    DB::raw('SUM(i.received_qty) as received_qty'),
                    DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                    DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal')
                )
                ->groupBy('it.name', 'cat.name', 'u.name')
                ->orderBy('cat.name')
                ->orderBy('it.name')
                ->get();
        };

        // Helper function to get GR data from good_receive_outlet_suppliers
        $getGRSupplierData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $query = DB::table('good_receive_outlet_suppliers as gr')
                ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('o.nama_outlet', $customer)
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to);

            // Apply warehouse condition
            if (is_array($warehouseCondition)) {
                $query->whereIn('w.name', $warehouseCondition);
            } else {
                $query->where('w.name', $warehouseCondition);
            }

            // Apply sub-category condition if provided
            if ($subCategoryCondition) {
                if (is_array($subCategoryCondition)) {
                    $query->whereIn('sc.name', $subCategoryCondition);
                } else {
                    $query->where('sc.name', $subCategoryCondition);
                }
            }

            // Apply exclude sub-categories if provided
            if ($excludeSubCategories) {
                $query->whereNotIn('sc.name', $excludeSubCategories);
            }

            return $query->select(
                    'it.name as item_name',
                    'cat.name as category',
                    'u.name as unit',
                    DB::raw('SUM(i.qty_received) as received_qty'),
                    DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                    DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
                )
                ->groupBy('it.name', 'cat.name', 'u.name')
                ->orderBy('cat.name')
                ->orderBy('it.name')
                ->get();
        };

            // Get data from outlet_food_good_receives (only GR, no GR Supplier to match main report)
            $mainKitchenGR = $getGRData(['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            $mainStoreGR = $getGRData('MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
            $chemicalGR = $getGRData('MAIN STORE', 'Chemical');
            $stationaryGR = $getGRData('MAIN STORE', 'Stationary');
            $marketingGR = $getGRData('MAIN STORE', 'Marketing');

            // Add source identifier to each dataset
            $mainKitchenGR->each(function($item) {
                $item->source = 'GR';
            });
            $mainStoreGR->each(function($item) {
                $item->source = 'GR';
            });
            $chemicalGR->each(function($item) {
                $item->source = 'GR';
            });
            $stationaryGR->each(function($item) {
                $item->source = 'GR';
            });
            $marketingGR->each(function($item) {
                $item->source = 'GR';
            });

            // Use only GR data (no GR Supplier to match main report)
            $mainKitchen = $mainKitchenGR;
            $mainStore = $mainStoreGR;
            $chemical = $chemicalGR;
            $stationary = $stationaryGR;
            $marketing = $marketingGR;

        return response()->json([
            'main_kitchen' => [
                'gr' => $mainKitchenGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $mainKitchen
            ],
            'main_store' => [
                'gr' => $mainStoreGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $mainStore
            ],
            'chemical' => [
                'gr' => $chemicalGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $chemical
            ],
            'stationary' => [
                'gr' => $stationaryGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $stationary
            ],
            'marketing' => [
                'gr' => $marketingGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $marketing
            ],
        ]);
    }

    /**
     * Generate FJ Detail PDF
     */
    public function fjDetailPdf(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

        // Helper function to get GR data with grouping to avoid duplicates (sama dengan rekap FJ)
        $getGRData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $query = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('o.nama_outlet', $customer)
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to)
                ->whereNull('gr.deleted_at'); // Filter GR yang belum dihapus

            // Apply warehouse condition
            if (is_array($warehouseCondition)) {
                $query->whereIn('w.name', $warehouseCondition);
            } else {
                $query->where('w.name', $warehouseCondition);
            }

            // Apply sub-category condition if provided
            if ($subCategoryCondition) {
                if (is_array($subCategoryCondition)) {
                    $query->whereIn('sc.name', $subCategoryCondition);
                } else {
                    $query->where('sc.name', $subCategoryCondition);
                }
            }

            // Apply exclude sub-categories if provided
            if ($excludeSubCategories) {
                $query->whereNotIn('sc.name', $excludeSubCategories);
            }

            return $query->select(
                    'it.name as item_name',
                    'cat.name as category',
                    'u.name as unit',
                    DB::raw('SUM(i.received_qty) as received_qty'),
                    DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                    DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal')
                )
                ->groupBy('it.name', 'cat.name', 'u.name')
                ->orderBy('cat.name')
                ->orderBy('it.name')
                ->get();
        };

        // Helper function to get GR Supplier data (sama dengan fjDetail)
        $getGRSupplierData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $query = DB::table('good_receive_outlet_suppliers as gr')
                ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->join('units as u', 'i.unit_id', '=', 'u.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as fo', function($join) {
                    $join->on('i.item_id', '=', 'fo.item_id')
                         ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                })
                ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                ->where('o.nama_outlet', $customer)
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to);

            // Apply warehouse condition
            if (is_array($warehouseCondition)) {
                $query->whereIn('w.name', $warehouseCondition);
            } else {
                $query->where('w.name', $warehouseCondition);
            }

            // Apply sub-category condition if provided
            if ($subCategoryCondition) {
                if (is_array($subCategoryCondition)) {
                    $query->whereIn('sc.name', $subCategoryCondition);
                } else {
                    $query->where('sc.name', $subCategoryCondition);
                }
            }

            // Apply exclude sub-categories if provided
            if ($excludeSubCategories) {
                $query->whereNotIn('sc.name', $excludeSubCategories);
            }

            return $query->select(
                    'it.name as item_name',
                    'cat.name as category',
                    'u.name as unit',
                    DB::raw('SUM(i.qty_received) as received_qty'),
                    DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                    DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
                )
                ->groupBy('it.name', 'cat.name', 'u.name')
                ->orderBy('cat.name')
                ->orderBy('it.name')
                ->get();
        };

        // Get data from outlet_food_good_receives (only GR, no GR Supplier to match main report)
        $mainKitchenGR = $getGRData(['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        $mainStoreGR = $getGRData('MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $chemicalGR = $getGRData('MAIN STORE', 'Chemical');
        $stationaryGR = $getGRData('MAIN STORE', 'Stationary');
        $marketingGR = $getGRData('MAIN STORE', 'Marketing');

        // Add source identifier to each dataset
        $mainKitchenGR->each(function($item) {
            $item->source = 'GR';
        });
        $mainStoreGR->each(function($item) {
            $item->source = 'GR';
        });
        $chemicalGR->each(function($item) {
            $item->source = 'GR';
        });
        $stationaryGR->each(function($item) {
            $item->source = 'GR';
        });
        $marketingGR->each(function($item) {
            $item->source = 'GR';
        });

        // Use only GR data (no GR Supplier to match main report)
        $mainKitchen = $mainKitchenGR;
        $mainStore = $mainStoreGR;
        $chemical = $chemicalGR;
        $stationary = $stationaryGR;
        $marketing = $marketingGR;

        // Calculate totals (only GR, no GR Supplier)
        $mainKitchenGrTotal = $mainKitchenGR->sum('subtotal');
        $mainStoreGrTotal = $mainStoreGR->sum('subtotal');
        $chemicalGrTotal = $chemicalGR->sum('subtotal');
        $stationaryGrTotal = $stationaryGR->sum('subtotal');
        $marketingGrTotal = $marketingGR->sum('subtotal');

        // Calculate grand totals (only GR)
        $mainKitchenTotal = $mainKitchenGrTotal;
        $mainStoreTotal = $mainStoreGrTotal;
        $chemicalTotal = $chemicalGrTotal;
        $stationaryTotal = $stationaryGrTotal;
        $marketingTotal = $marketingGrTotal;
        $grandTotal = $mainKitchenTotal + $mainStoreTotal + $chemicalTotal + $stationaryTotal + $marketingTotal;

        // Generate PDF with optimized settings
        $pdf = \PDF::loadView('reports.fj-detail-pdf', [
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'mainKitchen' => [
                'gr' => $mainKitchenGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $mainKitchen
            ],
            'mainStore' => [
                'gr' => $mainStoreGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $mainStore
            ],
            'chemical' => [
                'gr' => $chemicalGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $chemical
            ],
            'stationary' => [
                'gr' => $stationaryGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $stationary
            ],
            'marketing' => [
                'gr' => $marketingGR,
                'gr_supplier' => collect(),
                'retail_food' => collect(),
                'all' => $marketing
            ],
            'mainKitchenTotal' => $mainKitchenTotal,
            'mainStoreTotal' => $mainStoreTotal,
            'chemicalTotal' => $chemicalTotal,
            'stationaryTotal' => $stationaryTotal,
            'marketingTotal' => $marketingTotal,
            'grandTotal' => $grandTotal,
            'grTotals' => [
                'mainKitchen' => $mainKitchenGrTotal,
                'mainStore' => $mainStoreGrTotal,
                'chemical' => $chemicalGrTotal,
                'stationary' => $stationaryGrTotal,
                'marketing' => $marketingGrTotal,
            ],
            'retailTotals' => [
                'mainKitchen' => 0,
                'mainStore' => 0,
                'chemical' => 0,
                'stationary' => 0,
                'marketing' => 0,
            ],
        ]);

        // Optimize PDF settings for compact layout
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('margin-top', 10);
        $pdf->setOption('margin-bottom', 10);
        $pdf->setOption('margin-left', 10);
        $pdf->setOption('margin-right', 10);
        $pdf->setOption('dpi', 96);

                    // Clean filename from invalid characters and ensure it's safe
            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer); // Remove leading/trailing spaces
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer); // Replace multiple spaces with single underscore
            $filename = "FJ_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";
        
        return $pdf->download($filename);
        
        } catch (\Exception $e) {
            \Log::error('FJ Detail PDF Error: ' . $e->getMessage(), [
                'customer' => $request->customer ?? 'unknown',
                'from' => $request->from ?? 'unknown',
                'to' => $request->to ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Gagal generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Retail Detail PDF
     */
    public function retailDetailPdf(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get retail sales detail data with error handling
            $retailData = DB::table('retail_warehouse_sales as rws')
                ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->join('items as it', 'rwsi.item_id', '=', 'it.id')
                ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
                ->where('c.name', $customer)
                ->whereDate('rws.created_at', '>=', $from)
                ->whereDate('rws.created_at', '<=', $to)
                ->select(
                    'it.name as item_name',
                    DB::raw('COALESCE(sc.name, "Uncategorized") as category'),
                    'rwsi.qty',
                    'rwsi.price',
                    'rwsi.subtotal',
                    'rws.number as sale_number',
                    'rws.created_at as sale_date'
                )
                ->orderBy('category')
                ->orderBy('it.name')
                ->get();

            // Group by category
            $groupedData = [];
            foreach ($retailData as $item) {
                $category = $item->category ?: 'Uncategorized';
                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }
                $groupedData[$category][] = $item;
            }

            // Calculate totals
            $totalAmount = $retailData->sum('subtotal');

            // Generate PDF
            $pdf = \PDF::loadView('reports.retail-detail-pdf', [
                'customer' => $customer,
                'from' => $from,
                'to' => $to,
                'detailData' => $groupedData,
                'totalAmount' => $totalAmount,
            ]);

            // Optimize PDF settings for compact layout
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('dpi', 96);

            // Clean filename from invalid characters and ensure it's safe
            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer); // Remove leading/trailing spaces
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer); // Replace multiple spaces with single underscore
            $filename = "Retail_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            \Log::error('Retail Detail PDF Error: ' . $e->getMessage(), [
                'customer' => $request->customer ?? 'unknown',
                'from' => $request->from ?? 'unknown',
                'to' => $request->to ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Gagal generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Warehouse Detail PDF
     */
    public function warehouseDetailPdf(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get warehouse sales detail data with error handling
            $warehouseData = DB::table('warehouse_sales as ws')
                ->join('warehouse_sale_items as wsi', 'ws.id', '=', 'wsi.warehouse_sale_id')
                ->join('warehouses as w', 'ws.target_warehouse_id', '=', 'w.id')
                ->join('items as it', 'wsi.item_id', '=', 'it.id')
                ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->where('w.name', $customer)
                ->whereDate('ws.date', '>=', $from)
                ->whereDate('ws.date', '<=', $to)
                ->whereNull('ws.deleted_at') // Filter warehouse sales yang belum dihapus
                ->select(
                    'it.name as item_name',
                    DB::raw('COALESCE(sc.name, "Uncategorized") as category'),
                    'wsi.qty_small',
                    'wsi.qty_medium',
                    'wsi.qty_large',
                    'wsi.price',
                    'wsi.total',
                    'ws.number as sale_number',
                    'ws.date as sale_date'
                )
                ->orderBy('category')
                ->orderBy('it.name')
                ->get();

            // Group by category
            $groupedData = [];
            foreach ($warehouseData as $item) {
                $category = $item->category ?: 'Uncategorized';
                if (!isset($groupedData[$category])) {
                    $groupedData[$category] = [];
                }
                $groupedData[$category][] = $item;
            }

            // Calculate totals
            $totalAmount = $warehouseData->sum('total');

            // Generate PDF
            $pdf = \PDF::loadView('reports.warehouse-detail-pdf', [
                'customer' => $customer,
                'from' => $from,
                'to' => $to,
                'detailData' => $groupedData,
                'totalAmount' => $totalAmount,
            ]);

            // Optimize PDF settings for compact layout
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('dpi', 96);

            // Clean filename from invalid characters and ensure it's safe
            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer); // Remove leading/trailing spaces
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer); // Replace multiple spaces with single underscore
            $filename = "Warehouse_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            \Log::error('Warehouse Detail PDF Error: ' . $e->getMessage(), [
                'customer' => $request->customer ?? 'unknown',
                'from' => $request->from ?? 'unknown',
                'to' => $request->to ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Gagal generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export FJ Detail to Excel
     */
    public function fjDetailExcel(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Helper function to get GR data with grouping to avoid duplicates (sama dengan rekap FJ)
            $getGRData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
                $query = DB::table('outlet_food_good_receives as gr')
                    ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                    ->join('items as it', 'i.item_id', '=', 'it.id')
                    ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                    ->join('units as u', 'i.unit_id', '=', 'u.id')
                    ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_floor_order_items as fo', function($join) {
                        $join->on('i.item_id', '=', 'fo.item_id')
                             ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                    })
                    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                    ->where('o.nama_outlet', $customer)
                    ->whereDate('gr.receive_date', '>=', $from)
                    ->whereDate('gr.receive_date', '<=', $to)
                    ->whereNull('gr.deleted_at');

                // Apply warehouse condition
                if (is_array($warehouseCondition)) {
                    $query->whereIn('w.name', $warehouseCondition);
                } else {
                    $query->where('w.name', $warehouseCondition);
                }

                // Apply sub-category condition if provided
                if ($subCategoryCondition) {
                    if (is_array($subCategoryCondition)) {
                        $query->whereIn('sc.name', $subCategoryCondition);
                    } else {
                        $query->where('sc.name', $subCategoryCondition);
                    }
                }

                // Apply exclude sub-categories if provided
                if ($excludeSubCategories) {
                    $query->whereNotIn('sc.name', $excludeSubCategories);
                }

                return $query->select(
                        'it.name as item_name',
                        'cat.name as category',
                        'u.name as unit',
                        DB::raw('SUM(i.received_qty) as received_qty'),
                        DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                        DB::raw('SUM(i.received_qty * COALESCE(fo.price, 0)) as subtotal')
                    )
                    ->groupBy('it.name', 'cat.name', 'u.name')
                    ->orderBy('cat.name')
                    ->orderBy('it.name')
                    ->get();
            };

            // Helper function to get GR Supplier data (sama dengan fjDetail)
            $getGRSupplierData = function($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
                $query = DB::table('good_receive_outlet_suppliers as gr')
                    ->join('good_receive_outlet_supplier_items as i', 'gr.id', '=', 'i.good_receive_id')
                    ->join('items as it', 'i.item_id', '=', 'it.id')
                    ->join('categories as cat', 'it.category_id', '=', 'cat.id')
                    ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                    ->join('units as u', 'i.unit_id', '=', 'u.id')
                    ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                    ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                    ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_floor_order_items as fo', function($join) {
                        $join->on('i.item_id', '=', 'fo.item_id')
                             ->on('fo.floor_order_id', '=', 'do.floor_order_id');
                    })
                    ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
                    ->where('o.nama_outlet', $customer)
                    ->whereDate('gr.receive_date', '>=', $from)
                    ->whereDate('gr.receive_date', '<=', $to);

                // Apply warehouse condition
                if (is_array($warehouseCondition)) {
                    $query->whereIn('w.name', $warehouseCondition);
                } else {
                    $query->where('w.name', $warehouseCondition);
                }

                // Apply sub-category condition if provided
                if ($subCategoryCondition) {
                    if (is_array($subCategoryCondition)) {
                        $query->whereIn('sc.name', $subCategoryCondition);
                    } else {
                        $query->where('sc.name', $subCategoryCondition);
                    }
                }

                // Apply exclude sub-categories if provided
                if ($excludeSubCategories) {
                    $query->whereNotIn('sc.name', $excludeSubCategories);
                }

                return $query->select(
                        'it.name as item_name',
                        'cat.name as category',
                        'u.name as unit',
                        DB::raw('SUM(i.qty_received) as received_qty'),
                        DB::raw('AVG(COALESCE(fo.price, 0)) as price'),
                        DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as subtotal')
                    )
                    ->groupBy('it.name', 'cat.name', 'u.name')
                    ->orderBy('cat.name')
                    ->orderBy('it.name')
                    ->get();
            };

            // Get data from outlet_food_good_receives (only GR, no GR Supplier to match main report)
            $mainKitchenGR = $getGRData(['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
            $mainStoreGR = $getGRData('MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
            $chemicalGR = $getGRData('MAIN STORE', 'Chemical');
            $stationaryGR = $getGRData('MAIN STORE', 'Stationary');
            $marketingGR = $getGRData('MAIN STORE', 'Marketing');

            // Use only GR data (no GR Supplier to match main report)
            $mainKitchen = $mainKitchenGR;
            $mainStore = $mainStoreGR;
            $chemical = $chemicalGR;
            $stationary = $stationaryGR;
            $marketing = $marketingGR;

            // Prepare data for Excel
            $excelData = [];
            
            // Add header
            $excelData[] = [
                'Kategori',
                'Item Name',
                'Category',
                'Unit',
                'Qty Received',
                'Price',
                'Subtotal'
            ];

            // Add Main Kitchen data
            foreach ($mainKitchen as $item) {
                $excelData[] = [
                    'Main Kitchen',
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->received_qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Add Main Store data
            foreach ($mainStore as $item) {
                $excelData[] = [
                    'Main Store',
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->received_qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Add Chemical data
            foreach ($chemical as $item) {
                $excelData[] = [
                    'Chemical',
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->received_qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Add Stationary data
            foreach ($stationary as $item) {
                $excelData[] = [
                    'Stationary',
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->received_qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Add Marketing data
            foreach ($marketing as $item) {
                $excelData[] = [
                    'Marketing',
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->received_qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Create Excel file
            $filename = 'FJ_Detail_' . $customer . '_' . $from . '_' . $to . '.xlsx';
            
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\FjDetailExport($excelData),
                $filename
            );

        } catch (\Exception $e) {
            \Log::error('FJ Detail Excel error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export Retail Detail to Excel
     */
    public function retailDetailExcel(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get retail sales data (using same tables as retailSalesDetail and retailDetailPdf)
            $retailData = DB::table('retail_warehouse_sales as rws')
                ->join('retail_warehouse_sale_items as rwsi', 'rws.id', '=', 'rwsi.retail_warehouse_sale_id')
                ->join('customers as c', 'rws.customer_id', '=', 'c.id')
                ->join('items as it', 'rwsi.item_id', '=', 'it.id')
                ->leftJoin('categories as cat', 'it.category_id', '=', 'cat.id')
                ->leftJoin('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
                ->where('c.name', $customer)
                ->whereDate('rws.created_at', '>=', $from)
                ->whereDate('rws.created_at', '<=', $to)
                ->select(
                    DB::raw('COALESCE(sc.name, "Uncategorized") as sub_category'),
                    'it.name as item_name',
                    DB::raw('COALESCE(cat.name, "Uncategorized") as category_name'),
                    DB::raw('COALESCE(rwsi.unit, "Pcs") as unit'),
                    DB::raw('SUM(rwsi.qty) as qty'),
                    DB::raw('AVG(rwsi.price) as price'),
                    DB::raw('SUM(rwsi.subtotal) as subtotal')
                )
                ->groupBy(DB::raw('COALESCE(sc.name, "Uncategorized")'), 'it.name', DB::raw('COALESCE(cat.name, "Uncategorized")'), 'rwsi.unit')
                ->orderBy('sub_category')
                ->orderBy('it.name')
                ->get();

            // Prepare data for Excel
            $excelData = [];
            
            // Add header (matching FjDetailExport structure: Kategori, Item Name, Category, Unit, Qty Received, Price, Subtotal)
            $excelData[] = [
                'Kategori',
                'Item Name',
                'Category',
                'Unit',
                'Qty Received',
                'Price',
                'Subtotal'
            ];

            // Add retail data grouped by sub_category
            foreach ($retailData as $item) {
                $excelData[] = [
                    $item->sub_category ?: 'Uncategorized', // Kategori (sub_category)
                    $item->item_name, // Item Name
                    $item->category_name ?: 'Uncategorized', // Category (from categories table)
                    $item->unit ?: 'Pcs', // Unit
                    $item->qty, // Qty Received
                    $item->price, // Price
                    $item->subtotal // Subtotal
                ];
            }

            // Create Excel file
            $filename = 'Retail_Detail_' . str_replace([' ', '/'], '_', $customer) . '_' . $from . '_' . $to . '.xlsx';
            
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\FjDetailExport($excelData),
                $filename
            );

        } catch (\Exception $e) {
            \Log::error('Retail Detail Excel error: ' . $e->getMessage());
            \Log::error('Retail Detail Excel error trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export Warehouse Detail to Excel
     */
    public function warehouseDetailExcel(Request $request)
    {
        try {
            $request->validate([
                'customer' => 'required|string',
                'from' => 'required|date',
                'to' => 'required|date',
            ]);

            $customer = $request->customer;
            $from = $request->from;
            $to = $request->to;

            // Get warehouse sales data
            $warehouseData = DB::table('warehouse_sales as ws')
                ->join('warehouse_sales_items as wsi', 'ws.id', '=', 'wsi.warehouse_sales_id')
                ->join('items as i', 'wsi.item_id', '=', 'i.id')
                ->join('categories as c', 'i.category_id', '=', 'c.id')
                ->join('units as u', 'wsi.unit_id', '=', 'u.id')
                ->join('tbl_data_outlet as o', 'ws.outlet_id', '=', 'o.id_outlet')
                ->where('o.nama_outlet', $customer)
                ->whereDate('ws.sales_date', '>=', $from)
                ->whereDate('ws.sales_date', '<=', $to)
                ->select(
                    'i.name as item_name',
                    'c.name as category',
                    'u.name as unit',
                    DB::raw('SUM(wsi.qty) as qty'),
                    DB::raw('AVG(wsi.price) as price'),
                    DB::raw('SUM(wsi.qty * wsi.price) as subtotal')
                )
                ->groupBy('i.name', 'c.name', 'u.name')
                ->orderBy('c.name')
                ->orderBy('i.name')
                ->get();

            // Prepare data for Excel
            $excelData = [];
            
            // Add header
            $excelData[] = [
                'Item Name',
                'Category',
                'Unit',
                'Qty',
                'Price',
                'Subtotal'
            ];

            // Add warehouse data
            foreach ($warehouseData as $item) {
                $excelData[] = [
                    $item->item_name,
                    $item->category,
                    $item->unit,
                    $item->qty,
                    $item->price,
                    $item->subtotal
                ];
            }

            // Create Excel file
            $filename = 'Warehouse_Detail_' . $customer . '_' . $from . '_' . $to . '.xlsx';
            
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\FjDetailExport($excelData),
                $filename
            );

        } catch (\Exception $e) {
            \Log::error('Warehouse Detail Excel error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Activity Log Report
     */
    public function reportActivityLog(Request $request)
    {
        $query = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->select(
                'al.id',
                'al.user_id',
                'u.nama_lengkap as user_name',
                'al.activity_type',
                'al.module',
                'al.description',
                'al.ip_address',
                'al.user_agent',
                'al.old_data',
                'al.new_data',
                'al.created_at'
            );

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('al.user_id', $request->user_id);
        }

        // Filter by activity type
        if ($request->filled('activity_type')) {
            $query->where('al.activity_type', $request->activity_type);
        }

        // Filter by module
        if ($request->filled('module')) {
            $query->where('al.module', $request->module);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('al.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('al.created_at', '<=', $request->date_to);
        }

        // Filter by search (description, module, user name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('al.description', 'like', "%{$search}%")
                  ->orWhere('al.module', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('al.ip_address', 'like', "%{$search}%");
            });
        }

        // Get unique values for filters
        $users = DB::table('users')
            ->whereIn('id', function($q) {
                $q->select('user_id')->from('activity_logs')->distinct();
            })
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get();

        $activityTypes = DB::table('activity_logs')
            ->select('activity_type')
            ->distinct()
            ->orderBy('activity_type')
            ->pluck('activity_type');

        $modules = DB::table('activity_logs')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        // Pagination
        $perPage = $request->get('per_page', 25);
        $logs = $query->orderByDesc('al.created_at')->paginate($perPage)->withQueryString();

        // For API requests, return JSON
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'logs' => $logs,
                'users' => $users,
                'activityTypes' => $activityTypes,
                'modules' => $modules,
                'filters' => [
                    'user_id' => $request->user_id,
                    'activity_type' => $request->activity_type,
                    'module' => $request->module,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'search' => $request->search,
                    'per_page' => $perPage,
                ]
            ]);
        }

        return Inertia::render('Report/ActivityLog', [
            'logs' => $logs,
            'users' => $users,
            'activityTypes' => $activityTypes,
            'modules' => $modules,
            'filters' => [
                'user_id' => $request->user_id,
                'activity_type' => $request->activity_type,
                'module' => $request->module,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'per_page' => $perPage,
            ]
        ]);
    }
} 