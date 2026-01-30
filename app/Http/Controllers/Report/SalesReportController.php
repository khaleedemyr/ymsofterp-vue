<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use App\Exports\ReportSalesAllItemAllOutletExport;
use App\Exports\SalesPivotSpecialExport;

/**
 * Sales Report Controller
 * 
 * Handles all sales-related reports including:
 * - Sales per category/date
 * - Sales pivot reports (per outlet, sub-category)
 * - Sales detail reports
 * - Export functions
 * 
 * Split from ReportController for better organization and performance
 * 
 * CRITICAL Performance Issues:
 * - Manual pagination (lines 75-79, 149-152, 296-303)
 * - Query merging in PHP (lines 296-298, 449-451)
 * - Nested loops for pivot transformation (multiple locations)
 * - whereYear/whereMonth usage (index killers)
 * 
 * Functions (10):
 * - reportSalesPerCategory: Sales grouped by category and month
 * - reportSalesPerTanggal: Sales grouped by date
 * - reportSalesAllItemAllOutlet: All items across all outlets (COMPLEX)
 * - exportSalesAllItemAllOutlet: Export to Excel
 * - reportSalesPivotPerOutletSubCategory: Pivot by outlet and sub-category (COMPLEX)
 * - exportSalesPivotPerOutletSubCategory: Export pivot to Excel
 * - reportSalesPivotSpecial: Special FJ pivot report (CRITICAL - Most Complex)
 * - exportSalesPivotSpecial: Export special pivot to Excel
 * - salesPivotOutletDetail: Detailed items for outlet
 * - reportSalesSimple: Simple sales report by outlet and date
 */
class SalesReportController extends Controller
{
    use ReportHelperTrait;

    /**
     * Report Sales Per Category
     * 
     * Shows sales data grouped by warehouse, month, year, and category
     * 
     * Performance Issues:
     * - Manual pagination (line 75-79)
     * - whereYear/whereMonth (line 54, 57) - kills indexes
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

        // Manual pagination using LengthAwarePaginator (karena groupBy tidak bisa dipaginate di SQL)
        // Tetap load semua data, tapi format response lebih proper
        $total = $data->count();
        $items = $data->slice(($page - 1) * $perPage, $perPage)->values();
        
        // Create paginator instance
        $paginated = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Data filter (using cached helpers)
        $warehouses = $this->getCachedWarehouseNames();
        $categories = $this->getCachedCategoryNames();
        $years = $this->getCachedReceiveDateYears();

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
        ]);
    }

    /**
     * Report Sales Per Tanggal
     * 
     * Shows sales data grouped by warehouse and date
     * 
     * Performance Issues:
     * - Manual pagination (line 149-152)
     * - whereYear/whereMonth (line 130, 133)
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

        // Manual pagination using LengthAwarePaginator (karena groupBy tidak bisa dipaginate di SQL)
        $total = $data->count();
        $items = $data->slice(($page - 1) * $perPage, $perPage)->values();
        
        // Create paginator instance
        $paginated = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Data filter (using cached helpers)
        $warehouses = $this->getCachedWarehouseNames();
        $years = $this->getCachedReceiveDateYears();
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
        ]);
    }

    /**
     * Report Sales All Item All Outlet
     * 
     * COMPLEX FUNCTION - Shows all items across all outlets
     * 
     * Performance Issues:
     * - Query merge in PHP (line 296-298)
     * - Manual pagination (line 300-303)
     * - Two separate queries instead of UNION
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

        // Manual pagination using LengthAwarePaginator (karena merge di PHP)
        $total = $data->count();
        $items = $data->slice(($page - 1) * $perPage, $perPage)->values();
        
        // Create paginator instance
        $paginated = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Data filter (using cached helpers)
        $warehouses = $this->getCachedWarehouseNames();
        $outlets = $this->getCachedOutletNames();

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
        ]);
    }

    /**
     * Export Sales All Item All Outlet
     * 
     * Export to Excel - same complex logic as reportSalesAllItemAllOutlet
     * 
     * @param Request $request
     * @return mixed
     */
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

