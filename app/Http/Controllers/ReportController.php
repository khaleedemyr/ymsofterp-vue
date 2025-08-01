<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\OrderDetailExport;
use App\Exports\ItemEngineeringExport;
use App\Exports\ItemEngineeringMultiSheetExport;
use App\Exports\ItemEngineeringSheetExport;
use App\Exports\ModifierEngineeringSheetExport;
use App\Exports\SalesPivotPerOutletSubCategoryExport;
use App\Exports\SalesPivotSpecialExport;

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

        $perPage = $request->input('perPage', 25);
        $page = $request->input('page', 1);
        $data = collect($query->get());

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

    public function reportSalesPivotPerOutletSubCategory(Request $request)
    {
        // Ambil semua sub kategori dengan show_pos = '0'
        $subCategories = DB::table('sub_categories')
            ->where('show_pos', '0')
            ->orderBy('name')
            ->get();

        // Ambil data penjualan per outlet per sub kategori dengan show_pos = '0'
        $salesQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
            ->where('sc.show_pos', '0')
            ->select(
                'o.nama_outlet as customer',
                'sc.name as sub_category',
                DB::raw('SUM(i.received_qty * fo.price) as nilai')
            );
        if ($request->filled('tanggal')) {
            $salesQuery->whereDate('gr.receive_date', $request->tanggal);
        }
        $sales = $salesQuery
            ->groupBy('o.nama_outlet', 'sc.name')
            ->orderBy('o.nama_outlet')
            ->orderBy('sc.name')
            ->get();

        // Ambil total per outlet dengan show_pos = '0'
        $totalsQuery = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
            ->where('sc.show_pos', '0')
            ->select(
                'o.nama_outlet as customer',
                DB::raw('SUM(i.received_qty * fo.price) as line_total')
            );
        if ($request->filled('tanggal')) {
            $totalsQuery->whereDate('gr.receive_date', $request->tanggal);
        }
        $totals = $totalsQuery
            ->groupBy('o.nama_outlet')
            ->orderBy('o.nama_outlet')
            ->get()
            ->keyBy('customer');

        // Bentuk pivot array
        $pivot = [];
        foreach ($sales as $row) {
            $customer = $row->customer;
            if (!isset($pivot[$customer])) {
                $pivot[$customer] = [
                    'customer' => $customer,
                    'line_total' => 0,
                ];
            }
            $pivot[$customer][$row->sub_category] = $row->nilai;
        }
        // Isi line_total dan pastikan semua sub kategori ada kolomnya
        foreach ($pivot as $customer => &$row) {
            $row['line_total'] = $totals[$customer]->line_total ?? 0;
            foreach ($subCategories as $sc) {
                if (!isset($row[$sc->name])) {
                    $row[$sc->name] = 0;
                }
            }
        }
        unset($row);

        // Kirim ke frontend
        return Inertia::render('Report/ReportSalesPivotPerOutletSubCategory', [
            'subCategories' => $subCategories,
            'report' => array_values($pivot),
            'filters' => [
                'tanggal' => $request->tanggal,
            ],
        ]);
    }

    public function exportSalesPivotPerOutletSubCategory(Request $request)
    {
        try {
            $tanggal = $request->input('tanggal');
            
            if (!$tanggal) {
                return response()->json(['error' => 'Tanggal harus diisi'], 400);
            }
            
            // Ambil semua sub kategori dengan show_pos = '0'
            $subCategories = DB::table('sub_categories')
                ->where('show_pos', '0')
                ->orderBy('name')
                ->get();

            // Ambil data penjualan per outlet per sub kategori dengan show_pos = '0'
            $salesQuery = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
                ->where('sc.show_pos', '0')
                ->select(
                    'o.nama_outlet as customer',
                    'sc.name as sub_category',
                    DB::raw('SUM(i.received_qty * fo.price) as nilai')
                );
            
            $salesQuery->whereDate('gr.receive_date', $tanggal);
            
            $sales = $salesQuery
                ->groupBy('o.nama_outlet', 'sc.name')
                ->orderBy('o.nama_outlet')
                ->orderBy('sc.name')
                ->get();

            // Ambil total per outlet dengan show_pos = '0'
            $totalsQuery = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
                ->where('sc.show_pos', '0')
                ->select(
                    'o.nama_outlet as customer',
                    DB::raw('SUM(i.received_qty * fo.price) as line_total')
                );
            
            $totalsQuery->whereDate('gr.receive_date', $tanggal);
            
            $totals = $totalsQuery
                ->groupBy('o.nama_outlet')
                ->orderBy('o.nama_outlet')
                ->get()
                ->keyBy('customer');

            // Bentuk pivot array
            $pivot = [];
            foreach ($sales as $row) {
                $customer = $row->customer;
                if (!isset($pivot[$customer])) {
                    $pivot[$customer] = (object)[
                        'customer' => $customer,
                        'line_total' => 0,
                    ];
                }
                $pivot[$customer]->{$row->sub_category} = $row->nilai;
            }
            
            // Isi line_total dan pastikan semua sub kategori ada kolomnya
            foreach ($pivot as $customer => $row) {
                $row->line_total = $totals[$customer]->line_total ?? 0;
                foreach ($subCategories as $sc) {
                    if (!isset($row->{$sc->name})) {
                        $row->{$sc->name} = 0;
                    }
                }
            }

            return new SalesPivotPerOutletSubCategoryExport(array_values($pivot), $subCategories, $tanggal);
            
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat export: ' . $e->getMessage()], 500);
        }
    }

    public function reportSalesPivotSpecial(Request $request)
    {
        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
                'o.nama_outlet as customer',
                DB::raw("SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN i.received_qty * fo.price ELSE 0 END) as main_kitchen"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN i.received_qty * fo.price ELSE 0 END) as main_store"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN i.received_qty * fo.price ELSE 0 END) as chemical"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN i.received_qty * fo.price ELSE 0 END) as stationary"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN i.received_qty * fo.price ELSE 0 END) as marketing"),
                DB::raw("(
                    SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN i.received_qty * fo.price ELSE 0 END) +
                    SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN i.received_qty * fo.price ELSE 0 END) +
                    SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN i.received_qty * fo.price ELSE 0 END) +
                    SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN i.received_qty * fo.price ELSE 0 END) +
                    SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN i.received_qty * fo.price ELSE 0 END)
                ) as line_total")
            );

        if ($request->filled('tanggal')) {
            $query->whereDate('gr.receive_date', $request->tanggal);
        }

        $report = $query->groupBy('o.nama_outlet')
            ->orderBy('o.nama_outlet')
            ->get();

        return Inertia::render('Report/ReportSalesPivotSpecial', [
            'report' => $report,
            'filters' => [
                'tanggal' => $request->tanggal,
            ],
        ]);
    }

    public function exportSalesPivotSpecial(Request $request)
    {
        try {
            $tanggal = $request->input('tanggal');
            
            if (!$tanggal) {
                return response()->json(['error' => 'Tanggal harus diisi'], 400);
            }
            
            $query = DB::table('outlet_food_good_receives as gr')
                ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
                ->join('items as it', 'i.item_id', '=', 'it.id')
                ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
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
                    'o.nama_outlet as customer',
                    DB::raw("SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN i.received_qty * fo.price ELSE 0 END) as main_kitchen"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN i.received_qty * fo.price ELSE 0 END) as main_store"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN i.received_qty * fo.price ELSE 0 END) as chemical"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN i.received_qty * fo.price ELSE 0 END) as stationary"),
                    DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN i.received_qty * fo.price ELSE 0 END) as marketing"),
                    DB::raw("(
                        SUM(CASE WHEN w.name IN ('MK1 Hot Kitchen', 'MK2 Cold Kitchen') THEN i.received_qty * fo.price ELSE 0 END) +
                        SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN i.received_qty * fo.price ELSE 0 END) +
                        SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN i.received_qty * fo.price ELSE 0 END) +
                        SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN i.received_qty * fo.price ELSE 0 END) +
                        SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN i.received_qty * fo.price ELSE 0 END)
                    ) as line_total")
                );

            $query->whereDate('gr.receive_date', $tanggal);

            $report = $query->groupBy('o.nama_outlet')
                ->orderBy('o.nama_outlet')
                ->get();

            return new SalesPivotSpecialExport($report, $tanggal);
            
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

        // Ambil data GR per item, unit, outlet pada tanggal
        $data = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
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
                DB::raw('SUM(i.received_qty) as qty')
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->orderBy('it.name')
            ->orderBy('u.name')
            ->orderBy('o.nama_outlet')
            ->get();

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
            $pivot[$key][$row->nama_outlet] = $row->qty;
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

        // Ambil data GR per item, unit, outlet pada tanggal
        $data = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
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
                DB::raw('SUM(i.received_qty) as qty')
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->orderBy('it.name')
            ->orderBy('u.name')
            ->orderBy('o.nama_outlet')
            ->get();

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
            $pivot[$key][$row->nama_outlet] = $row->qty;
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
            'tanggal' => 'required|date',
        ]);
        $items = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->join('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->join('food_floor_order_items as fo', function($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                     ->on('fo.floor_order_id', '=', 'pl.food_floor_order_id');
            })
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('o.nama_outlet', $request->outlet)
            ->whereDate('gr.receive_date', $request->tanggal)
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

        \Log::info('DEBUG FILTER', [
            'outlet' => $outlet,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

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
        \Log::info('DEBUG ORDERS COUNT', ['count' => $orders->count()]);
        
        // DEBUG: Log sample orders dengan commfee
        $sampleOrders = $orders->take(5);
        \Log::info('DEBUG SAMPLE ORDERS', [
            'sample_orders' => $sampleOrders->map(function($order) {
                return [
                    'id' => $order->id,
                    'nomor' => $order->nomor,
                    'commfee' => $order->commfee,
                    'rounding' => $order->rounding,
                    'grand_total' => $order->grand_total,
                ];
            })->toArray()
        ]);
        
        // DEBUG: Log total commfee dan rounding
        \Log::info('DEBUG COMMFEE ROUNDING TOTALS', [
            'total_commfee' => $orders->sum('commfee'),
            'total_rounding' => $orders->sum('rounding'),
            'orders_with_commfee' => $orders->where('commfee', '>', 0)->count(),
            'orders_with_rounding' => $orders->where('rounding', '>', 0)->count(),
        ]);

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
            // 2. Disc (-): sum(discount) from orders
            'total_discount' => $orders->sum('discount'),
            // 3. Cashback: sum(cashback) from orders
            'total_cashback' => $orders->sum('cashback'),
            // 4. Net Sales: sum(total) - sum(discount) - sum(cashback)
            'net_sales' => $orders->sum('total') - $orders->sum('discount') - $orders->sum('cashback'),
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
                return ($order->discount ?? 0) - ($order->manual_discount_amount ?? 0);
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
                'total_discount' => $group->sum('discount'),
                'total_cashback' => $group->sum('cashback'),
                'total_service' => $group->sum('service'),
                'total_pb1' => $group->sum('pb1'),
                'total_commfee' => $group->sum('commfee'),
                'total_rounding' => $group->sum('rounding'),
                'total_promo_discount' => $group->sum(function($order) {
                    return ($order->discount ?? 0) - ($order->manual_discount_amount ?? 0);
                }),
                // Tambahkan net_sales, grand_total, avg_check jika perlu
                'net_sales' => $group->sum('total') - $group->sum('discount') - $group->sum('cashback'),
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
        $user = auth()->user();
        $query = \DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '');
        
        // Jika user bukan superuser (id_outlet != 1), hanya tampilkan outlet mereka sendiri
        if ($user->id_outlet != 1) {
            $query->where('id_outlet', $user->id_outlet);
        }
        
        $outlets = $query->get(['id_outlet as id', 'nama_outlet as name', 'qr_code']);
        return response()->json(['outlets' => $outlets]);
    }

    /**
     * API: Get qr_code for current user's outlet
     */
    public function myOutletQr()
    {
        $user = auth()->user();
        $qr_code = null;
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $qr_code = \DB::table('tbl_data_outlet')->where('id_outlet', $user->id_outlet)->value('qr_code');
        }
        return response()->json(['qr_code' => $qr_code]);
    }

    public function reportItemEngineering(Request $request)
    {
        $outlet = $request->input('outlet');
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
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
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
        if ($outlet) {
            $query->where('orders.kode_outlet', $outlet);
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
        
        // Retail Food
        $retailFoods = \App\Models\RetailFood::with(['items', 'invoices'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
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
                            'file_path' => $inv->file_path ? (\Storage::disk('public')->url($inv->file_path)) : null
                        ];
                    }),
                ];
            });
        // Retail Non Food
        $retailNonFoods = \App\Models\RetailNonFood::with(['items', 'invoices'])
            ->where('outlet_id', $outletId)
            ->whereDate('transaction_date', $date)
            ->where('status', 'approved')
            ->get()
            ->map(function($rnf) {
                return [
                    'id' => $rnf->id,
                    'retail_number' => $rnf->retail_number,
                    'transaction_date' => $rnf->transaction_date,
                    'total_amount' => $rnf->total_amount,
                    'notes' => $rnf->notes,
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
                            'file_path' => $inv->file_path ? (\Storage::disk('public')->url($inv->file_path)) : null
                        ];
                    }),
                ];
            });
        \Log::info('apiOutletExpenses result', [
            'retail_food_count' => $retailFoods->count(),
            'retail_non_food_count' => $retailNonFoods->count(),
            'retail_food_ids' => $retailFoods->pluck('id'),
            'retail_non_food_ids' => $retailNonFoods->pluck('id'),
        ]);
        return response()->json([
            'retail_food' => $retailFoods,
            'retail_non_food' => $retailNonFoods,
        ]);
    }
} 