<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Traits\ReportHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Warehouse Report Controller
 * 
 * Handles warehouse, distribution, receiving, and FJ (Food & Juice) reports
 * Split from ReportController for better organization and performance
 * 
 * This is the MOST COMPLEX controller with:
 * - Heavy data processing
 * - Multiple separate queries
 * - Nested loops and aggregations
 * - Helper functions (closures) for reusable logic
 * 
 * Functions:
 * - reportGoodReceiveOutlet: Good receive pivot report per outlet
 * - exportGoodReceiveOutlet: Export to Excel
 * - reportReceivingSheet: Receiving sheet (cost vs sales comparison) - CRITICAL COMPLEX
 * - fjDetail: FJ distribution detail report - CRITICAL COMPLEX
 * - fjDetailPdf: Export FJ detail to PDF
 * - fjDetailExcel: Export FJ detail to Excel
 * - warehouseSalesDetail: Warehouse sales detail API
 * - warehouseDetailPdf: Export warehouse detail to PDF
 * - warehouseDetailExcel: Export warehouse detail to Excel
 */
class WarehouseReportController extends Controller
{
    use ReportHelperTrait;
    
    /**
     * Report Good Receive Outlet
     * 
     * Pivot report showing good receives per outlet per date
     * Combines data from outlet_food_good_receives and good_receive_outlet_suppliers
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
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

        // Ambil semua outlet aktif (using cached helper)
        $outlets = $this->getCachedActiveOutlets();

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

    /**
     * Export Good Receive Outlet
     * 
     * Export pivot report to Excel
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportGoodReceiveOutlet(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
        ]);

        $tanggal = $request->tanggal;

        // Ambil semua outlet aktif (using cached helper)
        $outlets = $this->getCachedActiveOutlets();

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

    /**
     * Report Receiving Sheet
     * 
     * CRITICAL COMPLEX FUNCTION - Shows daily cost vs sales comparison
     * 
     * Combines data from multiple sources:
     * - outlet_food_good_receives (cost data)
     * - retail_food (approved cash purchases)
     * - orders (sales/omzet data)
     * - good_receive_outlet_suppliers (supplier direct purchases)
     * - Breakdown per warehouse and supplier
     * 
     * Performance Warning: Multiple separate queries that could be optimized
     * 
     * @param Request $request
     * @return \Inertia\Response
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

        // Get outlets for filter (using cached helper)
        $outlets = $this->getCachedActiveOutletsIdName();

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

    /**
     * FJ Detail - Food & Juice Distribution Detail
     * 
     * CRITICAL COMPLEX FUNCTION - Returns detailed FJ distribution items
     * 
     * Breakdown by warehouse categories:
     * - Main Kitchen (MK1 Hot Kitchen, MK2 Cold Kitchen)
     * - Main Store (excluding Chemical, Stationary, Marketing)
     * - Chemical
     * - Stationary
     * - Marketing
     * 
     * Uses helper functions (closures) for reusable query logic
     * Only uses GR data (not GR Supplier) to match main report
     * 
     * Performance Warning: Multiple queries with complex JOINs
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * FJ Detail PDF
     * 
     * Generate PDF for FJ distribution detail
     * Uses same helper function logic as fjDetail()
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
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

            // Calculate totals
            $mainKitchenTotal = $mainKitchen->sum('subtotal');
            $mainStoreTotal = $mainStore->sum('subtotal');
            $chemicalTotal = $chemical->sum('subtotal');
            $stationaryTotal = $stationary->sum('subtotal');
            $marketingTotal = $marketing->sum('subtotal');
            $grandTotal = $mainKitchenTotal + $mainStoreTotal + $chemicalTotal + $stationaryTotal + $marketingTotal;

            // Generate PDF
            $pdf = \PDF::loadView('reports.fj-detail-pdf', [
                'customer' => $customer,
                'from' => $from,
                'to' => $to,
                'mainKitchen' => $mainKitchen,
                'mainStore' => $mainStore,
                'chemical' => $chemical,
                'stationary' => $stationary,
                'marketing' => $marketing,
                'mainKitchenTotal' => $mainKitchenTotal,
                'mainStoreTotal' => $mainStoreTotal,
                'chemicalTotal' => $chemicalTotal,
                'stationaryTotal' => $stationaryTotal,
                'marketingTotal' => $marketingTotal,
                'grandTotal' => $grandTotal,
            ]);

            // Optimize PDF settings for compact layout
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('dpi', 96);

            // Clean filename from invalid characters and ensure it's safe
            $cleanCustomer = preg_replace('/[^a-zA-Z0-9\s\-_]/', '_', $customer);
            $cleanCustomer = trim($cleanCustomer);
            $cleanCustomer = preg_replace('/\s+/', '_', $cleanCustomer);
            $filename = "FJ_Detail_{$cleanCustomer}_{$from}_{$to}.pdf";
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('FJ Detail PDF Error: ' . $e->getMessage(), [
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
     * FJ Detail Excel
     * 
     * Export FJ distribution detail to Excel
     * Uses same helper function logic as fjDetail()
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
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

        // Helper function to get GR data (same as fjDetail and fjDetailPdf)
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
            Log::error('FJ Detail Excel error: ' . $e->getMessage());
            Log::error('FJ Detail Excel error trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Warehouse Sales Detail
     * 
     * Returns detailed items for warehouse sales
     * Grouped by sub-category
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * Warehouse Detail PDF
     * 
     * Generate PDF for warehouse sales detail
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
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
            Log::error('Warehouse Detail PDF Error: ' . $e->getMessage(), [
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
     * Warehouse Detail Excel
     * 
     * Export warehouse sales detail to Excel
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
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
                ->join('warehouse_sale_items as wsi', 'ws.id', '=', 'wsi.warehouse_sale_id')
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
            Log::error('Warehouse Detail Excel error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate Excel: ' . $e->getMessage()], 500);
        }
    }
}
