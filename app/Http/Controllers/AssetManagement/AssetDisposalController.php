<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetDisposal;
use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AssetDisposalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $assetId = $request->get('asset_id', '');
        $disposalMethod = $request->get('disposal_method', '');
        $status = $request->get('status', 'all');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $perPage = $request->get('per_page', 15);

        $query = AssetDisposal::with(['asset.category', 'asset.currentOutlet', 'requester', 'approver']);

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

        // Disposal method filter
        if ($disposalMethod !== '') {
            $query->where('disposal_method', $disposalMethod);
        }

        // Status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Date range filter
        if ($dateFrom) {
            $query->where('disposal_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('disposal_date', '<=', $dateTo);
        }

        $disposals = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Get filter options
        $assets = Asset::orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);

        return Inertia::render('AssetManagement/Disposals/Index', [
            'disposals' => $disposals,
            'assets' => $assets,
            'filters' => [
                'search' => $search,
                'asset_id' => $assetId,
                'disposal_method' => $disposalMethod,
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

        return Inertia::render('AssetManagement/Disposals/Create', [
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
            'disposal_date' => 'required|date',
            'disposal_method' => 'required|in:Sold,Broken,Donated,Scrapped',
            'disposal_value' => 'nullable|numeric|min:0',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
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
            $asset = Asset::findOrFail($request->asset_id);
            
            // Check if asset is available for disposal
            if ($asset->status === 'Disposed') {
                throw new \Exception('Asset is already disposed');
            }

            $disposal = AssetDisposal::create([
                'asset_id' => $request->asset_id,
                'disposal_date' => $request->disposal_date,
                'disposal_method' => $request->disposal_method,
                'disposal_value' => $request->disposal_value,
                'reason' => $request->reason,
                'status' => 'Pending',
                'requested_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Disposal request created successfully',
                    'data' => $disposal->load(['asset.category', 'asset.currentOutlet', 'requester']),
                ], 201);
            }

            return redirect()->route('asset-management.disposals.index')
                ->with('success', 'Disposal request created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create disposal: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to create disposal: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $disposal = AssetDisposal::with([
            'asset.category',
            'asset.currentOutlet',
            'requester',
            'approver',
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/Disposals/Show', [
            'disposal' => $disposal,
        ]);
    }

    /**
     * Approve disposal
     */
    public function approve($id, Request $request)
    {
        $disposal = AssetDisposal::findOrFail($id);

        if ($disposal->status !== 'Pending') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Disposal is not pending',
                ], 422);
            }
            return redirect()->back()->with('error', 'Disposal is not pending');
        }

        DB::beginTransaction();
        try {
            $disposal->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Disposal approved successfully',
                    'data' => $disposal->fresh()->load(['asset', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Disposal approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve disposal: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to approve disposal: ' . $e->getMessage());
        }
    }

    /**
     * Reject disposal
     */
    public function reject($id, Request $request)
    {
        $disposal = AssetDisposal::findOrFail($id);

        if ($disposal->status !== 'Pending') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Disposal is not pending',
                ], 422);
            }
            return redirect()->back()->with('error', 'Disposal is not pending');
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator);
        }

        DB::beginTransaction();
        try {
            $disposal->update([
                'status' => 'Rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Disposal rejected successfully',
                    'data' => $disposal->fresh()->load(['asset', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Disposal rejected successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject disposal: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to reject disposal: ' . $e->getMessage());
        }
    }

    /**
     * Complete disposal
     */
    public function complete($id, Request $request)
    {
        $disposal = AssetDisposal::findOrFail($id);

        if ($disposal->status !== 'Approved') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Disposal must be approved first',
                ], 422);
            }
            return redirect()->back()->with('error', 'Disposal must be approved first');
        }

        DB::beginTransaction();
        try {
            $disposal->update([
                'status' => 'Completed',
                'completed_at' => now(),
            ]);

            // Update asset status to Disposed
            $asset = Asset::findOrFail($disposal->asset_id);
            $asset->update(['status' => 'Disposed']);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Disposal completed successfully',
                    'data' => $disposal->fresh()->load(['asset', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Disposal completed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete disposal: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to complete disposal: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $disposal = AssetDisposal::findOrFail($id);

        if ($disposal->status === 'Completed') {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed disposal',
                ], 422);
            }
            return redirect()->back()->with('error', 'Cannot delete completed disposal');
        }

        $disposal->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Disposal deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.disposals.index')
            ->with('success', 'Disposal deleted successfully');
    }
}

