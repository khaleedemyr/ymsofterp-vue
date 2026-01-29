<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\FoodInventoryAdjustment;
use App\Models\FoodInventoryAdjustmentItem;
use App\Models\FoodInventoryStock;
use App\Models\FoodInventoryItem;
use App\Models\FoodInventoryCard;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\User;
use App\Services\NotificationService;

class FoodInventoryAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = FoodInventoryAdjustment::with(['items', 'warehouse', 'creator']);
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('items', function($q) use ($search) {
                $q->where('item_id', $search);
            });
        }
        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $adjustments = $query->orderByDesc('date')->paginate(10)->withQueryString();
        return inertia('FoodInventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'warehouse_id', 'from', 'to']),
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        return inertia('FoodInventoryAdjustment/Form', [
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('FoodInventoryAdjustment store method called', $request->all());
        
        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:in,out',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.selected_unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|exists:users,id'
        ]);
        
        DB::beginTransaction();
        try {
            $userId = auth()->id();
            if (!$userId) {
                throw new \Exception('User tidak terautentikasi. Silakan login ulang.');
            }
            
            $number = $this->generateAdjustmentNumber();
            $headerId = DB::table('food_inventory_adjustments')->insertGetId([
                'number' => $number,
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => 'waiting_approval',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Insert items
            foreach ($request->items as $item) {
                DB::table('food_inventory_adjustment_items')->insert([
                    'adjustment_id' => $headerId,
                    'item_id' => $item['item_id'],
                    'qty' => $item['qty'],
                    'unit' => $item['selected_unit'],
                    'note' => $item['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Create approval flows
            foreach ($request->approvers as $index => $approverId) {
                DB::table('food_inventory_adjustment_approval_flows')->insert([
                    'adjustment_id' => $headerId,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Activity log
            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'activity_type' => 'create',
                'module' => 'stock_adjustment',
                'description' => 'Membuat stock adjustment baru: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($request->all()),
                'created_at' => now(),
            ]);
            
            // Send notification to first approver
            $this->sendNotificationToNextApprover($headerId);
            
            DB::commit();
            
            \Log::info('FoodInventoryAdjustment created successfully', [
                'id' => $headerId,
                'number' => $number
            ]);
            
            return redirect()->route('food-inventory-adjustment.show', $headerId)
                ->with('success', 'Stock adjustment berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodInventoryAdjustment store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $adj = DB::table('food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) {
                throw new \Exception('Adjustment not found');
            }
            
            // Check if adjustment has approval flows
            $hasApprovalFlows = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->exists();
            
            if (!$hasApprovalFlows) {
                throw new \Exception('Approval flow not found. Adjustment ini mungkin dibuat sebelum sistem approval flow diterapkan.');
            }
            
            // Find current approval flow for this user
            $currentApprovalFlow = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('approver_id', $user->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
            
            // Superadmin can approve any pending level
            if ($isSuperadmin && !$currentApprovalFlow) {
                $currentApprovalFlow = DB::table('food_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();
            }
            
            if (!$currentApprovalFlow) {
                throw new \Exception('Anda tidak memiliki approval yang pending untuk adjustment ini.');
            }
            
            $currentLevel = $currentApprovalFlow->approval_level;
            
            // Check if all lower levels have been approved
            $lowerLevelsPending = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('approval_level', '<', $currentLevel)
                ->where('status', 'PENDING')
                ->exists();
            
            if ($lowerLevelsPending) {
                throw new \Exception('Level approval sebelumnya harus di-approve terlebih dahulu.');
            }
            
            // Approve current level
            DB::table('food_inventory_adjustment_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'notes' => $request->note,
                    'updated_at' => now(),
                ]);
            
            // Check if all levels approved
            $allApproved = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('status', 'PENDING')
                ->doesntExist();
            
            if ($allApproved) {
                // All approved - update status and process inventory
                DB::table('food_inventory_adjustments')
                    ->where('id', $id)
                    ->update([
                        'status' => 'approved',
                        'updated_at' => now(),
                    ]);
                
                $this->processInventory($id);
                
                $message = 'Stock adjustment berhasil di-approve dan inventory telah di-update!';
            } else {
                // Send notification to next approver
                $this->sendNotificationToNextApprover($id);
                
                $message = 'Stock adjustment berhasil di-approve!';
            }
            
            // Activity log
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'approve',
                'module' => 'stock_adjustment',
                'description' => "Approve stock adjustment ID: {$id} (Level {$currentLevel})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            
            DB::commit();
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            } else {
                return redirect()->route('food-inventory-adjustment.show', $id)
                    ->with('success', $message);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodInventoryAdjustment approve error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Gagal approve: ' . $e->getMessage());
            }
        }
    }

    public function reject(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $adj = DB::table('food_inventory_adjustments')->where('id', $id)->first();
            if (!$adj) {
                throw new \Exception('Adjustment not found');
            }
            
            // Check if adjustment has approval flows
            $hasApprovalFlows = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->exists();
            
            if (!$hasApprovalFlows) {
                throw new \Exception('Approval flow not found.');
            }
            
            // Find current approval flow for this user
            $currentApprovalFlow = DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->where('approver_id', $user->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
            
            // Superadmin can reject any pending level
            if ($isSuperadmin && !$currentApprovalFlow) {
                $currentApprovalFlow = DB::table('food_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();
            }
            
            if (!$currentApprovalFlow) {
                throw new \Exception('Anda tidak memiliki approval yang pending untuk adjustment ini.');
            }
            
            // Reject current level
            DB::table('food_inventory_adjustment_approval_flows')
                ->where('id', $currentApprovalFlow->id)
                ->update([
                    'status' => 'REJECTED',
                    'approved_at' => now(),
                    'notes' => $request->note,
                    'updated_at' => now(),
                ]);
            
            // Update adjustment status to rejected
            DB::table('food_inventory_adjustments')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);
            
            // Activity log
            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'reject',
                'module' => 'stock_adjustment',
                'description' => 'Reject stock adjustment ID: ' . $id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            
            DB::commit();
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            } else {
                return redirect()->route('food-inventory-adjustment.show', $id)
                    ->with('success', 'Stock adjustment berhasil direject!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            } else {
                return redirect()->back()->with('error', 'Gagal reject: ' . $e->getMessage());
            }
        }
    }

    public function show($id)
    {
        $adjustment = FoodInventoryAdjustment::with([
            'items.item',
            'warehouse',
            'creator',
        ])->findOrFail($id);
        
        $user = auth()->user();
        
        // Get approval flows
        $approvalFlows = DB::table('food_inventory_adjustment_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.adjustment_id', $id)
            ->select(
                'af.*',
                'u.nama_lengkap as approver_name',
                'u.email as approver_email',
                'j.nama_jabatan as approver_jabatan'
            )
            ->orderBy('af.approval_level')
            ->get();
        
        $adjustment->approval_flows = $approvalFlows;
        
        // Check if current user can approve
        $currentApprover = DB::table('food_inventory_adjustment_approval_flows')
            ->where('adjustment_id', $id)
            ->where('approver_id', $user->id)
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();
        
        return inertia('FoodInventoryAdjustment/Show', [
            'adjustment' => $adjustment,
            'user' => $user,
            'approval_flows' => $approvalFlows,
            'current_approval_flow_id' => $currentApprover ? $currentApprover->id : null,
            'can_approve' => $currentApprover !== null || ($user->id_role === '5af56935b011a' && $user->status === 'A'),
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $adj = FoodInventoryAdjustment::with(['items'])->findOrFail($id);
            
            // Only allow delete if not approved yet
            if ($adj->status === 'approved') {
                throw new \Exception('Adjustment yang sudah approved tidak bisa dihapus. Silakan buat adjustment baru untuk rollback.');
            }
            
            // Delete approval flows
            DB::table('food_inventory_adjustment_approval_flows')
                ->where('adjustment_id', $id)
                ->delete();
            
            // Delete items and header
            $adj->items()->delete();
            $adj->delete();
            
            // Activity log
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'stock_adjustment',
                'description' => 'Menghapus stock adjustment: ' . $adj->number,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => json_encode($adj->toArray()),
                'new_data' => null,
                'created_at' => now(),
            ]);
            
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get pending approvals for current user
     */
    public function getPendingApprovals()
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            $userId = $user->id;
            
            \Log::info('Warehouse Stock Adjustment approvals check (with approval flows)', [
                'user_id' => $userId,
                'isSuperadmin' => $isSuperadmin
            ]);
            
            // Query berdasarkan approval flows
            $query = DB::table('food_inventory_adjustment_approval_flows as af')
                ->join('food_inventory_adjustments as fia', 'af.adjustment_id', '=', 'fia.id')
                ->join('warehouses as w', 'fia.warehouse_id', '=', 'w.id')
                ->leftJoin('users as creator', 'fia.created_by', '=', 'creator.id')
                ->where('af.status', 'PENDING')
                ->where('fia.status', 'waiting_approval');
            
            if (!$isSuperadmin) {
                // Regular user: hanya lihat approval yang assigned ke mereka
                $query->where('af.approver_id', $userId);
            }
            
            // Check that all previous levels have been approved
            $query->whereNotExists(function($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('food_inventory_adjustment_approval_flows as af2')
                    ->whereRaw('af2.adjustment_id = af.adjustment_id')
                    ->whereRaw('af2.approval_level < af.approval_level')
                    ->where('af2.status', 'PENDING');
            });
            
            $adjustments = $query->select(
                'fia.id',
                'fia.number',
                'fia.date',
                'fia.type',
                'fia.reason',
                'fia.status',
                'fia.created_at',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'creator.id as creator_id',
                'creator.nama_lengkap as creator_name',
                'af.approver_id',
                'af.approval_level'
            )
            ->orderBy('fia.created_at', 'desc')
            ->get();
            
            // Get items count for each adjustment
            $transformedAdjustments = $adjustments->map(function($adj) use ($user) {
                $itemsCount = DB::table('food_inventory_adjustment_items')
                    ->where('adjustment_id', $adj->id)
                    ->count();
                
                // Get approver name for display
                $approver = DB::table('users')
                    ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
                    ->where('users.id', $adj->approver_id)
                    ->select('users.nama_lengkap', 'j.nama_jabatan')
                    ->first();
                
                return [
                    'id' => $adj->id,
                    'number' => $adj->number,
                    'date' => $adj->date,
                    'type' => $adj->type,
                    'reason' => $adj->reason,
                    'status' => $adj->status,
                    'warehouse' => [
                        'id' => $adj->warehouse_id,
                        'name' => $adj->warehouse_name,
                    ],
                    'creator' => [
                        'id' => $adj->creator_id,
                        'nama_lengkap' => $adj->creator_name,
                    ],
                    'created_at' => $adj->created_at,
                    'items_count' => $itemsCount,
                    'approver_name' => $approver ? $approver->nama_lengkap . ($approver->nama_jabatan ? " ({$approver->nama_jabatan})" : '') : 'Unknown',
                    'approval_level' => $adj->approval_level,
                ];
            });
            
            \Log::info('Warehouse Stock Adjustment query result', [
                'count' => $transformedAdjustments->count()
            ]);
            
            return response()->json([
                'success' => true,
                'adjustments' => $transformedAdjustments
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending warehouse stock adjustment approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }
    
    /**
     * Get approvers for approval flow
     */
    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        
        $users = User::where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where(function($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();
        
        return response()->json(['success' => true, 'users' => $users]);
    }
    
    /**
     * Send notification to the next approver in line
     */
    private function sendNotificationToNextApprover($adjustmentId)
    {
        // Get the next pending approver with lowest level
        $nextApprovalFlow = DB::table('food_inventory_adjustment_approval_flows')
            ->where('adjustment_id', $adjustmentId)
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();
        
        if (!$nextApprovalFlow) {
            return; // No more approvers
        }
        
        // Check if all previous levels have been approved
        $previousLevelsPending = DB::table('food_inventory_adjustment_approval_flows')
            ->where('adjustment_id', $adjustmentId)
            ->where('approval_level', '<', $nextApprovalFlow->approval_level)
            ->where('status', 'PENDING')
            ->exists();
        
        if ($previousLevelsPending) {
            return; // Don't send notification yet
        }
        
        // Get adjustment details
        $adjustment = DB::table('food_inventory_adjustments as fia')
            ->join('warehouses as w', 'fia.warehouse_id', '=', 'w.id')
            ->where('fia.id', $adjustmentId)
            ->select('fia.number', 'w.name as warehouse_name', 'fia.type')
            ->first();
        
        if (!$adjustment) {
            return;
        }
        
        $typeLabel = $adjustment->type === 'in' ? 'Stock In' : 'Stock Out';
        $message = "Stock Adjustment #{$adjustment->number} ({$typeLabel}) dari {$adjustment->warehouse_name} menunggu approval Anda.\n";
        $message .= "Level Approval: {$nextApprovalFlow->approval_level}";
        
        // Send notification
        try {
            NotificationService::insert([
                'user_id' => $nextApprovalFlow->approver_id,
                'type' => 'stock_adjustment_approval',
                'message' => $message,
                'url' => '/food-inventory-adjustment/' . $adjustmentId,
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send notification to next approver', [
                'adjustment_id' => $adjustmentId,
                'approver_id' => $nextApprovalFlow->approver_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Helper untuk generate nomor adjustment
    private function generateAdjustmentNumber()
    {
        $prefix = 'SA-' . date('Ym') . '-';
        $last = DB::table('food_inventory_adjustments')
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');
        $next = $last ? (int)substr($last, -4) + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // Proses update inventory setelah approved
    private function processInventory($adjustmentId)
    {
        $adj = FoodInventoryAdjustment::with(['items', 'warehouse'])->find($adjustmentId);
        if (!$adj) return;
        
        foreach ($adj->items as $item) {
            $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                $itemMaster = \App\Models\Item::find($item->item_id);
                $inventoryItem = \App\Models\FoodInventoryItem::create([
                    'item_id' => $item->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            $inventory_item_id = $inventoryItem->id;
            $itemMaster = \App\Models\Item::find($item->item_id);
            $unit = $item->unit;
            $qty_input = $item->qty;
            $qty_small = 0; $qty_medium = 0; $qty_large = 0;
            
            $unitSmall = optional($itemMaster->smallUnit)->name;
            $unitMedium = optional($itemMaster->mediumUnit)->name;
            $unitLarge = optional($itemMaster->largeUnit)->name;
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
            
            // Convert qty based on unit
            if ($unit === $unitSmall) {
                $qty_small = $qty_input;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unit === $unitMedium) {
                $qty_medium = $qty_input;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unit === $unitLarge) {
                $qty_large = $qty_input;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                $qty_small = $qty_input;
            }
            
            // Update stock
            $stock = \App\Models\FoodInventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $inventory_item_id,
                    'warehouse_id' => $adj->warehouse_id
                ],
                [
                    'qty_small' => 0,
                    'qty_medium' => 0,
                    'qty_large' => 0,
                    'value' => 0,
                    'last_cost_small' => 0,
                    'last_cost_medium' => 0,
                    'last_cost_large' => 0,
                ]
            );
            
            if ($adj->type === 'in') {
                $stock->qty_small += $qty_small;
                $stock->qty_medium += $qty_medium;
                $stock->qty_large += $qty_large;
            } else {
                $stock->qty_small -= $qty_small;
                $stock->qty_medium -= $qty_medium;
                $stock->qty_large -= $qty_large;
            }
            
            $stock->value = ($stock->qty_small * $stock->last_cost_small)
                + ($stock->qty_medium * $stock->last_cost_medium)
                + ($stock->qty_large * $stock->last_cost_large);
            $stock->save();
            
            // Insert stock card
            \App\Models\FoodInventoryCard::create([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $adj->warehouse_id,
                'date' => $adj->date,
                'reference_type' => 'stock_adjustment',
                'reference_id' => $adj->id,
                'in_qty_small' => $adj->type === 'in' ? $qty_small : 0,
                'in_qty_medium' => $adj->type === 'in' ? $qty_medium : 0,
                'in_qty_large' => $adj->type === 'in' ? $qty_large : 0,
                'out_qty_small' => $adj->type === 'out' ? $qty_small : 0,
                'out_qty_medium' => $adj->type === 'out' ? $qty_medium : 0,
                'out_qty_large' => $adj->type === 'out' ? $qty_large : 0,
                'cost_per_small' => $stock->last_cost_small,
                'cost_per_medium' => $stock->last_cost_medium,
                'cost_per_large' => $stock->last_cost_large,
                'value_in' => $adj->type === 'in' ? $qty_small * $stock->last_cost_small : 0,
                'value_out' => $adj->type === 'out' ? $qty_small * $stock->last_cost_small : 0,
                'saldo_qty_small' => $stock->qty_small,
                'saldo_qty_medium' => $stock->qty_medium,
                'saldo_qty_large' => $stock->qty_large,
                'saldo_value' => $stock->value,
                'description' => 'Stock Adjustment',
            ]);
        }
    }
}
