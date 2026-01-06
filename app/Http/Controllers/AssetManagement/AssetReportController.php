<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransfer;
use App\Models\AssetMaintenance;
use App\Models\AssetDepreciation;
use App\Models\DataOutlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AssetReportController extends Controller
{
    /**
     * Asset Register Report
     */
    public function assetRegister(Request $request)
    {
        $search = $request->get('search', '');
        $categoryId = $request->get('category_id', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all');

        $query = Asset::with(['category', 'currentOutlet', 'creator']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($outletId !== '') {
            $query->where('current_outlet_id', $outletId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_code')->get();

        $categories = AssetCategory::where('is_active', 1)->orderBy('name')->get();
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/AssetRegister', [
            'assets' => $assets,
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'outlet_id' => $outletId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Asset by Outlet Report
     */
    public function assetByOutlet(Request $request)
    {
        $outletId = $request->get('outlet_id', '');
        $categoryId = $request->get('category_id', '');
        $status = $request->get('status', 'all');

        $query = Asset::with(['category', 'currentOutlet']);

        if ($outletId !== '') {
            $query->where('current_outlet_id', $outletId);
        }

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_code')->get();

        // Group by outlet
        $assetsByOutlet = $assets->groupBy('current_outlet_id')->map(function($group) {
            return [
                'outlet' => $group->first()->currentOutlet,
                'assets' => $group,
                'total_value' => $group->sum('purchase_price'),
                'count' => $group->count(),
            ];
        });

        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        $categories = AssetCategory::where('is_active', 1)->orderBy('name')->get();

        return Inertia::render('AssetManagement/Reports/AssetByOutlet', [
            'assetsByOutlet' => $assetsByOutlet,
            'outlets' => $outlets,
            'categories' => $categories,
            'filters' => [
                'outlet_id' => $outletId,
                'category_id' => $categoryId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Asset by Category Report
     */
    public function assetByCategory(Request $request)
    {
        $categoryId = $request->get('category_id', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all');

        $query = Asset::with(['category', 'currentOutlet']);

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($outletId !== '') {
            $query->where('current_outlet_id', $outletId);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_code')->get();

        // Group by category
        $assetsByCategory = $assets->groupBy('category_id')->map(function($group) {
            return [
                'category' => $group->first()->category,
                'assets' => $group,
                'total_value' => $group->sum('purchase_price'),
                'count' => $group->count(),
            ];
        });

        $categories = AssetCategory::where('is_active', 1)->orderBy('name')->get();
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/AssetByCategory', [
            'assetsByCategory' => $assetsByCategory,
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'category_id' => $categoryId,
                'outlet_id' => $outletId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Maintenance History Report
     */
    public function maintenanceHistory(Request $request)
    {
        $assetId = $request->get('asset_id', '');
        $outletId = $request->get('outlet_id', '');
        $maintenanceType = $request->get('maintenance_type', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = AssetMaintenance::with(['asset.category', 'asset.currentOutlet', 'performer']);

        if ($assetId !== '') {
            $query->where('asset_id', $assetId);
        }

        if ($outletId !== '') {
            $query->whereHas('asset', function($q) use ($outletId) {
                $q->where('current_outlet_id', $outletId);
            });
        }

        if ($maintenanceType !== '') {
            $query->where('maintenance_type', $maintenanceType);
        }

        if ($dateFrom) {
            $query->where('maintenance_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('maintenance_date', '<=', $dateTo);
        }

        $maintenances = $query->orderBy('maintenance_date', 'desc')->get();

        $totalCost = $maintenances->sum('cost');

        $assets = Asset::where('status', 'Active')->orderBy('asset_code')->get(['id', 'asset_code', 'name']);
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/MaintenanceHistory', [
            'maintenances' => $maintenances,
            'totalCost' => $totalCost,
            'assets' => $assets,
            'outlets' => $outlets,
            'filters' => [
                'asset_id' => $assetId,
                'outlet_id' => $outletId,
                'maintenance_type' => $maintenanceType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Transfer History Report
     */
    public function transferHistory(Request $request)
    {
        $assetId = $request->get('asset_id', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = AssetTransfer::with(['asset.category', 'fromOutlet', 'toOutlet', 'requester', 'approver']);

        if ($assetId !== '') {
            $query->where('asset_id', $assetId);
        }

        if ($outletId !== '') {
            $query->where(function($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)
                  ->orWhere('to_outlet_id', $outletId);
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('transfer_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transfer_date', '<=', $dateTo);
        }

        $transfers = $query->orderBy('transfer_date', 'desc')->get();

        $assets = Asset::orderBy('asset_code')->get(['id', 'asset_code', 'name']);
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/TransferHistory', [
            'transfers' => $transfers,
            'assets' => $assets,
            'outlets' => $outlets,
            'filters' => [
                'asset_id' => $assetId,
                'outlet_id' => $outletId,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Depreciation Report
     */
    public function depreciation(Request $request)
    {
        $categoryId = $request->get('category_id', '');
        $outletId = $request->get('outlet_id', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = AssetDepreciation::with(['asset.category', 'asset.currentOutlet']);

        if ($categoryId !== '') {
            $query->whereHas('asset', function($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($outletId !== '') {
            $query->whereHas('asset', function($q) use ($outletId) {
                $q->where('current_outlet_id', $outletId);
            });
        }

        if ($dateFrom) {
            $query->where('last_calculated_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('last_calculated_date', '<=', $dateTo);
        }

        $depreciations = $query->get();

        $totalDepreciation = $depreciations->sum('accumulated_depreciation');
        $totalCurrentValue = $depreciations->sum('current_value');

        $categories = AssetCategory::where('is_active', 1)->orderBy('name')->get();
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/Depreciation', [
            'depreciations' => $depreciations,
            'totalDepreciation' => $totalDepreciation,
            'totalCurrentValue' => $totalCurrentValue,
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'category_id' => $categoryId,
                'outlet_id' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Asset Value Report
     */
    public function assetValue(Request $request)
    {
        $categoryId = $request->get('category_id', '');
        $outletId = $request->get('outlet_id', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');

        $query = Asset::where('status', 'Active')->with(['category', 'currentOutlet']);

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($outletId !== '') {
            $query->where('current_outlet_id', $outletId);
        }

        if ($dateFrom) {
            $query->where('purchase_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('purchase_date', '<=', $dateTo);
        }

        $assets = $query->orderBy('asset_code')->get();

        $totalValue = $assets->sum('purchase_price');

        // Group by category
        $valueByCategory = $assets->groupBy('category_id')->map(function($group) {
            return [
                'category' => $group->first()->category,
                'total_value' => $group->sum('purchase_price'),
                'count' => $group->count(),
            ];
        });

        // Group by outlet
        $valueByOutlet = $assets->whereNotNull('current_outlet_id')
            ->groupBy('current_outlet_id')
            ->map(function($group) {
                return [
                    'outlet' => $group->first()->currentOutlet,
                    'total_value' => $group->sum('purchase_price'),
                    'count' => $group->count(),
                ];
            });

        $categories = AssetCategory::where('is_active', 1)->orderBy('name')->get();
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();

        return Inertia::render('AssetManagement/Reports/AssetValue', [
            'totalValue' => $totalValue,
            'valueByCategory' => $valueByCategory,
            'valueByOutlet' => $valueByOutlet,
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'category_id' => $categoryId,
                'outlet_id' => $outletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}

