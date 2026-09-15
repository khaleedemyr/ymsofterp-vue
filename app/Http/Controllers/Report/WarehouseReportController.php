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
 * - exportReceivingSheet: Export receiving sheet to Excel
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
     * Combines data from outlet_food_good_receives, good_receive_outlet_suppliers, and outlet_serial_receive
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
        $outlets = $this->getCachedActiveOutlets();
        $items = $this->buildGoodReceiveOutletPivotItems($tanggal);

        return Inertia::render('Report/ReportGoodReceiveOutlet', [
            'outlets' => $outlets,
            'items' => $items,
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
        $outlets = $this->getCachedActiveOutlets();
        $items = $this->buildGoodReceiveOutletPivotItems($tanggal);

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
     * Pivot GR outlet per tanggal: Food + Supplier + Nomor Seri.
     */
    private function buildGoodReceiveOutletPivotItems(string $tanggal): array
    {
        $data = $this->fetchGoodReceiveOutletRawRows($tanggal);

        $pivot = [];
        foreach ($data as $row) {
            $key = $row->item_id . '|' . $row->unit_name;
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'item_name' => $row->item_name,
                    'unit_name' => $row->unit_name,
                ];
            }
            if (isset($pivot[$key][$row->nama_outlet])) {
                $pivot[$key][$row->nama_outlet] += $row->qty;
            } else {
                $pivot[$key][$row->nama_outlet] = $row->qty;
            }
        }

        return array_values($pivot);
    }

    /**
     * Baris agregat GR per item, unit, outlet (sumber: food, supplier, serial).
     */
    private function fetchGoodReceiveOutletRawRows(string $tanggal)
    {
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
                DB::raw('SUM(i.received_qty) as qty')
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

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
                DB::raw('SUM(i.qty_received) as qty')
            )
            ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
            ->get();

        $data = $data1->concat($data2);

        if ($this->rekapFjHasSerialGrTables()) {
            $data3 = DB::table('outlet_serial_receive_headers as h')
                ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->join('units as u', 'si.unit_id', '=', 'u.id')
                ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
                ->whereDate('h.receive_date', $tanggal)
                ->whereNull('h.deleted_at')
                ->select(
                    'it.id as item_id',
                    'it.name as item_name',
                    'u.name as unit_name',
                    'o.id_outlet',
                    'o.nama_outlet',
                    DB::raw('SUM(si.qty) as qty')
                )
                ->groupBy('it.id', 'it.name', 'u.name', 'o.id_outlet', 'o.nama_outlet')
                ->get();

            $data = $data->concat($data3);
        }

        return $data;
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
        $payload = $this->buildReceivingSheetPayload($request);
        $user = auth()->user();

        $outlets = $this->getCachedActiveOutletsIdName();
        if ($user->id_outlet != 1) {
            $outlets = collect($outlets)
                ->where('id_outlet', $user->id_outlet)
                ->values();
        }

        return Inertia::render('Report/ReceivingSheet', [
            'report' => $payload['report'],
            'outlets' => $outlets,
            'warehouseColumns' => $payload['warehouseColumns'],
            'suppliers' => $payload['suppliers'],
            'filters' => $payload['filters'],
            'user' => $user,
        ]);
    }

    /**
     * Export Receiving Sheet to Excel (warna kolom sama dengan UI).
     */
    public function exportReceivingSheet(Request $request)
    {
        $payload = $this->buildReceivingSheetPayload($request);
        $outletLabel = 'Semua Outlet';
        if (! empty($payload['filters']['outlet'])) {
            $outletLabel = DB::table('tbl_data_outlet')
                ->where('id_outlet', $payload['filters']['outlet'])
                ->value('nama_outlet') ?: ('Outlet #'.$payload['filters']['outlet']);
        }

        $filename = 'Receiving_Sheet_'.date('Y-m-d_His').'.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ReceivingSheetExport(
                $payload['report']->all(),
                $payload['warehouseColumns'],
                $payload['suppliers']->all(),
                [
                    'outlet_label' => $outletLabel,
                    'date_from' => $payload['filters']['date_from'] ?: '-',
                    'date_to' => $payload['filters']['date_to'] ?: '-',
                ]
            ),
            $filename
        );
    }

    /**
     * Build Receiving Sheet rows + columns (shared by page + Excel export).
     *
     * @return array{
     *   report: \Illuminate\Support\Collection,
     *   warehouseColumns: list<array{key: string, name: string}>,
     *   suppliers: \Illuminate\Support\Collection,
     *   filters: array{outlet: mixed, date_from: mixed, date_to: mixed}
     * }
     */
    private function buildReceivingSheetPayload(Request $request): array
    {
        $outlet = $request->input('outlet');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $user = auth()->user();
        if ($user->id_outlet != 1) {
            $outlet = $user->id_outlet;
        }

        $outletQrCode = null;
        if ($outlet) {
            $outletQrCode = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outlet)
                ->value('qr_code');
        }

        $salesQuery = DB::table('orders')
            ->select(
                DB::raw('DATE(created_at) as tanggal'),
                DB::raw('SUM(grand_total) as omzet')
            );
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

        $warehouseSpendByDate = [];
        $addWarehouseSpend = function (array &$warehouseSpendByDate, $date, $warehouseName, $amount): void {
            $date = (string) $date;
            $amount = (float) $amount;
            $bucket = $this->receivingSheetWarehouseBucket($warehouseName);
            if ($date === '' || $amount == 0.0 || $bucket === null) {
                return;
            }
            if (! isset($warehouseSpendByDate[$date])) {
                $warehouseSpendByDate[$date] = [
                    'main_store' => 0.0,
                    'mk1' => 0.0,
                    'mk2' => 0.0,
                ];
            }
            $warehouseSpendByDate[$date][$bucket] += $amount;
        };

        $grSpendQuery = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as fpl', 'do.packing_list_id', '=', 'fpl.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ffoi.floor_order_id', '=', 'ffo.id')
                    ->on('ffoi.item_id', '=', 'ofgri.item_id');
            })
            ->leftJoin('warehouse_division as wd', 'fpl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->whereNull('ofgr.deleted_at')
            ->whereNotNull('w.id')
            ->select(
                'ofgr.receive_date as tanggal',
                'w.name as warehouse_name',
                DB::raw('SUM(ofgri.received_qty * COALESCE(ffoi.price, 0)) as total')
            );
        if ($outlet) {
            $grSpendQuery->where('ofgr.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $grSpendQuery->whereDate('ofgr.receive_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $grSpendQuery->whereDate('ofgr.receive_date', '<=', $dateTo);
        }
        foreach ($grSpendQuery->groupBy('ofgr.receive_date', 'w.name')->get() as $row) {
            $addWarehouseSpend($warehouseSpendByDate, $row->tanggal, $row->warehouse_name, $row->total);
        }

        if ($this->rekapFjHasSerialGrTables()) {
            $gsrPriceExpr = $this->rekapFjSerialGrEffectivePriceSql('it');
            $gsrSpendQuery = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->whereNotNull('w.id')
                ->select(
                    'h.receive_date as tanggal',
                    'w.name as warehouse_name',
                    DB::raw("SUM(si.qty * ({$gsrPriceExpr})) as total")
                );
            if ($outlet) {
                $gsrSpendQuery->where('h.outlet_id', $outlet);
            }
            if ($dateFrom) {
                $gsrSpendQuery->whereDate('h.receive_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $gsrSpendQuery->whereDate('h.receive_date', '<=', $dateTo);
            }
            foreach ($gsrSpendQuery->groupBy('h.receive_date', 'w.name')->get() as $row) {
                $addWarehouseSpend($warehouseSpendByDate, $row->tanggal, $row->warehouse_name, $row->total);
            }
        }

        $rwsSpendQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', DB::raw('COALESCE(wd.warehouse_id, rws.warehouse_id)'));
            })
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->whereNotNull('w.id')
            ->select(
                'rws.sale_date as tanggal',
                'w.name as warehouse_name',
                DB::raw('SUM(COALESCE(rws.total_amount, 0)) as total')
            );
        if ($outlet) {
            $rwsSpendQuery->where('c.id_outlet', $outlet);
        }
        if ($dateFrom) {
            $rwsSpendQuery->whereDate('rws.sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $rwsSpendQuery->whereDate('rws.sale_date', '<=', $dateTo);
        }
        foreach ($rwsSpendQuery->groupBy('rws.sale_date', 'w.name')->get() as $row) {
            $addWarehouseSpend($warehouseSpendByDate, $row->tanggal, $row->warehouse_name, $row->total);
        }

        $retailSupplierQuery = DB::table('retail_food as rf')
            ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereNotNull('rf.supplier_id')
            ->select(
                'rf.transaction_date as tanggal',
                's.id as supplier_id',
                's.name as supplier_name',
                DB::raw('SUM(COALESCE(rf.total_amount, 0)) as total')
            );
        if ($outlet) {
            $retailSupplierQuery->where('rf.outlet_id', $outlet);
        }
        if ($dateFrom) {
            $retailSupplierQuery->whereDate('rf.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $retailSupplierQuery->whereDate('rf.transaction_date', '<=', $dateTo);
        }
        $retailSupplierData = $retailSupplierQuery
            ->groupBy('rf.transaction_date', 's.id', 's.name')
            ->get();

        $suppliers = $retailSupplierData->map(function ($row) {
            return [
                'id' => $row->supplier_id,
                'name' => $row->supplier_name,
            ];
        })->unique('id')->sortBy('name')->values();

        $supplierSpendByDate = [];
        foreach ($retailSupplierData as $row) {
            $date = (string) $row->tanggal;
            $sid = $row->supplier_id;
            if (! isset($supplierSpendByDate[$date])) {
                $supplierSpendByDate[$date] = [];
            }
            $supplierSpendByDate[$date][$sid] = (float) $row->total;
        }

        $warehouseColumns = [
            ['key' => 'main_store', 'name' => 'Main Store'],
            ['key' => 'mk1', 'name' => 'MK1 Hot Kitchen'],
            ['key' => 'mk2', 'name' => 'MK2 Cold Kitchen'],
        ];

        $allDates = collect($salesData->keys())
            ->merge(collect($warehouseSpendByDate)->keys())
            ->merge(collect($supplierSpendByDate)->keys())
            ->unique()
            ->sort();

        $report = [];
        foreach ($allDates as $date) {
            $date = (string) $date;
            $mainStore = (float) ($warehouseSpendByDate[$date]['main_store'] ?? 0);
            $mk1 = (float) ($warehouseSpendByDate[$date]['mk1'] ?? 0);
            $mk2 = (float) ($warehouseSpendByDate[$date]['mk2'] ?? 0);
            $supplierTotal = array_sum($supplierSpendByDate[$date] ?? []);
            $cost = $mainStore + $mk1 + $mk2 + $supplierTotal;
            $omzet = (float) ($salesData->get($date)?->omzet ?? 0);
            $persentase = $omzet > 0 ? ($cost / $omzet) * 100 : 0;

            $row = [
                'tanggal' => $date,
                'omzet' => $omzet,
                'main_store' => $mainStore,
                'mk1' => $mk1,
                'mk2' => $mk2,
                'cost' => $cost,
                'persentase_cost' => round($persentase, 2),
            ];
            foreach ($suppliers as $sp) {
                $row['supplier_'.$sp['id']] = (float) ($supplierSpendByDate[$date][$sp['id']] ?? 0);
            }
            $report[] = $row;
        }

        return [
            'report' => collect($report)->sortByDesc('tanggal')->values(),
            'warehouseColumns' => $warehouseColumns,
            'suppliers' => $suppliers,
            'filters' => [
                'outlet' => $outlet,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }

    /**
     * Map nama warehouse ke bucket kolom Receiving Sheet.
     */
    private function receivingSheetWarehouseBucket(?string $warehouseName): ?string
    {
        $whName = strtoupper(trim((string) $warehouseName));
        if ($whName === '') {
            return null;
        }
        if ($whName === 'MAIN STORE' || str_contains($whName, 'MAIN STORE')) {
            return 'main_store';
        }
        if ($whName === 'MK1 HOT KITCHEN' || str_starts_with($whName, 'MK1')) {
            return 'mk1';
        }
        if ($whName === 'MK2 COLD KITCHEN' || str_starts_with($whName, 'MK2')) {
            return 'mk2';
        }

        return null;
    }

    /**
     * Detail lazy-load Receiving Sheet (warehouse / supplier Retail Food).
     */
    public function receivingSheetDetail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:warehouse,supplier',
            'key' => 'required|string',
            'outlet' => 'nullable',
        ]);

        $user = auth()->user();
        $outlet = $request->input('outlet');
        if ($user->id_outlet != 1) {
            $outlet = $user->id_outlet;
        }
        if (! $outlet) {
            return response()->json(['error' => 'Outlet wajib dipilih'], 422);
        }

        $date = $request->input('date');
        $type = $request->input('type');
        $key = $request->input('key');

        if ($type === 'supplier') {
            return response()->json($this->receivingSheetSupplierDetail((int) $outlet, $date, (int) $key));
        }

        if (! in_array($key, ['main_store', 'mk1', 'mk2'], true)) {
            return response()->json(['error' => 'Warehouse key tidak valid'], 422);
        }

        return response()->json($this->receivingSheetWarehouseDetail((int) $outlet, $date, $key));
    }

    private function receivingSheetWarehouseDetail(int $outletId, string $date, string $bucket): array
    {
        $labels = [
            'main_store' => 'Main Store',
            'mk1' => 'MK1 Hot Kitchen',
            'mk2' => 'MK2 Cold Kitchen',
        ];
        $transactions = [];

        // GR Food
        $grRows = DB::table('outlet_food_good_receive_items as ofgri')
            ->join('outlet_food_good_receives as ofgr', 'ofgri.outlet_food_good_receive_id', '=', 'ofgr.id')
            ->join('delivery_orders as do', 'ofgr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as fpl', 'do.packing_list_id', '=', 'fpl.id')
            ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
            ->leftJoin('food_floor_order_items as ffoi', function ($join) {
                $join->on('ffoi.floor_order_id', '=', 'ffo.id')
                    ->on('ffoi.item_id', '=', 'ofgri.item_id');
            })
            ->leftJoin('warehouse_division as wd', 'fpl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->leftJoin('items as it', 'ofgri.item_id', '=', 'it.id')
            ->leftJoin('units as u', 'ofgri.unit_id', '=', 'u.id')
            ->leftJoin('users as usr', 'ffo.user_id', '=', 'usr.id')
            ->whereNull('ofgr.deleted_at')
            ->where('ofgr.outlet_id', $outletId)
            ->whereDate('ofgr.receive_date', $date)
            ->whereNotNull('w.name')
            ->select(
                'ofgr.id as txn_id',
                'ofgr.number as txn_number',
                'ffo.order_number as ro_number',
                'usr.nama_lengkap as ordered_by',
                'it.name as item_name',
                'u.name as unit_name',
                'ofgri.received_qty as qty',
                DB::raw('COALESCE(ffoi.price, 0) as price'),
                DB::raw('(ofgri.received_qty * COALESCE(ffoi.price, 0)) as subtotal'),
                'w.name as warehouse_name'
            )
            ->orderBy('ofgr.number')
            ->orderBy('it.name')
            ->get()
            ->filter(fn ($row) => $this->receivingSheetWarehouseBucket($row->warehouse_name) === $bucket);

        foreach ($grRows->groupBy('txn_id') as $txnId => $items) {
            $first = $items->first();
            $transactions[] = [
                'source' => 'GR',
                'number' => $first->txn_number,
                'ro_number' => $first->ro_number,
                'ordered_by' => $first->ordered_by ?: '-',
                'total' => round($items->sum('subtotal'), 2),
                'items' => $items->map(fn ($i) => [
                    'name' => $i->item_name,
                    'qty' => (float) $i->qty,
                    'unit' => $i->unit_name ?: '-',
                    'price' => (float) $i->price,
                    'subtotal' => (float) $i->subtotal,
                ])->values(),
            ];
        }

        // GSR
        if ($this->rekapFjHasSerialGrTables()) {
            $gsrPriceExpr = $this->rekapFjSerialGrEffectivePriceSql('it');
            $gsrRows = DB::table('outlet_serial_receive_items as si')
                ->join('outlet_serial_receive_headers as h', 'si.header_id', '=', 'h.id')
                ->join('items as it', 'si.item_id', '=', 'it.id')
                ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
                ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
                ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
                ->leftJoin('users as usr', 'h.created_by', '=', 'usr.id')
                ->leftJoin('delivery_orders as do', 'si.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_orders as ffo', 'do.floor_order_id', '=', 'ffo.id')
                ->whereNull('h.deleted_at')
                ->where('h.status', 'completed')
                ->where('h.outlet_id', $outletId)
                ->whereDate('h.receive_date', $date)
                ->whereNotNull('w.name')
                ->select(
                    'h.id as txn_id',
                    'h.number as txn_number',
                    'ffo.order_number as ro_number',
                    'usr.nama_lengkap as ordered_by',
                    'it.name as item_name',
                    'u.name as unit_name',
                    DB::raw('SUM(si.qty) as qty'),
                    DB::raw("CASE WHEN SUM(si.qty) > 0 THEN SUM(si.qty * ({$gsrPriceExpr})) / SUM(si.qty) ELSE MAX({$gsrPriceExpr}) END as price"),
                    DB::raw("SUM(si.qty * ({$gsrPriceExpr})) as subtotal"),
                    'w.name as warehouse_name'
                )
                ->groupBy(
                    'h.id',
                    'h.number',
                    'ffo.order_number',
                    'usr.nama_lengkap',
                    'it.name',
                    'u.name',
                    'w.name',
                    'it.warehouse_division_id',
                    'si.item_id',
                    'si.unit_id'
                )
                ->get()
                ->filter(fn ($row) => $this->receivingSheetWarehouseBucket($row->warehouse_name) === $bucket);

            foreach ($gsrRows->groupBy('txn_id') as $txnId => $items) {
                $first = $items->first();
                $transactions[] = [
                    'source' => 'GSR',
                    'number' => $first->txn_number,
                    'ro_number' => $first->ro_number,
                    'ordered_by' => $first->ordered_by ?: '-',
                    'total' => round($items->sum('subtotal'), 2),
                    'items' => $items->map(fn ($i) => [
                        'name' => $i->item_name,
                        'qty' => (float) $i->qty,
                        'unit' => $i->unit_name ?: '-',
                        'price' => (float) $i->price,
                        'subtotal' => (float) $i->subtotal,
                    ])->values(),
                ];
            }
        }

        // RWS
        $rwsHeaders = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('warehouse_division as wd', 'rws.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', DB::raw('COALESCE(wd.warehouse_id, rws.warehouse_id)'));
            })
            ->leftJoin('users as usr', 'rws.created_by', '=', 'usr.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->where('c.id_outlet', $outletId)
            ->whereDate('rws.sale_date', $date)
            ->whereNotNull('w.name')
            ->select(
                'rws.id as txn_id',
                'rws.number as txn_number',
                'usr.nama_lengkap as ordered_by',
                'w.name as warehouse_name',
                'rws.total_amount'
            )
            ->get()
            ->filter(fn ($row) => $this->receivingSheetWarehouseBucket($row->warehouse_name) === $bucket);

        if ($rwsHeaders->isNotEmpty()) {
            $rwsIds = $rwsHeaders->pluck('txn_id')->unique()->values();
            $rwsItems = DB::table('retail_warehouse_sale_items as rwsi')
                ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                ->whereIn('rwsi.retail_warehouse_sale_id', $rwsIds)
                ->select(
                    'rwsi.retail_warehouse_sale_id as txn_id',
                    'i.name as item_name',
                    'rwsi.qty',
                    'rwsi.unit as unit_name',
                    'rwsi.price',
                    'rwsi.subtotal'
                )
                ->get()
                ->groupBy('txn_id');

            foreach ($rwsHeaders as $header) {
                $items = $rwsItems->get($header->txn_id, collect());
                $transactions[] = [
                    'source' => 'RWS',
                    'number' => $header->txn_number,
                    'ro_number' => null,
                    'ordered_by' => $header->ordered_by ?: '-',
                    'total' => round((float) ($items->sum('subtotal') ?: $header->total_amount), 2),
                    'items' => $items->map(fn ($i) => [
                        'name' => $i->item_name,
                        'qty' => (float) $i->qty,
                        'unit' => $i->unit_name ?: '-',
                        'price' => (float) $i->price,
                        'subtotal' => (float) $i->subtotal,
                    ])->values(),
                ];
            }
        }

        usort($transactions, fn ($a, $b) => strcmp((string) $a['number'], (string) $b['number']));

        return [
            'title' => ($labels[$bucket] ?? $bucket).' — '.$date,
            'type' => 'warehouse',
            'key' => $bucket,
            'date' => $date,
            'transactions' => array_values($transactions),
            'grand_total' => round(collect($transactions)->sum('total'), 2),
        ];
    }

    private function receivingSheetSupplierDetail(int $outletId, string $date, int $supplierId): array
    {
        $supplierName = DB::table('suppliers')->where('id', $supplierId)->value('name') ?: 'Supplier';

        $headers = DB::table('retail_food as rf')
            ->leftJoin('users as usr', 'rf.created_by', '=', 'usr.id')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->where('rf.outlet_id', $outletId)
            ->where('rf.supplier_id', $supplierId)
            ->whereDate('rf.transaction_date', $date)
            ->select(
                'rf.id as txn_id',
                'rf.retail_number as txn_number',
                'usr.nama_lengkap as ordered_by',
                'rf.total_amount'
            )
            ->orderBy('rf.retail_number')
            ->get();

        $itemsByTxn = collect();
        if ($headers->isNotEmpty()) {
            $itemsByTxn = DB::table('retail_food_items as rfi')
                ->whereIn('rfi.retail_food_id', $headers->pluck('txn_id'))
                ->select(
                    'rfi.retail_food_id as txn_id',
                    'rfi.item_name as item_name',
                    'rfi.qty',
                    'rfi.unit as unit_name',
                    'rfi.price',
                    'rfi.subtotal'
                )
                ->orderBy('rfi.item_name')
                ->get()
                ->groupBy('txn_id');
        }

        $transactions = [];
        foreach ($headers as $header) {
            $items = $itemsByTxn->get($header->txn_id, collect());
            $transactions[] = [
                'source' => 'Retail Food',
                'number' => $header->txn_number,
                'ro_number' => null,
                'ordered_by' => $header->ordered_by ?: '-',
                'total' => round((float) ($items->sum('subtotal') ?: $header->total_amount), 2),
                'items' => $items->map(fn ($i) => [
                    'name' => $i->item_name,
                    'qty' => (float) $i->qty,
                    'unit' => $i->unit_name ?: '-',
                    'price' => (float) $i->price,
                    'subtotal' => (float) $i->subtotal,
                ])->values(),
            ];
        }

        return [
            'title' => $supplierName.' — '.$date,
            'type' => 'supplier',
            'key' => (string) $supplierId,
            'date' => $date,
            'transactions' => $transactions,
            'grand_total' => round(collect($transactions)->sum('total'), 2),
        ];
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

        // GR Food + GR Serial (sama dengan rekap FJ utama)
        $getGRData = function ($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $food = $this->rekapFjFetchFoodGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);
            $serial = $this->rekapFjFetchSerialGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);

            return $this->rekapFjMergeFjDetailRows($food, $serial);
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

        $getGRData = function ($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $food = $this->rekapFjFetchFoodGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);
            $serial = $this->rekapFjFetchSerialGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);

            return $this->rekapFjMergeFjDetailRows($food, $serial);
        };

        $mainKitchenGR = $getGRData(['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        $mainStoreGR = $getGRData('MAIN STORE', null, ['Chemical', 'Stationary', 'Marketing']);
        $chemicalGR = $getGRData('MAIN STORE', 'Chemical');
        $stationaryGR = $getGRData('MAIN STORE', 'Stationary');
        $marketingGR = $getGRData('MAIN STORE', 'Marketing');

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

        $getGRData = function ($warehouseCondition, $subCategoryCondition = null, $excludeSubCategories = null) use ($customer, $from, $to) {
            $food = $this->rekapFjFetchFoodGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);
            $serial = $this->rekapFjFetchSerialGrDetailRows($customer, $from, $to, $warehouseCondition, $subCategoryCondition, $excludeSubCategories);

            return $this->rekapFjMergeFjDetailRows($food, $serial);
        };

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