    /**
     * Report Sales Pivot Per Outlet Sub Category
     * 
     * COMPLEX FUNCTION - Pivot report showing sales per outlet and sub-category
     * 
     * Performance Issues:
     * - Multiple nested loops (lines 560-615)
     * - Two separate queries + merging in PHP
     * - Pivot transformation in PHP
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * Export Sales Pivot Per Outlet Sub Category
     * 
     * Export pivot report to Excel
     * Same complex logic as reportSalesPivotPerOutletSubCategory
     * 
     * @param Request $request
     * @return mixed
     */
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
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to)
                ->whereNull('gr.deleted_at')
                ->select(
                    'o.nama_outlet as customer',
                    'o.is_outlet',
                    'sc.name as sub_category',
                    DB::raw('SUM(i.received_qty * fo.price) as nilai')
                )
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
                ->whereDate('gr.receive_date', '>=', $from)
                ->whereDate('gr.receive_date', '<=', $to)
                ->select(
                    'o.nama_outlet as customer',
                    'o.is_outlet',
                    'sc.name as sub_category',
                    DB::raw('SUM(i.qty_received * COALESCE(fo.price, 0)) as nilai')
                )
                ->groupBy('o.nama_outlet', 'o.is_outlet', 'sc.name')
                ->orderBy('o.is_outlet', 'desc')
                ->orderBy('o.nama_outlet')
                ->orderBy('sc.name')
                ->get();

            // Gabungkan kedua report
            $outletData = [];
            
            // Process query1 (outlet_food_good_receives)
            foreach ($query1 as $row) {
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
            
            // Process query2 (good_receive_outlet_suppliers)
            foreach ($query2 as $row) {
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

            // Group data berdasarkan is_outlet
            $groupedReport = [
                'outlets' => array_values(array_filter($pivot, function($row) {
                    return $row['is_outlet'] == 1;
                })),
                'nonOutlets' => array_values(array_filter($pivot, function($row) {
                    return $row['is_outlet'] != 1;
                }))
            ];

            // Create export
            return new \App\Exports\SalesPivotPerOutletSubCategoryExport($groupedReport, $subCategories, $from . ' - ' . $to);
            
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Report Sales Pivot Special (FJ Rekap)
     * 
     * CRITICAL MOST COMPLEX FUNCTION - FJ distribution report with multiple categories
     * 
     * Performance Issues:
     * - MOST nested loops (lines 1056-1094, 1100-1135, 1152-1168)
     * - Multiple separate queries
     * - Complex data pivoting and merging
     * - Group by item first, then sum by outlet (to avoid double counting)
     * 
     * This is THE HEAVIEST function in the entire ReportController
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

    /**
     * Export Sales Pivot Special
     * 
     * CRITICAL - Export the most complex report
     * Same heavy logic as reportSalesPivotSpecial
     * 
     * @param Request $request
     * @return mixed
     */
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
            Log::error('Export error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Sales Pivot Outlet Detail
     * 
     * Returns detailed items for a specific outlet within date range
     * Combines data from outlet_food_good_receives and good_receive_outlet_suppliers
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
     * Report Sales Simple
     * 
     * Simple sales report filtered by outlet and date range
     * Shows orders data with outlet info
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportSalesSimple(Request $request)
    {
        $outlet = $request->input('outlet');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Enable debug logging to troubleshoot
        Log::info('reportSalesSimple - Request params', [
            'outlet' => $outlet,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'all_params' => $request->all()
        ]);

        $query = DB::table('orders')
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
            // Handle both QR code and outlet name/ID
            // If outlet is numeric, treat as id_outlet, otherwise as QR code
            if (is_numeric($outlet)) {
                // Get QR code from outlet ID
                $outletQr = DB::table('tbl_data_outlet')
                    ->where('id_outlet', $outlet)
                    ->value('qr_code');
                if ($outletQr) {
                    $query->where('orders.kode_outlet', $outletQr);
                } else {
                    $query->where('orders.kode_outlet', $outlet);
                }
            } else {
                // Check if it's outlet name or QR code, convert to QR code
                $outletQr = DB::table('tbl_data_outlet')
                    ->where(function($q) use ($outlet) {
                        $q->where('nama_outlet', $outlet)
                          ->orWhere('qr_code', $outlet);
                    })
                    ->value('qr_code');
                if ($outletQr) {
                    $query->where('orders.kode_outlet', $outletQr);
                } else {
                    // Fallback: try direct match with QR code
                    $query->where('orders.kode_outlet', $outlet);
                }
            }
        }
        
        if ($dateFrom) {
            // Handle different date formats (MM/DD/YYYY or YYYY-MM-DD)
            $dateFromFormatted = $this->normalizeDate($dateFrom);
            $query->whereDate('orders.created_at', '>=', $dateFromFormatted);
        }
        if ($dateTo) {
            // Handle different date formats (MM/DD/YYYY or YYYY-MM-DD)
            $dateToFormatted = $this->normalizeDate($dateTo);
            $query->whereDate('orders.created_at', '<=', $dateToFormatted);
        }
        
        // Opsional: filter status jika dikirim
        if ($request->has('status') && $request->status) {
            $query->where('orders.status', $request->status);
        }

        // Log the SQL query for debugging
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        Log::info('reportSalesSimple - SQL Query', [
            'sql' => $sql,
            'bindings' => $bindings
        ]);

        $orders = $query->orderBy('orders.created_at')->get();
        
        Log::info('reportSalesSimple - Query result', [
            'count' => $orders->count(),
            'first_order' => $orders->first() ? [
                'id' => $orders->first()->id,
                'nomor' => $orders->first()->nomor,
                'kode_outlet' => $orders->first()->kode_outlet,
                'created_at' => $orders->first()->created_at,
            ] : null
        ]);

        // Calculate summary from orders
        $summary = [
            'total_sales' => $orders->sum('total') ?? 0,
            'grand_total' => $orders->sum('grand_total') ?? 0,
            'total_order' => $orders->count(),
            'total_pax' => $orders->sum('pax') ?? 0,
            'total_discount' => $orders->sum(function($order) {
                $discount = floatval($order->discount ?? 0);
                $manualDiscount = floatval($order->manual_discount_amount ?? 0);
                // If both > 0, take the max (same logic as frontend)
                if ($discount > 0 && $manualDiscount > 0) {
                    return max($discount, $manualDiscount);
                }
                return $discount + $manualDiscount;
            }),
            'total_cashback' => $orders->sum('cashback') ?? 0,
            'total_service' => $orders->sum('service') ?? 0,
            'total_pb1' => $orders->sum('pb1') ?? 0,
            'total_commfee' => $orders->sum('commfee') ?? 0,
            'total_rounding' => $orders->sum('rounding') ?? 0,
        ];

        // Group orders by date for per_day breakdown
        $perDay = [];
        foreach ($orders as $order) {
            $date = date('Y-m-d', strtotime($order->created_at));
            if (!isset($perDay[$date])) {
                $perDay[$date] = [
                    'tanggal' => $date,
                    'total_order' => 0,
                    'total_pax' => 0,
                    'total_discount' => 0,
                    'total_cashback' => 0,
                    'total_service' => 0,
                    'total_pb1' => 0,
                    'total_commfee' => 0,
                    'total_rounding' => 0,
                    'total_sales' => 0,
                    'grand_total' => 0,
                ];
            }
            
            $perDay[$date]['total_order']++;
            $perDay[$date]['total_pax'] += floatval($order->pax ?? 0);
            
            // Calculate discount (same logic as summary)
            $discount = floatval($order->discount ?? 0);
            $manualDiscount = floatval($order->manual_discount_amount ?? 0);
            if ($discount > 0 && $manualDiscount > 0) {
                $perDay[$date]['total_discount'] += max($discount, $manualDiscount);
            } else {
                $perDay[$date]['total_discount'] += ($discount + $manualDiscount);
            }
            
            $perDay[$date]['total_cashback'] += floatval($order->cashback ?? 0);
            $perDay[$date]['total_service'] += floatval($order->service ?? 0);
            $perDay[$date]['total_pb1'] += floatval($order->pb1 ?? 0);
            $perDay[$date]['total_commfee'] += floatval($order->commfee ?? 0);
            $perDay[$date]['total_rounding'] += floatval($order->rounding ?? 0);
            $perDay[$date]['total_sales'] += floatval($order->total ?? 0);
            $perDay[$date]['grand_total'] += floatval($order->grand_total ?? 0);
        }

        // Frontend expects per_day as object with date as key (v-for="(row, tanggal) in report.per_day")
        // Calculate avg_check for each day
        foreach ($perDay as $date => &$day) {
            $day['avg_check'] = $day['total_pax'] > 0 
                ? ($day['grand_total'] / $day['total_pax']) 
                : 0;
        }

        Log::info('reportSalesSimple - Response prepared', [
            'summary' => $summary,
            'per_day_count' => count($perDay),
            'per_day_dates' => array_keys($perDay),
            'orders_count' => $orders->count(),
        ]);

        return response()->json([
            'summary' => $summary,
            'per_day' => $perDay, // Object with date as key
            'orders' => $orders->toArray(),
        ]);
    }
    
    /**
     * Normalize date format from various formats to YYYY-MM-DD
     * Handles: MM/DD/YYYY, DD/MM/YYYY, YYYY-MM-DD
     */
    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        
        // If already in YYYY-MM-DD format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Try to parse MM/DD/YYYY or DD/MM/YYYY format
        $parts = preg_split('/[\/\-\.]/', $date);
        if (count($parts) === 3) {
            // Check if first part is year (4 digits) or month (1-2 digits)
            if (strlen($parts[0]) === 4) {
                // Format: YYYY-MM-DD or YYYY/MM/DD
                return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
            } else {
                // Format: MM/DD/YYYY (assuming US format)
                // Or DD/MM/YYYY (assuming EU format)
                // Try MM/DD/YYYY first (most common in US systems)
                if ((int)$parts[0] <= 12 && (int)$parts[1] <= 31) {
                    return sprintf('%04d-%02d-%02d', $parts[2], $parts[0], $parts[1]);
                } else {
                    // Try DD/MM/YYYY
                    return sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                }
            }
        }
        
        // If can't parse, return original (let MySQL handle it)
        return $date;
    }
}
