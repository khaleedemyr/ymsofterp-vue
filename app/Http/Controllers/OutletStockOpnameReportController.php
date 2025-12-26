<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class OutletStockOpnameReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get filter parameters
        $outletId = $request->input('outlet_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $date = $request->input('date'); // Single date instead of date range
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);
        $loadData = $request->input('load', false); // Only load data if explicitly requested
        
        // If user is not superadmin, force outlet_id to their outlet
        if ($user->id_outlet != 1) {
            $outletId = $user->id_outlet;
        }

        // Get outlets
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        // Filter by outlet if user is not superadmin
        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
        }

        $outlets = $outletsQuery->get();

        // Get warehouse outlets
        $warehouseOutlets = [];
        if ($outletId) {
            $warehouseOutletsQuery = DB::table('warehouse_outlets')
                ->where('outlet_id', $outletId)
                ->where('status', 'active')
                ->select('id', 'name')
                ->orderBy('name');

            $warehouseOutlets = $warehouseOutletsQuery->get();
        }

        // Query stock opnames - ALL STATUSES
        $query = StockOpname::with([
            'outlet',
            'warehouseOutlet',
            'creator',
            'items.inventoryItem.item',
            'items.inventoryItem.item.category',
            'items.inventoryItem.item.smallUnit',
            'items.inventoryItem.item.mediumUnit',
            'items.inventoryItem.item.largeUnit',
            'approvalFlows.approver'
        ]);

        // Filter by outlet
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Filter by warehouse outlet
        if ($warehouseOutletId) {
            $query->where('warehouse_outlet_id', $warehouseOutletId);
        }

        // Filter by single date (opname_date)
        if ($date) {
            $query->whereDate('opname_date', $date);
        }

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('items.inventoryItem.item', function($itemQuery) use ($search) {
                      $itemQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        // Only load data if explicitly requested (load=true)
        $stockOpnames = collect([]);
        $totalItems = 0;
        
        if ($loadData) {
            // Get total count for pagination
            $totalItems = $query->count();

            // Pagination
            $stockOpnames = $query->orderBy('opname_date', 'desc')
                ->orderBy('id', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
        }

        // Format data for frontend
        $reportData = $stockOpnames->map(function($stockOpname) {
            $items = $stockOpname->items->map(function($item) {
                $inventoryItem = $item->inventoryItem ?? null;
                $itemData = $inventoryItem->item ?? null;
                $category = $itemData->category ?? null;
                
                return [
                    'id' => $item->id,
                    'item_code' => $itemData->sku ?? '-',
                    'item_name' => $itemData->name ?? '-',
                    'category_name' => $category->name ?? '-',
                    'qty_system_small' => (float)$item->qty_system_small,
                    'qty_system_medium' => (float)$item->qty_system_medium,
                    'qty_system_large' => (float)$item->qty_system_large,
                    'qty_physical_small' => (float)$item->qty_physical_small,
                    'qty_physical_medium' => (float)$item->qty_physical_medium,
                    'qty_physical_large' => (float)$item->qty_physical_large,
                    'qty_diff_small' => (float)$item->qty_diff_small,
                    'qty_diff_medium' => (float)$item->qty_diff_medium,
                    'qty_diff_large' => (float)$item->qty_diff_large,
                    'reason' => $item->reason,
                    'mac_before' => (float)$item->mac_before,
                    'mac_after' => (float)$item->mac_after,
                    'value_adjustment' => (float)$item->value_adjustment,
                    'small_unit_name' => $itemData && $itemData->smallUnit ? $itemData->smallUnit->name : '-',
                    'medium_unit_name' => $itemData && $itemData->mediumUnit ? $itemData->mediumUnit->name : '-',
                    'large_unit_name' => $itemData && $itemData->largeUnit ? $itemData->largeUnit->name : '-',
                ];
            });

            // Get approvers info
            $approvers = $stockOpname->approvalFlows->map(function($flow) {
                return [
                    'level' => $flow->approval_level,
                    'name' => $flow->approver->nama_lengkap ?? '-',
                    'status' => $flow->status,
                    'approved_at' => $flow->approved_at ? Carbon::parse($flow->approved_at)->format('Y-m-d H:i:s') : null,
                ];
            })->sortBy('level')->values();

            return [
                'id' => $stockOpname->id,
                'opname_number' => $stockOpname->opname_number,
                'opname_date' => $stockOpname->opname_date ? Carbon::parse($stockOpname->opname_date)->format('Y-m-d') : null,
                'outlet' => $stockOpname->outlet->nama_outlet ?? '-',
                'warehouse_outlet' => $stockOpname->warehouseOutlet->name ?? '-',
                'status' => $stockOpname->status,
                'notes' => $stockOpname->notes,
                'created_by' => $stockOpname->creator->nama_lengkap ?? '-',
                'created_at' => $stockOpname->created_at ? Carbon::parse($stockOpname->created_at)->format('Y-m-d H:i:s') : null,
                'items' => $items,
                'items_count' => $items->count(),
                'approvers' => $approvers,
                'total_value_adjustment' => $items->sum('value_adjustment'),
            ];
        });

        // Calculate totals across all stock opnames (only if loadData is true)
        $totals = (object)[
            'total_qty_diff_small' => 0,
            'total_qty_diff_medium' => 0,
            'total_qty_diff_large' => 0,
            'total_value_adjustment' => 0
        ];
        
        if ($loadData) {
            $totalsQuery = StockOpname::query();
            
            if ($outletId) {
                $totalsQuery->where('outlet_id', $outletId);
            }
            
            if ($warehouseOutletId) {
                $totalsQuery->where('warehouse_outlet_id', $warehouseOutletId);
            }
            
            if ($date) {
                $totalsQuery->whereDate('opname_date', $date);
            }

            $totals = DB::table('outlet_stock_opname_items as items')
                ->join('outlet_stock_opnames as opnames', 'items.stock_opname_id', '=', 'opnames.id')
                ->when($outletId, function($q) use ($outletId) {
                    $q->where('opnames.outlet_id', $outletId);
                })
                ->when($warehouseOutletId, function($q) use ($warehouseOutletId) {
                    $q->where('opnames.warehouse_outlet_id', $warehouseOutletId);
                })
                ->when($date, function($q) use ($date) {
                    $q->whereDate('opnames.opname_date', $date);
                })
                ->select(
                    DB::raw('SUM(items.qty_diff_small) as total_qty_diff_small'),
                    DB::raw('SUM(items.qty_diff_medium) as total_qty_diff_medium'),
                    DB::raw('SUM(items.qty_diff_large) as total_qty_diff_large'),
                    DB::raw('SUM(items.value_adjustment) as total_value_adjustment')
                )
                ->first() ?? $totals;
        }

        // Calculate pagination
        $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 0;

        return Inertia::render('OutletStockOpnameReport/Index', [
            'stockOpnames' => $reportData,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'totals' => [
                'total_qty_diff_small' => (float)($totals->total_qty_diff_small ?? 0),
                'total_qty_diff_medium' => (float)($totals->total_qty_diff_medium ?? 0),
                'total_qty_diff_large' => (float)($totals->total_qty_diff_large ?? 0),
                'total_value_adjustment' => (float)($totals->total_value_adjustment ?? 0),
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'date' => $date,
                'search' => $search,
                'per_page' => $perPage,
                'page' => $page,
            ],
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }
}

