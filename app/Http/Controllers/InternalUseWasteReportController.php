<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Exports\InternalUseWasteReportExport;
use App\Exports\InternalUseWasteReportSummaryExport;
use Maatwebsite\Excel\Facades\Excel;

class InternalUseWasteReportController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        
        // Get outlets
        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        
        // Get warehouse outlets
        $warehouseOutlets = [];
        if ($outletId) {
            $warehouseOutlets = DB::table('warehouse_outlets')
                ->where('outlet_id', $outletId)
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray();
        }
        
        $reportData = [];
        $totalItems = 0;
        
        if ($outletId && $startDate && $endDate) {
            // Query untuk mengambil data internal use waste dengan type RnD, Marketing, Wrong Maker
            $query = DB::table('outlet_internal_use_waste_headers as h')
                ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
                ->join('items as item', 'd.item_id', '=', 'item.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
                ->leftJoin('units as u_small', 'item.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'item.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'item.large_unit_id', '=', 'u_large.id')
                ->where('h.outlet_id', $outletId)
                ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
                ->where('h.status', 'APPROVED') // Hanya yang sudah approved
                ->whereBetween('h.date', [$startDate, $endDate])
                ->select(
                    'h.id as header_id',
                    'h.number as header_number',
                    'h.date',
                    'h.type',
                    'h.status',
                    'item.id as item_id',
                    'item.name as item_name',
                    'item.sku as item_code',
                    'd.qty',
                    'd.unit_id',
                    'fi.id as inventory_item_id',
                    'wo.name as warehouse_outlet_name',
                    'u_small.name as small_unit_name',
                    'u_medium.name as medium_unit_name',
                    'u_large.name as large_unit_name',
                    'item.small_unit_id',
                    'item.medium_unit_id',
                    'item.large_unit_id',
                    'item.small_conversion_qty',
                    'item.medium_conversion_qty',
                    'h.warehouse_outlet_id'
                );
            
            // Filter warehouse outlet (jika dipilih, jika "all" maka tidak difilter)
            if ($warehouseOutletId && $warehouseOutletId !== 'all') {
                $query->where('h.warehouse_outlet_id', $warehouseOutletId);
            }
            
            // Search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('item.name', 'like', "%{$search}%")
                      ->orWhere('item.sku', 'like', "%{$search}%")
                      ->orWhere('h.type', 'like', "%{$search}%");
                });
            }
            
            // Get total count for pagination
            $totalItems = $query->count();
            
            // Pagination untuk query utama
            $offset = ($page - 1) * $perPage;
            
            $items = $query->orderBy('h.date', 'desc')
                ->orderBy('h.type', 'asc')
                ->orderBy('item.name', 'asc')
                ->offset($offset)
                ->limit($perPage)
                ->get();
            
            // ===== OPTIMIZED: Load MAC data sekaligus dengan subquery =====
            $inventoryItemIds = $items->pluck('inventory_item_id')->filter()->unique()->toArray();
            $macDataCache = [];
            $stockDataCache = [];
            
            if (!empty($inventoryItemIds)) {
                // Ambil MAC dari cost_histories untuk semua items sekaligus menggunakan subquery
                $macData = DB::table('outlet_food_inventory_cost_histories as ch1')
                    ->select(
                        'ch1.inventory_item_id',
                        'ch1.warehouse_outlet_id',
                        'ch1.mac'
                    )
                    ->whereIn('ch1.inventory_item_id', $inventoryItemIds)
                    ->where('ch1.id_outlet', $outletId)
                    ->where('ch1.date', '<=', $endDate)
                    ->whereRaw('ch1.id = (
                        SELECT ch2.id 
                        FROM outlet_food_inventory_cost_histories ch2 
                        WHERE ch2.inventory_item_id = ch1.inventory_item_id 
                        AND ch2.warehouse_outlet_id = ch1.warehouse_outlet_id 
                        AND ch2.id_outlet = ch1.id_outlet 
                        AND ch2.date <= ?
                        ORDER BY ch2.date DESC, ch2.id DESC 
                        LIMIT 1
                    )', [$endDate])
                    ->get();
                
                foreach ($macData as $macRow) {
                    $key = $macRow->inventory_item_id . '_' . $macRow->warehouse_outlet_id;
                    $macDataCache[$key] = (float) ($macRow->mac ?? 0);
                }
                
                // Pre-load stock data sebagai fallback
                $stockData = DB::table('outlet_food_inventory_stocks')
                    ->whereIn('inventory_item_id', $inventoryItemIds)
                    ->where('id_outlet', $outletId)
                    ->select('inventory_item_id', 'warehouse_outlet_id', 'last_cost_small')
                    ->get();
                
                foreach ($stockData as $stock) {
                    $key = $stock->inventory_item_id . '_' . $stock->warehouse_outlet_id;
                    $stockDataCache[$key] = (float) ($stock->last_cost_small ?? 0);
                }
            }
            // ===== END OPTIMIZED MAC LOADING =====
            
            // Ambil data approver untuk setiap header
            $headerIds = $items->pluck('header_id')->unique()->toArray();
            $approvers = [];
            
            if (!empty($headerIds)) {
                $approvalFlows = DB::table('outlet_internal_use_waste_approval_flows as af')
                    ->join('users as u', 'af.approver_id', '=', 'u.id')
                    ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                    ->whereIn('af.header_id', $headerIds)
                    ->where('af.status', 'APPROVED')
                    ->select(
                        'af.header_id',
                        'u.nama_lengkap as approver_name',
                        'j.nama_jabatan as approver_position',
                        'af.approved_at'
                    )
                    ->orderBy('af.approval_level')
                    ->get();
                
                foreach ($approvalFlows as $flow) {
                    $headerId = $flow->header_id;
                    if (!isset($approvers[$headerId])) {
                        $approvers[$headerId] = [
                            'approvers' => [],
                            'last_approved_at' => null,
                        ];
                    }
                    $approvers[$headerId]['approvers'][] = [
                        'name' => $flow->approver_name,
                        'position' => $flow->approver_position,
                        'approved_at' => $flow->approved_at,
                    ];
                    // Simpan tanggal approval terakhir (yang paling akhir)
                    if ($flow->approved_at && (!$approvers[$headerId]['last_approved_at'] || $flow->approved_at > $approvers[$headerId]['last_approved_at'])) {
                        $approvers[$headerId]['last_approved_at'] = $flow->approved_at;
                    }
                }
            }
            
            foreach ($items as $row) {
                // ===== FIX N+1 QUERY: Gunakan data yang sudah di-load sebelumnya =====
                $mac = 0;
                if ($row->inventory_item_id) {
                    $key = $row->inventory_item_id . '_' . $row->warehouse_outlet_id;
                    $mac = $macDataCache[$key] ?? $stockDataCache[$key] ?? 0;
                }
                
                // Konversi qty ke small, medium, large
                $qty = (float) $row->qty;
                $smallConv = (float) ($row->small_conversion_qty ?: 1);
                $mediumConv = (float) ($row->medium_conversion_qty ?: 1);
                
                $qtySmall = 0;
                $qtyMedium = 0;
                $qtyLarge = 0;
                
                if ($row->unit_id == $row->small_unit_id) {
                    $qtySmall = $qty;
                    $qtyMedium = $smallConv > 0 ? $qty / $smallConv : 0;
                    $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qty / ($smallConv * $mediumConv) : 0;
                } elseif ($row->unit_id == $row->medium_unit_id) {
                    $qtyMedium = $qty;
                    $qtySmall = $qty * $smallConv;
                    $qtyLarge = $mediumConv > 0 ? $qty / $mediumConv : 0;
                } elseif ($row->unit_id == $row->large_unit_id) {
                    $qtyLarge = $qty;
                    $qtyMedium = $qty * $mediumConv;
                    $qtySmall = $qty * $mediumConv * $smallConv;
                }
                
                // Format qty dengan konversi (array untuk ditampilkan vertikal)
                $qtyDisplay = [];
                if ($row->small_unit_name && $qtySmall > 0) {
                    $qtyDisplay[] = number_format($qtySmall, 2, ',', '.') . ' ' . $row->small_unit_name;
                }
                if ($row->medium_unit_name && $qtyMedium > 0) {
                    $qtyDisplay[] = number_format($qtyMedium, 2, ',', '.') . ' ' . $row->medium_unit_name;
                }
                if ($row->large_unit_name && $qtyLarge > 0) {
                    $qtyDisplay[] = number_format($qtyLarge, 2, ',', '.') . ' ' . $row->large_unit_name;
                }
                
                if (empty($qtyDisplay)) {
                    $qtyDisplay = ['0'];
                }
                
                // Format type
                $typeDisplay = '';
                switch ($row->type) {
                    case 'r_and_d':
                        $typeDisplay = 'RnD';
                        break;
                    case 'marketing':
                        $typeDisplay = 'Marketing';
                        break;
                    case 'wrong_maker':
                        $typeDisplay = 'Wrong Maker';
                        break;
                    default:
                        $typeDisplay = ucfirst(str_replace('_', ' ', $row->type));
                }
                
                // Ambil approvers untuk header ini
                $headerApproverData = $approvers[$row->header_id] ?? ['approvers' => [], 'last_approved_at' => null];
                $headerApprovers = $headerApproverData['approvers'] ?? [];
                $lastApprovedAt = $headerApproverData['last_approved_at'] ?? null;
                
                $approverDisplay = [];
                foreach ($headerApprovers as $approver) {
                    $approverText = $approver['name'] . ($approver['position'] ? ' (' . $approver['position'] . ')' : '');
                    if ($approver['approved_at']) {
                        $approvedDate = \Carbon\Carbon::parse($approver['approved_at'])->format('d/m/Y H:i');
                        $approverText .= ' - ' . $approvedDate;
                    }
                    $approverDisplay[] = $approverText;
                }
                
                if (empty($approverDisplay)) {
                    $approverDisplay = ['-'];
                }
                
                // Format MAC per unit (gunakan MAC dari cost history, ini sudah per unit small)
                $macPerUnit = $mac;
                
                // Hitung subtotal MAC (qty_small * mac_per_unit)
                $subtotalMac = $qtySmall * $macPerUnit;
                
                $reportData[] = [
                    'date' => $row->date,
                    'type' => $typeDisplay,
                    'type_code' => $row->type,
                    'header_id' => $row->header_id,
                    'header_number' => $row->header_number ?? '-',
                    'item_name' => $row->item_name,
                    'item_code' => $row->item_code,
                    'warehouse_outlet_name' => $row->warehouse_outlet_name ?? '-',
                    'qty' => $qtyDisplay, // Array untuk ditampilkan vertikal
                    'qty_small' => $qtySmall,
                    'qty_medium' => $qtyMedium,
                    'qty_large' => $qtyLarge,
                    'mac_per_unit' => $macPerUnit, // MAC per unit (small)
                    'subtotal_mac' => $subtotalMac, // Qty x MAC
                    'approvers' => $approverDisplay, // Array untuk ditampilkan vertikal (dengan tanggal approval)
                    'warehouse_outlet_id' => $row->warehouse_outlet_id,
                ];
            }
            
            // Hitung grand total MAC dari semua data (bukan hanya yang di-paginate)
            // Ambil semua data tanpa pagination untuk menghitung grand total
            $allItemsQuery = DB::table('outlet_internal_use_waste_headers as h')
                ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
                ->join('items as item', 'd.item_id', '=', 'item.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
                ->leftJoin('units as u_small', 'item.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'item.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'item.large_unit_id', '=', 'u_large.id')
                ->where('h.outlet_id', $outletId)
                ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
                ->where('h.status', 'APPROVED')
                ->whereBetween('h.date', [$startDate, $endDate])
                ->select(
                    'h.id as header_id',
                    'h.date',
                    'd.qty',
                    'd.unit_id',
                    'fi.id as inventory_item_id',
                    'item.small_unit_id',
                    'item.medium_unit_id',
                    'item.large_unit_id',
                    'item.small_conversion_qty',
                    'item.medium_conversion_qty',
                    'h.warehouse_outlet_id'
                );
            
            // Filter warehouse outlet
            if ($warehouseOutletId && $warehouseOutletId !== 'all') {
                $allItemsQuery->where('h.warehouse_outlet_id', $warehouseOutletId);
            }
            
            // Search filter
            if ($search) {
                $allItemsQuery->where(function($q) use ($search) {
                    $q->where('item.name', 'like', "%{$search}%")
                      ->orWhere('item.sku', 'like', "%{$search}%")
                      ->orWhere('h.type', 'like', "%{$search}%");
                });
            }
            
            $allItems = $allItemsQuery->get();
            $grandTotalMac = 0;
            
            // ===== OPTIMIZED: Load MAC data sekaligus untuk grand total =====
            $allInventoryItemIds = $allItems->pluck('inventory_item_id')->filter()->unique()->toArray();
            $allMacDataCache = [];
            $allStockDataCache = [];
            
            if (!empty($allInventoryItemIds)) {
                // Ambil MAC dari cost_histories untuk semua items sekaligus
                $allMacData = DB::table('outlet_food_inventory_cost_histories as ch1')
                    ->select(
                        'ch1.inventory_item_id',
                        'ch1.warehouse_outlet_id',
                        'ch1.mac'
                    )
                    ->whereIn('ch1.inventory_item_id', $allInventoryItemIds)
                    ->where('ch1.id_outlet', $outletId)
                    ->where('ch1.date', '<=', $endDate)
                    ->whereRaw('ch1.id = (
                        SELECT ch2.id 
                        FROM outlet_food_inventory_cost_histories ch2 
                        WHERE ch2.inventory_item_id = ch1.inventory_item_id 
                        AND ch2.warehouse_outlet_id = ch1.warehouse_outlet_id 
                        AND ch2.id_outlet = ch1.id_outlet 
                        AND ch2.date <= ?
                        ORDER BY ch2.date DESC, ch2.id DESC 
                        LIMIT 1
                    )', [$endDate])
                    ->get();
                
                foreach ($allMacData as $macRow) {
                    $key = $macRow->inventory_item_id . '_' . $macRow->warehouse_outlet_id;
                    $allMacDataCache[$key] = (float) ($macRow->mac ?? 0);
                }
                
                // Pre-load stock data sebagai fallback
                $allStockData = DB::table('outlet_food_inventory_stocks')
                    ->whereIn('inventory_item_id', $allInventoryItemIds)
                    ->where('id_outlet', $outletId)
                    ->select('inventory_item_id', 'warehouse_outlet_id', 'last_cost_small')
                    ->get();
                
                foreach ($allStockData as $stock) {
                    $key = $stock->inventory_item_id . '_' . $stock->warehouse_outlet_id;
                    $allStockDataCache[$key] = (float) ($stock->last_cost_small ?? 0);
                }
            }
            // ===== END OPTIMIZED MAC LOADING =====
            
            foreach ($allItems as $row) {
                // ===== FIX N+1 QUERY: Gunakan data yang sudah di-load sebelumnya =====
                $mac = 0;
                if ($row->inventory_item_id) {
                    $key = $row->inventory_item_id . '_' . $row->warehouse_outlet_id;
                    $mac = $allMacDataCache[$key] ?? $allStockDataCache[$key] ?? 0;
                }
                
                // Konversi qty ke small (sama dengan logika di report data)
                $qty = (float) $row->qty;
                $smallConv = (float) ($row->small_conversion_qty ?: 1);
                $mediumConv = (float) ($row->medium_conversion_qty ?: 1);
                
                $qtySmall = 0;
                if ($row->unit_id == $row->small_unit_id) {
                    $qtySmall = $qty;
                } elseif ($row->unit_id == $row->medium_unit_id) {
                    $qtySmall = $qty * $smallConv;
                } elseif ($row->unit_id == $row->large_unit_id) {
                    $qtySmall = $qty * $mediumConv * $smallConv;
                }
                
                // Hitung subtotal MAC
                $subtotalMac = $qtySmall * $mac;
                $grandTotalMac += $subtotalMac;
            }
        } else {
            $grandTotalMac = 0;
        }
        
        // Calculate pagination
        $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 0;
        
        return Inertia::render('InternalUseWasteReport/Index', [
            'reportData' => $reportData,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'grandTotalMac' => $grandTotalMac ?? 0,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'per_page' => $perPage,
                'page' => $page,
            ]
        ]);
    }

    public function export(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');

        if (!$outletId || !$startDate || !$endDate) {
            return redirect()->back()->with('error', 'Outlet, tanggal mulai, dan tanggal akhir harus diisi untuk export.');
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "report_rnd_bm_wm_{$timestamp}.xlsx";

        return Excel::download(
            new InternalUseWasteReportExport($outletId, $warehouseOutletId, $startDate, $endDate, $search),
            $filename
        );
    }

    public function summary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        
        // Get all outlets
        $outlets = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        
        $summaryData = [];
        
        if ($startDate && $endDate) {
            // ===== FIX N+1 QUERY: Pre-load semua data sebelum loop outlet =====
            // Query semua data sekaligus untuk semua outlet
            $allOutletData = DB::table('outlet_internal_use_waste_headers as h')
                ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
                ->join('items as item', 'd.item_id', '=', 'item.id')
                ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                ->whereIn('h.type', ['r_and_d', 'marketing', 'wrong_maker'])
                ->where('h.status', 'APPROVED')
                ->whereBetween('h.date', [$startDate, $endDate])
                ->select(
                    'h.outlet_id',
                    'h.id as header_id',
                    'h.date',
                    'd.qty',
                    'd.unit_id',
                    'fi.id as inventory_item_id',
                    'item.small_unit_id',
                    'item.medium_unit_id',
                    'item.large_unit_id',
                    'item.small_conversion_qty',
                    'item.medium_conversion_qty',
                    'h.warehouse_outlet_id'
                )
                ->get();
            
            // Group by outlet_id
            $dataByOutlet = $allOutletData->groupBy('outlet_id');
            
            // Pre-load MAC data untuk semua inventory items
            $allInventoryItemIds = $allOutletData->pluck('inventory_item_id')->filter()->unique()->toArray();
            $macDataCache = [];
            $stockDataCache = [];
            // Pre-load MAC data untuk semua inventory items
            $allInventoryItemIds = $allOutletData->pluck('inventory_item_id')->filter()->unique()->toArray();
            $macDataCache = [];
            $stockDataCache = [];
            
            if (!empty($allInventoryItemIds)) {
                // Pre-load cost histories
                $costHistories = DB::table('outlet_food_inventory_cost_histories')
                    ->whereIn('inventory_item_id', $allInventoryItemIds)
                    ->where('date', '<=', $endDate)
                    ->select('inventory_item_id', 'outlet_id', 'warehouse_outlet_id', 'mac', 'date')
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->get();
                
                foreach ($costHistories as $cost) {
                    $key = $cost->inventory_item_id . '_' . $cost->outlet_id . '_' . $cost->warehouse_outlet_id;
                    if (!isset($macDataCache[$key])) {
                        $macDataCache[$key] = (float) ($cost->mac ?? 0);
                    }
                }
                
                // Pre-load stock data sebagai fallback
                $stockData = DB::table('outlet_food_inventory_stocks')
                    ->whereIn('inventory_item_id', $allInventoryItemIds)
                    ->select('inventory_item_id', 'id_outlet', 'warehouse_outlet_id', 'last_cost_small')
                    ->get();
                
                foreach ($stockData as $stock) {
                    $key = $stock->inventory_item_id . '_' . $stock->id_outlet . '_' . $stock->warehouse_outlet_id;
                    $stockDataCache[$key] = (float) ($stock->last_cost_small ?? 0);
                }
            }
            // ===== END FIX N+1 QUERY =====
            
            foreach ($outlets as $outlet) {
                // ===== FIX N+1 QUERY: Gunakan data yang sudah di-group =====
                $items = $dataByOutlet->get($outlet->id_outlet, collect());
                
                if ($items->isEmpty()) {
                    continue;
                }
                
                $totalMac = 0;
                
                foreach ($items as $row) {
                    // ===== FIX N+1 QUERY: Gunakan cached MAC data =====
                    $mac = 0;
                    if ($row->inventory_item_id) {
                        $key = $row->inventory_item_id . '_' . $outlet->id_outlet . '_' . $row->warehouse_outlet_id;
                        $mac = $macDataCache[$key] ?? $stockDataCache[$key] ?? 0;
                    }
                    
                    // Konversi qty ke small
                    $qty = (float) $row->qty;
                    $smallConv = (float) ($row->small_conversion_qty ?: 1);
                    $mediumConv = (float) ($row->medium_conversion_qty ?: 1);
                    
                    $qtySmall = 0;
                    if ($row->unit_id == $row->small_unit_id) {
                        $qtySmall = $qty;
                    } elseif ($row->unit_id == $row->medium_unit_id) {
                        $qtySmall = $qty * $smallConv;
                    } elseif ($row->unit_id == $row->large_unit_id) {
                        $qtySmall = $qty * $mediumConv * $smallConv;
                    }
                    
                    // Hitung subtotal MAC
                    $subtotalMac = $qtySmall * $mac;
                    $totalMac += $subtotalMac;
                }
                
                if ($totalMac > 0) {
                    $summaryData[] = [
                        'outlet_id' => $outlet->id_outlet,
                        'outlet_name' => $outlet->name,
                        'total_mac' => $totalMac,
                    ];
                }
            }
        }
        
        return Inertia::render('InternalUseWasteReport/Summary', [
            'summaryData' => $summaryData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function exportSummary(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            return redirect()->back()->with('error', 'Tanggal mulai dan tanggal akhir harus diisi untuk export.');
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "summary_report_rnd_bm_wm_{$timestamp}.xlsx";

        return Excel::download(
            new InternalUseWasteReportSummaryExport($startDate, $endDate),
            $filename
        );
    }
}

