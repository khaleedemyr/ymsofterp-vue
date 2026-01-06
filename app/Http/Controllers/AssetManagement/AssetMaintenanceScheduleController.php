<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetMaintenanceSchedule;
use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AssetMaintenanceScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $assetId = $request->get('asset_id', '');
        $maintenanceType = $request->get('maintenance_type', '');
        $frequency = $request->get('frequency', '');
        $isActive = $request->get('is_active', 'all');
        $perPage = $request->get('per_page', 15);

        $query = AssetMaintenanceSchedule::with(['asset.category', 'asset.currentOutlet']);

        // Search filter
        if ($search) {
            $query->whereHas('asset', function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Asset filter
        if ($assetId !== '') {
            $query->where('asset_id', $assetId);
        }

        // Maintenance type filter
        if ($maintenanceType !== '') {
            $query->where('maintenance_type', $maintenanceType);
        }

        // Frequency filter
        if ($frequency !== '') {
            $query->where('frequency', $frequency);
        }

        // Active filter
        if ($isActive === 'active') {
            $query->where('is_active', 1);
        } elseif ($isActive === 'inactive') {
            $query->where('is_active', 0);
        }

        $schedules = $query->orderBy('next_maintenance_date')->paginate($perPage)->withQueryString();

        // Get filter options
        $assets = Asset::where('status', 'Active')
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        return Inertia::render('AssetManagement/MaintenanceSchedules/Index', [
            'schedules' => $schedules,
            'assets' => $assets,
            'filters' => [
                'search' => $search,
                'asset_id' => $assetId,
                'maintenance_type' => $maintenanceType,
                'frequency' => $frequency,
                'is_active' => $isActive,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $assets = Asset::where('status', 'Active')
            ->with(['category', 'currentOutlet'])
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name', 'category_id', 'current_outlet_id']);

        return Inertia::render('AssetManagement/MaintenanceSchedules/Create', [
            'assets' => $assets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'maintenance_type' => 'required|in:Cleaning,Service,Repair,Inspection',
            'frequency' => 'required|in:Daily,Weekly,Monthly,Quarterly,Yearly',
            'next_maintenance_date' => 'required|date',
            'last_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $schedule = AssetMaintenanceSchedule::create([
            'asset_id' => $request->asset_id,
            'maintenance_type' => $request->maintenance_type,
            'frequency' => $request->frequency,
            'next_maintenance_date' => $request->next_maintenance_date,
            'last_maintenance_date' => $request->last_maintenance_date,
            'notes' => $request->notes,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule created successfully',
                'data' => $schedule->load(['asset.category', 'asset.currentOutlet']),
            ], 201);
        }

        return redirect()->route('asset-management.maintenance-schedules.index')
            ->with('success', 'Maintenance schedule created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $schedule = AssetMaintenanceSchedule::with([
            'asset.category',
            'asset.currentOutlet',
            'maintenances' => function($q) {
                $q->latest()->limit(10);
            },
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/MaintenanceSchedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $schedule = AssetMaintenanceSchedule::findOrFail($id);
        
        $assets = Asset::where('status', 'Active')
            ->with(['category', 'currentOutlet'])
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name', 'category_id', 'current_outlet_id']);
        
        return Inertia::render('AssetManagement/MaintenanceSchedules/Edit', [
            'schedule' => $schedule,
            'assets' => $assets,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schedule = AssetMaintenanceSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'maintenance_type' => 'required|in:Cleaning,Service,Repair,Inspection',
            'frequency' => 'required|in:Daily,Weekly,Monthly,Quarterly,Yearly',
            'next_maintenance_date' => 'required|date',
            'last_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $schedule->update([
            'asset_id' => $request->asset_id,
            'maintenance_type' => $request->maintenance_type,
            'frequency' => $request->frequency,
            'next_maintenance_date' => $request->next_maintenance_date,
            'last_maintenance_date' => $request->last_maintenance_date,
            'notes' => $request->notes,
            'is_active' => $request->has('is_active') ? $request->is_active : $schedule->is_active,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule updated successfully',
                'data' => $schedule->fresh()->load(['asset.category', 'asset.currentOutlet']),
            ], 200);
        }

        return redirect()->route('asset-management.maintenance-schedules.index')
            ->with('success', 'Maintenance schedule updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $schedule = AssetMaintenanceSchedule::findOrFail($id);
        $schedule->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.maintenance-schedules.index')
            ->with('success', 'Maintenance schedule deleted successfully');
    }

    /**
     * Toggle status of the resource.
     */
    public function toggleStatus($id)
    {
        $schedule = AssetMaintenanceSchedule::findOrFail($id);
        $schedule->is_active = $schedule->is_active ? 0 : 1;
        $schedule->save();
        $schedule->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => [
                'is_active' => (bool) $schedule->is_active,
            ],
        ], 200);
    }
}

