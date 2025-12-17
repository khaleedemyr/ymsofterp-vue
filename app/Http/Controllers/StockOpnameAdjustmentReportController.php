<?php

namespace App\Http\Controllers;

use App\Models\StockOpnameAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class StockOpnameAdjustmentReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get filter parameters
        $outletId = $request->input('outlet_id');
        $warehouseOutletId = $request->input('warehouse_outlet_id');
        $dateFrom = $request->input('date_from', date('Y-m-01')); // Default: awal bulan ini
        $dateTo = $request->input('date_to', date('Y-m-t')); // Default: akhir bulan ini
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 50);
        $page = $request->input('page', 1);

        // Get outlets
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        // Filter by outlet if user is not superadmin
        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
            $outletId = $user->id_outlet; // Force to user's outlet
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

        // Query adjustments
        $query = StockOpnameAdjustment::with([
            'stockOpname',
            'stockOpnameItem',
            'inventoryItem.item.category',
            'inventoryItem.item.smallUnit',
            'inventoryItem.item.mediumUnit',
            'inventoryItem.item.largeUnit',
            'outlet',
            'warehouseOutlet',
            'processedBy'
        ]);

        // Filter by outlet
        if ($outletId) {
            // Validate: if user is not superadmin, can only filter their own outlet
            if ($user->id_outlet != 1 && $outletId != $user->id_outlet) {
                $outletId = $user->id_outlet; // Force to user's outlet
            }
            $query->where('outlet_id', $outletId);
        }

        // Filter by warehouse outlet
        if ($warehouseOutletId) {
            $query->where('warehouse_outlet_id', $warehouseOutletId);
        }

        // Filter by date range
        if ($dateFrom) {
            $query->whereDate('processed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('processed_at', '<=', $dateTo);
        }

        // Search filter
        if ($search) {
            $query->whereHas('inventoryItem.item', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            })->orWhereHas('stockOpname', function($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%");
            })->orWhere('reason', 'like', "%{$search}%");
        }

        // Calculate totals (before pagination)
        $totalsQuery = clone $query;
        $totals = $totalsQuery->select(
            DB::raw('SUM(qty_diff_small) as total_qty_diff_small'),
            DB::raw('SUM(qty_diff_medium) as total_qty_diff_medium'),
            DB::raw('SUM(qty_diff_large) as total_qty_diff_large'),
            DB::raw('SUM(value_adjustment) as total_value_adjustment')
        )->first();

        // Get total count for pagination
        $totalItems = $query->count();

        // Pagination
        $adjustments = $query->orderBy('processed_at', 'desc')
            ->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Format data for frontend
        $reportData = $adjustments->map(function($adjustment) {
            $item = $adjustment->inventoryItem->item ?? null;
            $category = $item->category ?? null;
            
            return [
                'id' => $adjustment->id,
                'opname_number' => $adjustment->stockOpname->opname_number ?? '-',
                'opname_date' => $adjustment->stockOpname->opname_date ?? null,
                'processed_at' => $adjustment->processed_at ? Carbon::parse($adjustment->processed_at)->format('Y-m-d H:i:s') : null,
                'processed_by' => $adjustment->processedBy->nama_lengkap ?? '-',
                'outlet' => $adjustment->outlet->nama_outlet ?? '-',
                'warehouse_outlet' => $adjustment->warehouseOutlet->name ?? '-',
                'item_code' => $item->sku ?? '-',
                'item_name' => $item->name ?? '-',
                'category_name' => $category->name ?? '-',
                'qty_diff_small' => (float)$adjustment->qty_diff_small,
                'qty_diff_medium' => (float)$adjustment->qty_diff_medium,
                'qty_diff_large' => (float)$adjustment->qty_diff_large,
                'reason' => $adjustment->reason,
                'mac_before' => (float)$adjustment->mac_before,
                'mac_after' => (float)$adjustment->mac_after,
                'value_adjustment' => (float)$adjustment->value_adjustment,
                'small_unit_name' => $item && $item->smallUnit ? $item->smallUnit->name : '-',
                'medium_unit_name' => $item && $item->mediumUnit ? $item->mediumUnit->name : '-',
                'large_unit_name' => $item && $item->largeUnit ? $item->largeUnit->name : '-',
            ];
        });

        // Calculate pagination
        $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 0;

        return Inertia::render('StockOpnameAdjustmentReport/Index', [
            'adjustments' => $reportData,
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
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'per_page' => $perPage,
                'page' => $page,
            ],
            'user_outlet_id' => $user->id_outlet ?? null,
        ]);
    }
}

