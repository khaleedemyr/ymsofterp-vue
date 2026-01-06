<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetMaintenance;
use App\Models\Asset;
use App\Models\AssetMaintenanceSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetMaintenanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $assetId = $request->get('asset_id', '');
        $maintenanceType = $request->get('maintenance_type', '');
        $status = $request->get('status', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        $query = AssetMaintenance::with(['asset.category', 'asset.currentOutlet', 'schedule', 'performer']);

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

        // Status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Date range filter
        if ($dateFrom) {
            $query->where('maintenance_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('maintenance_date', '<=', $dateTo);
        }

        $maintenances = $query->orderBy('maintenance_date', 'desc')->paginate($perPage)->withQueryString();

        // Get filter options
        $assets = Asset::where('status', 'Active')
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        return Inertia::render('AssetManagement/Maintenances/Index', [
            'maintenances' => $maintenances,
            'assets' => $assets,
            'filters' => [
                'search' => $search,
                'asset_id' => $assetId,
                'maintenance_type' => $maintenanceType,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
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

        $schedules = AssetMaintenanceSchedule::where('is_active', 1)
            ->with('asset')
            ->orderBy('next_maintenance_date')
            ->get(['id', 'asset_id', 'maintenance_type', 'next_maintenance_date']);

        return Inertia::render('AssetManagement/Maintenances/Create', [
            'assets' => $assets,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'maintenance_schedule_id' => 'nullable|exists:asset_maintenance_schedules,id',
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|in:Cleaning,Service,Repair,Inspection',
            'cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:Scheduled,In Progress,Completed,Cancelled',
            'performed_by' => 'nullable|exists:users,id',
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

        DB::beginTransaction();
        try {
            $maintenance = AssetMaintenance::create([
                'asset_id' => $request->asset_id,
                'maintenance_schedule_id' => $request->maintenance_schedule_id,
                'maintenance_date' => $request->maintenance_date,
                'maintenance_type' => $request->maintenance_type,
                'cost' => $request->cost,
                'vendor' => $request->vendor,
                'notes' => $request->notes,
                'status' => $request->status,
                'performed_by' => $request->performed_by,
            ]);

            // If maintenance is from a schedule and completed, update schedule
            if ($request->maintenance_schedule_id && $request->status === 'Completed') {
                $schedule = AssetMaintenanceSchedule::findOrFail($request->maintenance_schedule_id);
                $schedule->update([
                    'last_maintenance_date' => $request->maintenance_date,
                    'next_maintenance_date' => $this->calculateNextMaintenanceDate(
                        $request->maintenance_date,
                        $schedule->frequency
                    ),
                ]);
            }

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Maintenance created successfully',
                    'data' => $maintenance->load(['asset.category', 'asset.currentOutlet', 'schedule', 'performer']),
                ], 201);
            }

            return redirect()->route('asset-management.maintenances.index')
                ->with('success', 'Maintenance created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create maintenance: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to create maintenance: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $maintenance = AssetMaintenance::with([
            'asset.category',
            'asset.currentOutlet',
            'schedule',
            'performer',
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/Maintenances/Show', [
            'maintenance' => $maintenance,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $maintenance = AssetMaintenance::findOrFail($id);
        
        $assets = Asset::where('status', 'Active')
            ->with(['category', 'currentOutlet'])
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name', 'category_id', 'current_outlet_id']);

        $schedules = AssetMaintenanceSchedule::where('is_active', 1)
            ->with('asset')
            ->orderBy('next_maintenance_date')
            ->get(['id', 'asset_id', 'maintenance_type', 'next_maintenance_date']);
        
        return Inertia::render('AssetManagement/Maintenances/Edit', [
            'maintenance' => $maintenance,
            'assets' => $assets,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $maintenance = AssetMaintenance::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'maintenance_schedule_id' => 'nullable|exists:asset_maintenance_schedules,id',
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|in:Cleaning,Service,Repair,Inspection',
            'cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:Scheduled,In Progress,Completed,Cancelled',
            'performed_by' => 'nullable|exists:users,id',
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

        DB::beginTransaction();
        try {
            $oldStatus = $maintenance->status;
            
            $maintenance->update([
                'asset_id' => $request->asset_id,
                'maintenance_schedule_id' => $request->maintenance_schedule_id,
                'maintenance_date' => $request->maintenance_date,
                'maintenance_type' => $request->maintenance_type,
                'cost' => $request->cost,
                'vendor' => $request->vendor,
                'notes' => $request->notes,
                'status' => $request->status,
                'performed_by' => $request->performed_by,
            ]);

            // If status changed to Completed and has schedule, update schedule
            if ($oldStatus !== 'Completed' && $request->status === 'Completed' && $request->maintenance_schedule_id) {
                $schedule = AssetMaintenanceSchedule::findOrFail($request->maintenance_schedule_id);
                $schedule->update([
                    'last_maintenance_date' => $request->maintenance_date,
                    'next_maintenance_date' => $this->calculateNextMaintenanceDate(
                        $request->maintenance_date,
                        $schedule->frequency
                    ),
                ]);
            }

            if ($request->status === 'Completed' && !$maintenance->completed_at) {
                $maintenance->update(['completed_at' => now()]);
            }

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Maintenance updated successfully',
                    'data' => $maintenance->fresh()->load(['asset.category', 'asset.currentOutlet', 'schedule', 'performer']),
                ], 200);
            }

            return redirect()->route('asset-management.maintenances.index')
                ->with('success', 'Maintenance updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update maintenance: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to update maintenance: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Complete maintenance
     */
    public function complete($id, Request $request)
    {
        $maintenance = AssetMaintenance::findOrFail($id);

        if ($maintenance->status === 'Completed') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maintenance is already completed',
                ], 422);
            }
            return redirect()->back()->with('error', 'Maintenance is already completed');
        }

        DB::beginTransaction();
        try {
            $maintenance->update([
                'status' => 'Completed',
                'completed_at' => now(),
            ]);

            // Update schedule if exists
            if ($maintenance->maintenance_schedule_id) {
                $schedule = AssetMaintenanceSchedule::findOrFail($maintenance->maintenance_schedule_id);
                $schedule->update([
                    'last_maintenance_date' => $maintenance->maintenance_date,
                    'next_maintenance_date' => $this->calculateNextMaintenanceDate(
                        $maintenance->maintenance_date,
                        $schedule->frequency
                    ),
                ]);
            }

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Maintenance completed successfully',
                    'data' => $maintenance->fresh()->load(['asset.category', 'asset.currentOutlet', 'schedule', 'performer']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Maintenance completed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete maintenance: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to complete maintenance: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $maintenance = AssetMaintenance::findOrFail($id);
        $maintenance->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Maintenance deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.maintenances.index')
            ->with('success', 'Maintenance deleted successfully');
    }

    /**
     * Calculate next maintenance date based on frequency
     */
    private function calculateNextMaintenanceDate($lastDate, $frequency)
    {
        $date = Carbon::parse($lastDate);
        
        switch ($frequency) {
            case 'Daily':
                return $date->addDay()->toDateString();
            case 'Weekly':
                return $date->addWeek()->toDateString();
            case 'Monthly':
                return $date->addMonth()->toDateString();
            case 'Quarterly':
                return $date->addMonths(3)->toDateString();
            case 'Yearly':
                return $date->addYear()->toDateString();
            default:
                return $date->toDateString();
        }
    }
}

