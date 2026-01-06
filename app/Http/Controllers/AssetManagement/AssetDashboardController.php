<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransfer;
use App\Models\AssetMaintenance;
use App\Models\AssetMaintenanceSchedule;
use App\Models\DataOutlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetDashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(Request $request)
    {
        // Get date range filters
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        // Total assets by status
        $assetsByStatus = Asset::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Total assets by category
        $assetsByCategory = Asset::join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->select('asset_categories.name as category', DB::raw('count(*) as count'))
            ->groupBy('asset_categories.name')
            ->get();

        // Total assets by outlet
        $assetsByOutlet = Asset::join('tbl_data_outlet', 'assets.current_outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->select('tbl_data_outlet.nama_outlet as outlet', DB::raw('count(*) as count'))
            ->whereNotNull('assets.current_outlet_id')
            ->groupBy('tbl_data_outlet.nama_outlet')
            ->get();

        // Total asset value
        $totalAssetValue = Asset::where('status', 'Active')
            ->sum('purchase_price');

        // Maintenance due (next 7 days)
        $maintenanceDue = AssetMaintenanceSchedule::where('is_active', 1)
            ->whereBetween('next_maintenance_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->with(['asset.category', 'asset.currentOutlet'])
            ->count();

        // Overdue maintenance
        $overdueMaintenance = AssetMaintenanceSchedule::where('is_active', 1)
            ->where('next_maintenance_date', '<', now()->toDateString())
            ->with(['asset.category', 'asset.currentOutlet'])
            ->count();

        // Recent transfers (last 10)
        $recentTransfers = AssetTransfer::with(['asset.category', 'fromOutlet', 'toOutlet', 'requester'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent maintenances (last 10)
        $recentMaintenances = AssetMaintenance::with(['asset.category', 'asset.currentOutlet', 'performer'])
            ->orderBy('maintenance_date', 'desc')
            ->limit(10)
            ->get();

        // Statistics
        $statistics = [
            'total_assets' => Asset::count(),
            'active_assets' => Asset::where('status', 'Active')->count(),
            'maintenance_assets' => Asset::where('status', 'Maintenance')->count(),
            'disposed_assets' => Asset::where('status', 'Disposed')->count(),
            'total_value' => $totalAssetValue,
            'maintenance_due' => $maintenanceDue,
            'overdue_maintenance' => $overdueMaintenance,
            'pending_transfers' => AssetTransfer::where('status', 'Pending')->count(),
            'pending_disposals' => DB::table('asset_disposals')->where('status', 'Pending')->count(),
        ];

        return Inertia::render('AssetManagement/Dashboard/Index', [
            'statistics' => $statistics,
            'assetsByStatus' => $assetsByStatus,
            'assetsByCategory' => $assetsByCategory,
            'assetsByOutlet' => $assetsByOutlet,
            'maintenanceDue' => $maintenanceDue,
            'overdueMaintenance' => $overdueMaintenance,
            'recentTransfers' => $recentTransfers,
            'recentMaintenances' => $recentMaintenances,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}

