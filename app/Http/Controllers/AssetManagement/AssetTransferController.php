<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetTransfer;
use App\Models\Asset;
use App\Models\DataOutlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AssetTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $assetId = $request->get('asset_id', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all');
        $perPage = $request->get('per_page', 15);

        $query = AssetTransfer::with(['asset', 'fromOutlet', 'toOutlet', 'requester', 'approver']);

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

        // Outlet filter
        if ($outletId !== '') {
            $query->where(function($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)
                  ->orWhere('to_outlet_id', $outletId);
            });
        }

        // Status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Get filter options
        $assets = Asset::where('status', 'Active')
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name']);
        
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('AssetManagement/Transfers/Index', [
            'transfers' => $transfers,
            'assets' => $assets,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'asset_id' => $assetId,
                'outlet_id' => $outletId,
                'status' => $status,
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
        
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('AssetManagement/Transfers/Create', [
            'assets' => $assets,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'from_outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'to_outlet_id' => 'required|exists:tbl_data_outlet,id_outlet|different:from_outlet_id',
            'transfer_date' => 'required|date',
            'reason' => 'nullable|string',
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
            
            // Check if asset is available for transfer
            if ($asset->status !== 'Active') {
                throw new \Exception('Asset is not available for transfer');
            }

            // Check if from_outlet matches asset's current outlet
            if ($asset->current_outlet_id != $request->from_outlet_id) {
                throw new \Exception('Asset is not currently at the selected outlet');
            }

            $transfer = AssetTransfer::create([
                'asset_id' => $request->asset_id,
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'transfer_date' => $request->transfer_date,
                'reason' => $request->reason,
                'status' => 'Pending',
                'requested_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Update asset status to Transfer
            $asset->update(['status' => 'Transfer']);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer request created successfully',
                    'data' => $transfer->load(['asset', 'fromOutlet', 'toOutlet', 'requester']),
                ], 201);
            }

            return redirect()->route('asset-management.transfers.index')
                ->with('success', 'Transfer request created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create transfer: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to create transfer: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $transfer = AssetTransfer::with([
            'asset.category',
            'asset.currentOutlet',
            'fromOutlet',
            'toOutlet',
            'requester',
            'approver',
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/Transfers/Show', [
            'transfer' => $transfer,
        ]);
    }

    /**
     * Approve transfer
     */
    public function approve($id, Request $request)
    {
        $transfer = AssetTransfer::findOrFail($id);

        if ($transfer->status !== 'Pending') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer is not pending',
                ], 422);
            }
            return redirect()->back()->with('error', 'Transfer is not pending');
        }

        DB::beginTransaction();
        try {
            $transfer->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer approved successfully',
                    'data' => $transfer->fresh()->load(['asset', 'fromOutlet', 'toOutlet', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Transfer approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve transfer: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to approve transfer: ' . $e->getMessage());
        }
    }

    /**
     * Reject transfer
     */
    public function reject($id, Request $request)
    {
        $transfer = AssetTransfer::findOrFail($id);

        if ($transfer->status !== 'Pending') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer is not pending',
                ], 422);
            }
            return redirect()->back()->with('error', 'Transfer is not pending');
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
            $transfer->update([
                'status' => 'Rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Revert asset status to Active
            $asset = Asset::findOrFail($transfer->asset_id);
            $asset->update(['status' => 'Active']);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer rejected successfully',
                    'data' => $transfer->fresh()->load(['asset', 'fromOutlet', 'toOutlet', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Transfer rejected successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject transfer: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to reject transfer: ' . $e->getMessage());
        }
    }

    /**
     * Complete transfer
     */
    public function complete($id, Request $request)
    {
        $transfer = AssetTransfer::findOrFail($id);

        if ($transfer->status !== 'Approved') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer must be approved first',
                ], 422);
            }
            return redirect()->back()->with('error', 'Transfer must be approved first');
        }

        DB::beginTransaction();
        try {
            $transfer->update([
                'status' => 'Completed',
                'completed_at' => now(),
            ]);

            // Update asset location and status
            $asset = Asset::findOrFail($transfer->asset_id);
            $asset->update([
                'current_outlet_id' => $transfer->to_outlet_id,
                'status' => 'Active',
            ]);

            DB::commit();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer completed successfully',
                    'data' => $transfer->fresh()->load(['asset', 'fromOutlet', 'toOutlet', 'requester', 'approver']),
                ], 200);
            }

            return redirect()->back()->with('success', 'Transfer completed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to complete transfer: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to complete transfer: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $transfer = AssetTransfer::findOrFail($id);

        if ($transfer->status === 'Completed') {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed transfer',
                ], 422);
            }
            return redirect()->back()->with('error', 'Cannot delete completed transfer');
        }

        DB::beginTransaction();
        try {
            // Revert asset status if transfer is pending or approved
            if (in_array($transfer->status, ['Pending', 'Approved'])) {
                $asset = Asset::findOrFail($transfer->asset_id);
                $asset->update(['status' => 'Active']);
            }

            $transfer->delete();

            DB::commit();

            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer deleted successfully',
                ], 200);
            }

            return redirect()->route('asset-management.transfers.index')
                ->with('success', 'Transfer deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete transfer: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to delete transfer: ' . $e->getMessage());
        }
    }
}

