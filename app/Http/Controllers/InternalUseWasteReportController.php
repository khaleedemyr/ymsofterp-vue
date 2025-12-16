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
                // Ambil MAC dari cost history pada tanggal transaksi
                $mac = 0;
                if ($row->inventory_item_id) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $row->inventory_item_id)
                        ->where('id_outlet', $outletId)
                        ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                        ->where('date', '<=', $row->date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->select('mac')
                        ->first();
                    
                    if ($macRow) {
                        $mac = (float) ($macRow->mac ?? 0);
                    } else {
                        // Fallback: ambil dari stock current
                        $stockRow = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $row->inventory_item_id)
                            ->where('id_outlet', $outletId)
                            ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                            ->select('last_cost_small')
                            ->first();
                        
                        if ($stockRow) {
                            $mac = (float) ($stockRow->last_cost_small ?? 0);
                        }
                    }
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
            
            foreach ($allItems as $row) {
                // Ambil MAC dari cost history pada tanggal transaksi (sama dengan logika di report data)
                $mac = 0;
                if ($row->inventory_item_id) {
                    $macRow = DB::table('outlet_food_inventory_cost_histories')
                        ->where('inventory_item_id', $row->inventory_item_id)
                        ->where('id_outlet', $outletId)
                        ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                        ->where('date', '<=', $row->date)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->select('mac')
                        ->first();
                    
                    if ($macRow) {
                        $mac = (float) ($macRow->mac ?? 0);
                    } else {
                        $stockRow = DB::table('outlet_food_inventory_stocks')
                            ->where('inventory_item_id', $row->inventory_item_id)
                            ->where('id_outlet', $outletId)
                            ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                            ->select('last_cost_small')
                            ->first();
                        
                        if ($stockRow) {
                            $mac = (float) ($stockRow->last_cost_small ?? 0);
                        }
                    }
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
            foreach ($outlets as $outlet) {
                // Query untuk mengambil data internal use waste dengan type RnD, Marketing, Wrong Maker per outlet
                $query = DB::table('outlet_internal_use_waste_headers as h')
                    ->join('outlet_internal_use_waste_details as d', 'h.id', '=', 'd.header_id')
                    ->join('items as item', 'd.item_id', '=', 'item.id')
                    ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                    ->where('h.outlet_id', $outlet->id_outlet)
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
                
                $items = $query->get();
                $totalMac = 0;
                
                foreach ($items as $row) {
                    // Ambil MAC dari cost history pada tanggal transaksi
                    $mac = 0;
                    if ($row->inventory_item_id) {
                        $macRow = DB::table('outlet_food_inventory_cost_histories')
                            ->where('inventory_item_id', $row->inventory_item_id)
                            ->where('id_outlet', $outlet->id_outlet)
                            ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                            ->where('date', '<=', $row->date)
                            ->orderByDesc('date')
                            ->orderByDesc('id')
                            ->select('mac')
                            ->first();
                        
                        if ($macRow) {
                            $mac = (float) ($macRow->mac ?? 0);
                        } else {
                            $stockRow = DB::table('outlet_food_inventory_stocks')
                                ->where('inventory_item_id', $row->inventory_item_id)
                                ->where('id_outlet', $outlet->id_outlet)
                                ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                                ->select('last_cost_small')
                                ->first();
                            
                            if ($stockRow) {
                                $mac = (float) ($stockRow->last_cost_small ?? 0);
                            }
                        }
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

