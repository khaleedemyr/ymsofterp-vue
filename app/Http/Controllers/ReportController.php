<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
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
        if ($request->filled('tanggal')) {
            $query->whereDate('gr.receive_date', $request->tanggal);
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
                'tanggal' => $request->tanggal,
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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
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
            ->join('food_floor_order_items as fo', 'i.item_id', '=', 'fo.item_id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->select(
                'o.nama_outlet as customer',
                DB::raw("SUM(CASE WHEN w.name = 'MAIN KITCHEN' THEN i.received_qty * fo.price ELSE 0 END) as main_kitchen"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name NOT IN ('Chemical', 'Stationary', 'Marketing') THEN i.received_qty * fo.price ELSE 0 END) as main_store"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Chemical' THEN i.received_qty * fo.price ELSE 0 END) as chemical"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Stationary' THEN i.received_qty * fo.price ELSE 0 END) as stationary"),
                DB::raw("SUM(CASE WHEN w.name = 'MAIN STORE' AND sc.name = 'Marketing' THEN i.received_qty * fo.price ELSE 0 END) as marketing"),
                DB::raw('SUM(i.received_qty * fo.price) as line_total')
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
} 