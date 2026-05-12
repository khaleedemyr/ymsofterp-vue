<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AssetInventoryAdjustment;
use App\Models\AssetInventoryAdjustmentItem;
use App\Models\AssetInventoryAdjustmentApprovalFlow;
use App\Services\NotificationService;

class AssetInventoryAdjustmentController extends Controller
{
    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_inventory_adjustments as a')
            ->leftJoin('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->select(
                'a.id', 'a.number', 'a.date', 'a.type', 'a.reason',
                'a.status', 'a.created_by', 'a.created_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('a.outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('a.number', 'like', "%{$search}%")
                  ->orWhere('a.reason', 'like', "%{$search}%");
            });
        }
        if ($request->from) {
            $query->whereDate('a.date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('a.date', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('a.status', $request->status);
        }
        if ($request->type) {
            $query->where('a.type', $request->type);
        }
        if ($request->outlet_id) {
            $query->where('a.outlet_id', $request->outlet_id);
        }

        $adjustments = $query->orderByDesc('a.created_at')->paginate(15)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('AssetInventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'type', 'outlet_id']),
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return inertia('AssetInventoryAdjustment/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'type' => 'required|in:in,out',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $number = AssetInventoryAdjustment::generateNumber();

            $adjustment = AssetInventoryAdjustment::create([
                'number' => $number,
                'date' => $validated['date'],
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                AssetInventoryAdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['selected_unit'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetInventoryAdjustmentApprovalFlow::create([
                    'adjustment_id' => $adjustment->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'asset_stock_adjustment',
                'description' => 'Create asset stock adjustment: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($adjustment->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->sendNotificationToNextApprover($adjustment->id);

            return redirect()->route('asset-inventory-adjustments.show', $adjustment->id)
                ->with('success', 'Adjustment berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error store asset inventory adjustment', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal membuat adjustment: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $adjustment = AssetInventoryAdjustment::with([
            'items.item',
            'outlet',
            'warehouseOutlet',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $adjustment);

        $canApprove = false;
        if ($adjustment->status === 'waiting_approval') {
            $nextFlow = $adjustment->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
            if ($nextFlow && $nextFlow->approver_id == $user->id) {
                $canApprove = true;
            }
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            if ($isSuperadmin && $nextFlow) {
                $canApprove = true;
            }
        }

        $adjustmentData = [
            'id' => $adjustment->id,
            'number' => $adjustment->number,
            'date' => $adjustment->date->format('Y-m-d'),
            'type' => $adjustment->type,
            'reason' => $adjustment->reason,
            'status' => $adjustment->status,
            'outlet_name' => optional($adjustment->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($adjustment->warehouseOutlet)->name,
            'creator_name' => optional($adjustment->creator)->nama_lengkap,
            'items' => $adjustment->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty' => $item->qty,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $adjustment->approvalFlows->map(function ($flow) {
                $jabatan = DB::table('tbl_data_jabatan')
                    ->where('id_jabatan', optional($flow->approver)->id_jabatan)
                    ->value('nama_jabatan');
                return [
                    'id' => $flow->id,
                    'approver_name' => optional($flow->approver)->nama_lengkap,
                    'approver_jabatan' => $jabatan ?? '-',
                    'approval_level' => $flow->approval_level,
                    'status' => $flow->status,
                    'approved_at' => $flow->approved_at,
                    'rejected_at' => $flow->rejected_at,
                    'comments' => $flow->comments,
                ];
            }),
        ];

        return inertia('AssetInventoryAdjustment/Show', [
            'adjustment' => $adjustmentData,
            'canApprove' => $canApprove,
            'user' => $user,
        ]);
    }

    public function destroy($id)
    {
        $adjustment = AssetInventoryAdjustment::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $adjustment);

        if ($adjustment->status !== 'waiting_approval') {
            return redirect()->back()->with('error', 'Hanya adjustment waiting approval yang bisa dihapus.');
        }

        DB::beginTransaction();
        try {
            $adjustment->approvalFlows()->delete();
            $adjustment->items()->delete();
            $adjustment->delete();
            DB::commit();
            return redirect()->route('asset-inventory-adjustments.index')
                ->with('success', 'Adjustment berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    // ─── APPROVAL (shared web+api) ───────────────────────────────────

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $adjustment = AssetInventoryAdjustment::with(['approvalFlows', 'items'])->findOrFail($id);
        $this->assertUserCanView($user, $adjustment);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate(['comments' => 'required|string']);
        }

        if ($adjustment->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Tidak bisa approve adjustment ini'], 400);
        }

        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        DB::beginTransaction();
        try {
            $nextFlow = $adjustment->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();

            if (!$nextFlow) {
                return response()->json(['success' => false, 'message' => 'Tidak ada approval pending'], 400);
            }
            if (!$isSuperadmin && $nextFlow->approver_id != $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($validated['action'] === 'approve') {
                $nextFlow->approve($validated['comments'] ?? null);

                $hasPending = $adjustment->approvalFlows()->where('status', 'PENDING')->count() > 0;
                if (!$hasPending) {
                    $adjustment->update(['status' => 'approved']);
                    $this->processInventory($adjustment->fresh()->load('items'));
                } else {
                    $this->sendNotificationToNextApprover($adjustment->id);
                }
            } else {
                $nextFlow->reject($validated['comments'] ?? null);
                $adjustment->update(['status' => 'rejected']);
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => $validated['action'],
                'module' => 'asset_stock_adjustment',
                'description' => ucfirst($validated['action']) . ' asset stock adjustment: ' . $adjustment->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($adjustment->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $validated['action'] === 'approve' ? 'Adjustment berhasil disetujui.' : 'Adjustment ditolak.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approve asset inventory adjustment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');

        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A');

        if ($search) {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->select(
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'users' => $users]);
    }

    // ─── API (JSON for mobile) ───────────────────────────────────────

    public function apiIndex(Request $request)
    {
        $user = Auth::user();

        $query = DB::table('asset_inventory_adjustments as a')
            ->leftJoin('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->select(
                'a.id', 'a.number', 'a.date', 'a.type', 'a.reason',
                'a.status', 'a.created_at',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('a.outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('a.number', 'like', "%{$request->search}%")
                  ->orWhere('a.reason', 'like', "%{$request->search}%");
            });
        }
        if ($request->date_from) {
            $query->whereDate('a.date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('a.date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('a.status', $request->status);
        }
        if ($request->type) {
            $query->where('a.type', $request->type);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $adjustments = $query->orderByDesc('a.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($adjustments);
    }

    public function apiCreateData(Request $request)
    {
        $user = Auth::user();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'user' => [
                'id' => $user->id,
                'id_outlet' => $user->id_outlet,
                'nama_lengkap' => $user->nama_lengkap,
            ],
        ]);
    }

    public function apiShow($id)
    {
        $user = Auth::user();
        $adjustment = AssetInventoryAdjustment::with([
            'items.item',
            'outlet',
            'warehouseOutlet',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $adjustment);

        $canApprove = false;
        if ($adjustment->status === 'waiting_approval') {
            $nextFlow = $adjustment->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
            if ($nextFlow && $nextFlow->approver_id == $user->id) {
                $canApprove = true;
            }
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            if ($isSuperadmin && $nextFlow) {
                $canApprove = true;
            }
        }

        return response()->json([
            'id' => $adjustment->id,
            'number' => $adjustment->number,
            'date' => $adjustment->date->format('Y-m-d'),
            'type' => $adjustment->type,
            'reason' => $adjustment->reason,
            'status' => $adjustment->status,
            'outlet_name' => optional($adjustment->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($adjustment->warehouseOutlet)->name,
            'creator_name' => optional($adjustment->creator)->nama_lengkap,
            'can_approve' => $canApprove,
            'items' => $adjustment->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty' => $item->qty,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $adjustment->approvalFlows->map(function ($flow) {
                $jabatan = DB::table('tbl_data_jabatan')
                    ->where('id_jabatan', optional($flow->approver)->id_jabatan)
                    ->value('nama_jabatan');
                return [
                    'id' => $flow->id,
                    'approver_id' => $flow->approver_id,
                    'approver_name' => optional($flow->approver)->nama_lengkap,
                    'approver_jabatan' => $jabatan ?? '-',
                    'approval_level' => $flow->approval_level,
                    'status' => $flow->status,
                    'approved_at' => $flow->approved_at,
                    'rejected_at' => $flow->rejected_at,
                    'comments' => $flow->comments,
                ];
            }),
        ]);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'type' => 'required|in:in,out',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $number = AssetInventoryAdjustment::generateNumber();

            $adjustment = AssetInventoryAdjustment::create([
                'number' => $number,
                'date' => $validated['date'],
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                AssetInventoryAdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['selected_unit'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetInventoryAdjustmentApprovalFlow::create([
                    'adjustment_id' => $adjustment->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            $this->sendNotificationToNextApprover($adjustment->id);

            return response()->json([
                'success' => true,
                'message' => 'Adjustment berhasil dibuat.',
                'adjustment_id' => $adjustment->id,
                'number' => $number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error apiStore asset inventory adjustment', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $adjustment = AssetInventoryAdjustment::findOrFail($id);
        $this->assertUserCanView(Auth::user(), $adjustment);

        if ($adjustment->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Hanya adjustment waiting approval yang bisa dihapus.'], 422);
        }

        DB::beginTransaction();
        try {
            $adjustment->approvalFlows()->delete();
            $adjustment->items()->delete();
            $adjustment->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Adjustment berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── INVENTORY PROCESSING ────────────────────────────────────────

    private function processInventory($adjustment)
    {
        $warehouseOutlet = DB::table('warehouse_outlets')
            ->where('id', $adjustment->warehouse_outlet_id)
            ->first();

        if (!$warehouseOutlet) {
            throw new \Exception('Warehouse outlet tidak ditemukan');
        }

        $outletId = $warehouseOutlet->outlet_id;

        foreach ($adjustment->items as $adjItem) {
            $itemMaster = DB::table('items')->where('id', $adjItem->item_id)->first();
            if (!$itemMaster) continue;

            $inventoryItem = DB::table('asset_inventory_items')
                ->where('item_id', $adjItem->item_id)
                ->first();

            if (!$inventoryItem) {
                $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                    'item_id' => $adjItem->item_id,
                    'name' => $itemMaster->name,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'small_conversion_qty' => $itemMaster->small_conversion_qty,
                    'medium_conversion_qty' => $itemMaster->medium_conversion_qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = $inventoryItem->id;
            }

            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

            $smallUnitName = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
            $mediumUnitName = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
            $largeUnitName = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');

            $qty = $adjItem->qty;
            $selectedUnit = $adjItem->unit;

            if ($selectedUnit == $smallUnitName || $adjItem->item_id && $itemMaster->small_unit_id && !$mediumUnitName && !$largeUnitName) {
                $qty_small = $qty;
                $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
            } elseif ($selectedUnit == $mediumUnitName) {
                $qty_medium = $qty;
                $qty_small = $qty * $mediumConv;
                $qty_large = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
            } elseif ($selectedUnit == $largeUnitName) {
                $qty_large = $qty;
                $qty_small = $qty * $smallConv;
                $qty_medium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
            } else {
                $qty_small = $qty;
                $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
            }

            $stock = DB::table('asset_inventory_stocks')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $outletId)
                ->where('warehouse_outlet_id', $adjustment->warehouse_outlet_id)
                ->first();

            $costSmall = $stock->last_cost_small ?? 0;
            $costMedium = $stock->last_cost_medium ?? 0;
            $costLarge = $stock->last_cost_large ?? 0;

            if ($adjustment->type === 'in') {
                if (!$stock) {
                    DB::table('asset_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'outlet_id' => $outletId,
                        'warehouse_outlet_id' => $adjustment->warehouse_outlet_id,
                        'qty_small' => $qty_small,
                        'qty_medium' => $qty_medium,
                        'qty_large' => $qty_large,
                        'value' => $qty_small * $costSmall,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $saldoSmall = $qty_small;
                    $saldoMedium = $qty_medium;
                    $saldoLarge = $qty_large;
                } else {
                    $saldoSmall = $stock->qty_small + $qty_small;
                    $saldoMedium = $stock->qty_medium + $qty_medium;
                    $saldoLarge = $stock->qty_large + $qty_large;

                    DB::table('asset_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $saldoSmall,
                            'qty_medium' => $saldoMedium,
                            'qty_large' => $saldoLarge,
                            'value' => $saldoSmall * $costSmall,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('asset_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $adjustment->warehouse_outlet_id,
                    'date' => $adjustment->date,
                    'reference_type' => 'asset_stock_adjustment',
                    'reference_id' => $adjustment->id,
                    'in_qty_small' => $qty_small,
                    'in_qty_medium' => $qty_medium,
                    'in_qty_large' => $qty_large,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => $qty_small * $costSmall,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldoSmall,
                    'saldo_qty_medium' => $saldoMedium,
                    'saldo_qty_large' => $saldoLarge,
                    'saldo_value' => $saldoSmall * $costSmall,
                    'description' => 'Stock In - Asset Stock Adjustment',
                    'created_at' => now(),
                ]);
            } else {
                if (!$stock) {
                    throw new \Exception('Stok tidak ditemukan untuk item: ' . $itemMaster->name);
                }

                if ($stock->qty_small < $qty_small) {
                    throw new \Exception('Stok tidak cukup untuk item: ' . $itemMaster->name . '. Tersedia: ' . $stock->qty_small . ', Dibutuhkan: ' . $qty_small);
                }

                $saldoSmall = $stock->qty_small - $qty_small;
                $saldoMedium = $stock->qty_medium - $qty_medium;
                $saldoLarge = $stock->qty_large - $qty_large;

                DB::table('asset_inventory_stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'qty_small' => $saldoSmall,
                        'qty_medium' => $saldoMedium,
                        'qty_large' => $saldoLarge,
                        'value' => $saldoSmall * $costSmall,
                        'updated_at' => now(),
                    ]);

                DB::table('asset_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $adjustment->warehouse_outlet_id,
                    'date' => $adjustment->date,
                    'reference_type' => 'asset_stock_adjustment',
                    'reference_id' => $adjustment->id,
                    'in_qty_small' => 0,
                    'in_qty_medium' => 0,
                    'in_qty_large' => 0,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => 0,
                    'value_out' => $qty_small * $costSmall,
                    'saldo_qty_small' => $saldoSmall,
                    'saldo_qty_medium' => $saldoMedium,
                    'saldo_qty_large' => $saldoLarge,
                    'saldo_value' => $saldoSmall * $costSmall,
                    'description' => 'Stock Out - Asset Stock Adjustment',
                    'created_at' => now(),
                ]);
            }

            $lastCostHistory = DB::table('asset_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $outletId)
                ->where('warehouse_outlet_id', $adjustment->warehouse_outlet_id)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();
            $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;

            DB::table('asset_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventoryItemId,
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $adjustment->warehouse_outlet_id,
                'date' => $adjustment->date,
                'old_cost' => $old_cost,
                'new_cost' => $costSmall,
                'mac' => $costSmall,
                'type' => 'asset_stock_adjustment',
                'reference_type' => 'asset_stock_adjustment',
                'reference_id' => $adjustment->id,
                'created_at' => now(),
            ]);
        }
    }

    // ─── PENDING APPROVALS ──────────────────────────────────────────

    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'headers' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = DB::table('asset_inventory_adjustments as a')
            ->join('asset_inventory_adjustment_approval_flows as af', 'a.id', '=', 'af.adjustment_id')
            ->leftJoin('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->join('users as creator', 'a.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('a.status', 'waiting_approval');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'a.id', 'a.number', 'a.date', 'a.status', 'a.type', 'a.reason as notes',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) return true;
                $lowerPending = DB::table('asset_inventory_adjustment_approval_flows')
                    ->where('adjustment_id', $header->id)
                    ->where('approval_level', '<', $header->approval_level)
                    ->where('status', 'PENDING')
                    ->count();
                return $lowerPending === 0;
            })
            ->unique('id')
            ->values();

        return response()->json(['success' => true, 'headers' => $pendingHeaders]);
    }

    public function getApprovalDetails($id)
    {
        $adjustment = DB::table('asset_inventory_adjustments as a')
            ->leftJoin('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'a.warehouse_outlet_id', '=', 'wo.id')
            ->join('users as creator', 'a.created_by', '=', 'creator.id')
            ->where('a.id', $id)
            ->select('a.*', 'o.nama_outlet as outlet_name', 'wo.name as warehouse_name', 'creator.nama_lengkap as creator_name')
            ->first();

        if (!$adjustment) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = DB::table('asset_inventory_adjustment_items as ai')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->where('ai.adjustment_id', $id)
            ->select('ai.*', 'i.name as item_name')
            ->get();

        $approvalFlows = DB::table('asset_inventory_adjustment_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.adjustment_id', $id)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name', 'j.nama_jabatan as approver_jabatan')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $adjustment,
            'items' => $items,
            'approval_flows' => $approvalFlows,
        ]);
    }

    private function sendNotificationToNextApprover($adjustmentId)
    {
        try {
            $nextApprover = DB::table('asset_inventory_adjustment_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.adjustment_id', $adjustmentId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap')
                ->first();

            if (!$nextApprover) return;

            $adjustment = DB::table('asset_inventory_adjustments as a')
                ->leftJoin('tbl_data_outlet as o', 'a.outlet_id', '=', 'o.id_outlet')
                ->join('users as creator', 'a.created_by', '=', 'creator.id')
                ->where('a.id', $adjustmentId)
                ->select('a.*', 'o.nama_outlet', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$adjustment) return;

            $typeLabel = $adjustment->type === 'in' ? 'Stock In' : 'Stock Out';

            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'asset_stock_adjustment_approval',
                'title' => 'Approval Asset Stock Adjustment',
                'message' => "Asset Adjustment {$adjustment->number} ({$typeLabel}) dari outlet {$adjustment->nama_outlet} oleh {$adjustment->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending Asset Adjustment notification: ' . $e->getMessage());
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────

    private function assertUserCanView($user, AssetInventoryAdjustment $adjustment): void
    {
        if ((int) ($user->id_outlet ?? 0) === 1) {
            return;
        }
        if ((int) ($user->id_outlet ?? 0) !== (int) $adjustment->outlet_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
