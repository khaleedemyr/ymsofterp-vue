<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AssetInventoryTransfer;
use App\Models\AssetInventoryTransferItem;
use App\Models\AssetInventoryTransferApprovalFlow;
use App\Services\NotificationService;
use App\Services\AssetInventoryStockService;

class AssetInventoryTransferController extends Controller
{
    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_inventory_transfers as t')
            ->leftJoin('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->leftJoin('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as oo', 't.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as of', 'wf.outlet_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 'wt.outlet_id', '=', 'ot.id_outlet')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->select(
                't.id', 't.transfer_number', 't.transfer_date', 't.status',
                't.notes', 't.created_by', 't.created_at', 't.owner_outlet_id',
                'oo.nama_outlet as owner_outlet_name',
                'wf.name as warehouse_outlet_from_name',
                'wt.name as warehouse_outlet_to_name',
                'of.nama_outlet as outlet_from_name',
                'ot.nama_outlet as outlet_to_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('t.owner_outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where('t.transfer_number', 'like', "%{$search}%");
        }
        if ($request->from) {
            $query->whereDate('t.transfer_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('t.transfer_date', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('t.status', $request->status);
        }
        if ($request->owner_outlet_id) {
            $query->where('t.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->outlet_id) {
            $query->where(function ($q) use ($request) {
                $q->where('wf.outlet_id', $request->outlet_id)
                  ->orWhere('wt.outlet_id', $request->outlet_id);
            });
        }

        $transfers = $query->orderByDesc('t.created_at')->paginate(15)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('AssetInventoryTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'outlet_id', 'owner_outlet_id']),
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

        return inertia('AssetInventoryTransfer/Create', [
            'user' => $user,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_outlet_id' => 'required|integer',
            'transfer_date' => 'required|date',
            'warehouse_outlet_from_id' => 'required|integer',
            'warehouse_outlet_to_id' => 'required|integer|different:warehouse_outlet_from_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.unit_id' => 'nullable|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.note' => 'nullable|string',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseFrom || !$warehouseTo) {
                throw new \Exception('Warehouse outlet tidak ditemukan');
            }

            $transferNumber = AssetInventoryTransfer::generateNumber();

            $transfer = AssetInventoryTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'owner_outlet_id' => $validated['owner_outlet_id'],
                'outlet_id' => $warehouseFrom->outlet_id,
                'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $itemMaster = DB::table('items')->where('id', $itemData['item_id'])->first();
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

                $unitId = $itemData['unit_id'] ?? $itemMaster->small_unit_id;
                $qty = $itemData['qty'];

                if ($unitId == $itemMaster->small_unit_id) {
                    $qty_small = $qty;
                    $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                    $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qty_medium = $qty;
                    $qty_small = $qty * $mediumConv;
                    $qty_large = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
                } elseif ($unitId == $itemMaster->large_unit_id) {
                    $qty_large = $qty;
                    $qty_small = $qty * $smallConv;
                    $qty_medium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
                } else {
                    $qty_small = $qty;
                    $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                    $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
                }

                AssetInventoryTransferItem::create([
                    'asset_inventory_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $unitId,
                    'qty' => $qty,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            $this->persistTransferApprovers($transfer, $validated['approvers'], true);

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'submit',
                'module' => 'asset_inventory_transfer',
                'description' => 'Submit asset inventory transfer: ' . $transferNumber,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($transfer->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('asset-inventory-transfers.show', $transfer->id)
                ->with('success', 'Transfer asset berhasil dibuat dan dikirim untuk approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error store asset inventory transfer', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal membuat transfer: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $transfer = AssetInventoryTransfer::with([
            'items.item', 'items.unit',
            'warehouseOutletFrom', 'warehouseOutletTo',
            'creator', 'approver',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $transfer);

        $ownerOutlet = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->owner_outlet_id)->first();
        $outletFrom = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->warehouseOutletFrom->outlet_id ?? null)->first();
        $outletTo = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->warehouseOutletTo->outlet_id ?? null)->first();

        $canApprove = false;
        if ($transfer->status === 'submitted') {
            $nextFlow = $transfer->approvalFlows()
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

        $transferData = [
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date->format('Y-m-d'),
            'status' => $transfer->status,
            'notes' => $transfer->notes,
            'owner_outlet_name' => $ownerOutlet->nama_outlet ?? '-',
            'warehouse_outlet_from_name' => optional($transfer->warehouseOutletFrom)->name,
            'warehouse_outlet_to_name' => optional($transfer->warehouseOutletTo)->name,
            'outlet_from_name' => $outletFrom->nama_outlet ?? '-',
            'outlet_to_name' => $outletTo->nama_outlet ?? '-',
            'creator_name' => optional($transfer->creator)->nama_lengkap,
            'approval_by_name' => optional($transfer->approver)->nama_lengkap,
            'approval_at' => $transfer->approval_at,
            'approval_notes' => $transfer->approval_notes,
            'items' => $transfer->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit_name' => optional($item->unit)->name ?? '-',
                    'qty' => $item->qty,
                    'qty_small' => $item->qty_small,
                    'qty_medium' => $item->qty_medium,
                    'qty_large' => $item->qty_large,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $transfer->approvalFlows->map(function ($flow) {
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
        ];

        return inertia('AssetInventoryTransfer/Show', [
            'transfer' => $transferData,
            'canApprove' => $canApprove,
            'user' => $user,
        ]);
    }

    public function destroy($id)
    {
        $transfer = AssetInventoryTransfer::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $transfer);

        if ($transfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya transfer draft yang bisa dihapus.');
        }

        DB::beginTransaction();
        try {
            $transfer->approvalFlows()->delete();
            $transfer->items()->delete();
            $transfer->delete();
            DB::commit();
            return redirect()->route('asset-inventory-transfers.index')
                ->with('success', 'Transfer berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    // ─── SUBMIT & APPROVAL (shared web+api) ─────────────────────────

    public function submit(Request $request, $id)
    {
        $transfer = AssetInventoryTransfer::with('approvalFlows')->findOrFail($id);
        $this->assertUserCanView(auth()->user(), $transfer);

        if ($transfer->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Transfer hanya bisa di-submit dari status draft'], 422);
        }

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $this->persistTransferApprovers($transfer, $validated['approvers'], true);

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'submit',
                'module' => 'asset_inventory_transfer',
                'description' => 'Submit asset inventory transfer: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($transfer->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Transfer berhasil di-submit.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error submit asset inventory transfer', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $transfer = AssetInventoryTransfer::with(['approvalFlows', 'items'])->findOrFail($id);
        $this->assertUserCanView($user, $transfer);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate(['comments' => 'required|string']);
        }

        if ($transfer->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Tidak bisa approve transfer ini'], 400);
        }

        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        DB::beginTransaction();
        try {
            $nextFlow = $transfer->approvalFlows()
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

                $hasPending = $transfer->approvalFlows()->where('status', 'PENDING')->count() > 0;
                if (!$hasPending) {
                    $transfer->update([
                        'status' => 'approved',
                        'approval_by' => $user->id,
                        'approval_at' => now(),
                        'approval_notes' => $validated['comments'] ?? null,
                    ]);
                    $this->processStockTransfer($transfer->fresh()->load('items'));
                } else {
                    $this->sendNotificationToNextApprover($transfer->id);
                }
            } else {
                $nextFlow->reject($validated['comments'] ?? null);
                $transfer->update([
                    'status' => 'rejected',
                    'approval_by' => $user->id,
                    'approval_at' => now(),
                    'approval_notes' => $validated['comments'] ?? null,
                ]);
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => $validated['action'],
                'module' => 'asset_inventory_transfer',
                'description' => ucfirst($validated['action']) . ' asset inventory transfer: ' . $transfer->transfer_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($transfer->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $validated['action'] === 'approve' ? 'Transfer berhasil disetujui.' : 'Transfer ditolak.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approve asset inventory transfer', ['error' => $e->getMessage()]);
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

        $query = DB::table('asset_inventory_transfers as t')
            ->leftJoin('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
            ->leftJoin('warehouse_outlets as wt', 't.warehouse_outlet_to_id', '=', 'wt.id')
            ->leftJoin('tbl_data_outlet as oo', 't.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as of', 'wf.outlet_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 'wt.outlet_id', '=', 'ot.id_outlet')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->select(
                't.id', 't.transfer_number', 't.transfer_date', 't.status',
                't.notes', 't.created_at',
                'oo.nama_outlet as owner_outlet_name',
                'wf.name as warehouse_outlet_from_name',
                'wt.name as warehouse_outlet_to_name',
                'of.nama_outlet as outlet_from_name',
                'ot.nama_outlet as outlet_to_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('t.owner_outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $query->where('t.transfer_number', 'like', "%{$request->search}%");
        }
        if ($request->date_from) {
            $query->whereDate('t.transfer_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('t.transfer_date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('t.status', $request->status);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $transfers = $query->orderByDesc('t.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($transfers);
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
        $transfer = AssetInventoryTransfer::with([
            'items.item', 'items.unit',
            'warehouseOutletFrom', 'warehouseOutletTo',
            'creator', 'approver',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $transfer);

        $ownerOutlet = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->owner_outlet_id)->first();
        $outletFrom = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->warehouseOutletFrom->outlet_id ?? null)->first();
        $outletTo = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->warehouseOutletTo->outlet_id ?? null)->first();

        $canApprove = false;
        if ($transfer->status === 'submitted') {
            $nextFlow = $transfer->approvalFlows()
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
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date->format('Y-m-d'),
            'status' => $transfer->status,
            'notes' => $transfer->notes,
            'owner_outlet_name' => $ownerOutlet->nama_outlet ?? '-',
            'warehouse_outlet_from_name' => optional($transfer->warehouseOutletFrom)->name,
            'warehouse_outlet_to_name' => optional($transfer->warehouseOutletTo)->name,
            'outlet_from_name' => $outletFrom->nama_outlet ?? '-',
            'outlet_to_name' => $outletTo->nama_outlet ?? '-',
            'creator_name' => optional($transfer->creator)->nama_lengkap,
            'approval_by_name' => optional($transfer->approver)->nama_lengkap,
            'approval_at' => $transfer->approval_at,
            'approval_notes' => $transfer->approval_notes,
            'can_approve' => $canApprove,
            'items' => $transfer->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit_id' => $item->unit_id,
                    'unit_name' => optional($item->unit)->name ?? '-',
                    'qty' => $item->qty,
                    'qty_small' => $item->qty_small,
                    'qty_medium' => $item->qty_medium,
                    'qty_large' => $item->qty_large,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $transfer->approvalFlows->map(function ($flow) {
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
            'owner_outlet_id' => 'required|integer',
            'transfer_date' => 'required|date',
            'warehouse_outlet_from_id' => 'required|integer',
            'warehouse_outlet_to_id' => 'required|integer|different:warehouse_outlet_from_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.unit_id' => 'nullable|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.note' => 'nullable|string',
            'approvers' => 'nullable|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $warehouseFrom = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_from_id'])->first();
            $warehouseTo = DB::table('warehouse_outlets')->where('id', $validated['warehouse_outlet_to_id'])->first();
            if (!$warehouseFrom || !$warehouseTo) {
                throw new \Exception('Warehouse outlet tidak ditemukan');
            }

            $transferNumber = AssetInventoryTransfer::generateNumber();

            $transfer = AssetInventoryTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $validated['transfer_date'],
                'owner_outlet_id' => $validated['owner_outlet_id'],
                'outlet_id' => $warehouseFrom->outlet_id,
                'warehouse_outlet_from_id' => $validated['warehouse_outlet_from_id'],
                'warehouse_outlet_to_id' => $validated['warehouse_outlet_to_id'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $itemMaster = DB::table('items')->where('id', $itemData['item_id'])->first();
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

                $unitId = $itemData['unit_id'] ?? $itemMaster->small_unit_id;
                $qty = $itemData['qty'];

                if ($unitId == $itemMaster->small_unit_id) {
                    $qty_small = $qty;
                    $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                    $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qty_medium = $qty;
                    $qty_small = $qty * $mediumConv;
                    $qty_large = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
                } elseif ($unitId == $itemMaster->large_unit_id) {
                    $qty_large = $qty;
                    $qty_small = $qty * $smallConv;
                    $qty_medium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
                } else {
                    $qty_small = $qty;
                    $qty_medium = $mediumConv > 0 ? $qty / $mediumConv : 0;
                    $qty_large = $smallConv > 0 ? $qty / $smallConv : 0;
                }

                AssetInventoryTransferItem::create([
                    'asset_inventory_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $unitId,
                    'qty' => $qty,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            if (!empty($validated['approvers'])) {
                $this->persistTransferApprovers($transfer, $validated['approvers'], true);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Transfer asset berhasil dibuat.',
                'transfer_id' => $transfer->id,
                'transfer_number' => $transferNumber,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error apiStore asset inventory transfer', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $transfer = AssetInventoryTransfer::findOrFail($id);
        $this->assertUserCanView(Auth::user(), $transfer);

        if ($transfer->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya transfer draft yang bisa dihapus.'], 422);
        }

        DB::beginTransaction();
        try {
            $transfer->approvalFlows()->delete();
            $transfer->items()->delete();
            $transfer->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Transfer berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getStock(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
        ]);

        $inventoryItem = DB::table('asset_inventory_items')->where('item_id', $request->item_id)->first();
        if (!$inventoryItem) {
            return response()->json(['stock_small' => 0, 'stock_medium' => 0, 'stock_large' => 0]);
        }

        $stock = AssetInventoryStockService::findStock(
            (int) $inventoryItem->id,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id
        );

        return response()->json([
            'stock_small' => $stock->qty_small ?? 0,
            'stock_medium' => $stock->qty_medium ?? 0,
            'stock_large' => $stock->qty_large ?? 0,
        ]);
    }

    // ─── INVENTORY PROCESSING ────────────────────────────────────────

    private function processStockTransfer($transfer)
    {
        $warehouseFrom = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_from_id)->first();
        $warehouseTo = DB::table('warehouse_outlets')->where('id', $transfer->warehouse_outlet_to_id)->first();
        $ownerOutletId = (int) $transfer->owner_outlet_id;
        $locationFromId = (int) $warehouseFrom->outlet_id;
        $locationToId = (int) $warehouseTo->outlet_id;

        foreach ($transfer->items as $item) {
            $inventoryItem = DB::table('asset_inventory_items')->where('item_id', $item->item_id)->first();
            if (!$inventoryItem) continue;

            $inventory_item_id = $inventoryItem->id;
            $qty_small = $item->qty_small ?? 0;
            $qty_medium = $item->qty_medium ?? 0;
            $qty_large = $item->qty_large ?? 0;

            // --- SOURCE: subtract stock ---
            $stockFrom = AssetInventoryStockService::findStock(
                $inventory_item_id,
                $ownerOutletId,
                $transfer->warehouse_outlet_from_id
            );

            if (!$stockFrom) {
                throw new \Exception('Stok tidak ditemukan di warehouse outlet asal untuk item: ' . optional($item->item)->name);
            }

            $costSmall = $stockFrom->last_cost_small ?? 0;
            $costMedium = $stockFrom->last_cost_medium ?? 0;
            $costLarge = $stockFrom->last_cost_large ?? 0;

            DB::table('asset_inventory_stocks')
                ->where('id', $stockFrom->id)
                ->update([
                    'qty_small' => $stockFrom->qty_small - $qty_small,
                    'qty_medium' => $stockFrom->qty_medium - $qty_medium,
                    'qty_large' => $stockFrom->qty_large - $qty_large,
                    'value' => ($stockFrom->qty_small - $qty_small) * $costSmall,
                    'updated_at' => now(),
                ]);

            // --- DESTINATION: add stock (upsert) ---
            $stockTo = AssetInventoryStockService::findStock(
                $inventory_item_id,
                $ownerOutletId,
                $transfer->warehouse_outlet_to_id
            );

            if (!$stockTo) {
                DB::table('asset_inventory_stocks')->insert([
                    'inventory_item_id' => $inventory_item_id,
                    'owner_outlet_id' => $ownerOutletId,
                    'outlet_id' => $locationToId,
                    'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
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
                $stockTo = (object) [
                    'qty_small' => 0,
                    'qty_medium' => 0,
                    'qty_large' => 0,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                ];
            } else {
                DB::table('asset_inventory_stocks')
                    ->where('id', $stockTo->id)
                    ->update([
                        'qty_small' => $stockTo->qty_small + $qty_small,
                        'qty_medium' => $stockTo->qty_medium + $qty_medium,
                        'qty_large' => $stockTo->qty_large + $qty_large,
                        'updated_at' => now(),
                    ]);
            }

            // MAC at destination
            $qty_lama = $stockTo->qty_small;
            $nilai_lama = $stockTo->qty_small * $stockTo->last_cost_small;
            $qty_baru = $qty_small;
            $nilai_baru = $qty_small * $costSmall;
            $total_qty = $qty_lama + $qty_baru;
            $total_nilai = $nilai_lama + $nilai_baru;
            $mac = $total_qty > 0 ? $total_nilai / $total_qty : $costSmall;

            $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
            $smallConv = $itemMaster->small_conversion_qty ?: 1;
            $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

            $destStockQuery = DB::table('asset_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id);
            AssetInventoryStockService::applyOwnerWarehouseScope(
                $destStockQuery,
                $ownerOutletId,
                $transfer->warehouse_outlet_to_id
            );
            $destStockQuery->update([
                'last_cost_small' => $mac,
                'last_cost_medium' => $mac * $mediumConv,
                'last_cost_large' => $mac * $smallConv,
                'value' => ($stockTo->qty_small + $qty_small) * $mac,
            ]);

            // Stock card OUT (source)
            DB::table('asset_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationFromId,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_from_id,
                'date' => $transfer->transfer_date,
                'reference_type' => 'asset_inventory_transfer',
                'reference_id' => $transfer->id,
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
                'saldo_qty_small' => $stockFrom->qty_small - $qty_small,
                'saldo_qty_medium' => $stockFrom->qty_medium - $qty_medium,
                'saldo_qty_large' => $stockFrom->qty_large - $qty_large,
                'saldo_value' => ($stockFrom->qty_small - $qty_small) * $costSmall,
                'description' => 'Stock Out - Asset Inventory Transfer',
                'created_at' => now(),
            ]);

            // Stock card IN (destination)
            DB::table('asset_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationToId,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
                'date' => $transfer->transfer_date,
                'reference_type' => 'asset_inventory_transfer',
                'reference_id' => $transfer->id,
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
                'saldo_qty_small' => $stockTo->qty_small + $qty_small,
                'saldo_qty_medium' => $stockTo->qty_medium + $qty_medium,
                'saldo_qty_large' => $stockTo->qty_large + $qty_large,
                'saldo_value' => ($stockTo->qty_small + $qty_small) * $mac,
                'description' => 'Stock In - Asset Inventory Transfer',
                'created_at' => now(),
            ]);

            // Cost history at destination
            $lastCostQuery = DB::table('asset_inventory_cost_histories')
                ->where('inventory_item_id', $inventory_item_id);
            AssetInventoryStockService::applyOwnerWarehouseScope(
                $lastCostQuery,
                $ownerOutletId,
                $transfer->warehouse_outlet_to_id
            );
            $lastCostHistory = $lastCostQuery
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();

            $newCostMedium = $mac * $mediumConv;
            $newCostLarge = $mac * $smallConv;

            DB::table('asset_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventory_item_id,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationToId,
                'warehouse_outlet_id' => $transfer->warehouse_outlet_to_id,
                'date' => $transfer->transfer_date,
                'reference_type' => 'asset_inventory_transfer',
                'reference_id' => $transfer->id,
                'old_cost_small' => $lastCostHistory ? $lastCostHistory->new_cost_small : 0,
                'old_cost_medium' => $lastCostHistory ? $lastCostHistory->new_cost_medium : 0,
                'old_cost_large' => $lastCostHistory ? $lastCostHistory->new_cost_large : 0,
                'new_cost_small' => $mac,
                'new_cost_medium' => $newCostMedium,
                'new_cost_large' => $newCostLarge,
                'qty' => $qty_small,
                'value' => $qty_small * $costSmall,
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

        $query = DB::table('asset_inventory_transfers as t')
            ->join('asset_inventory_transfer_approval_flows as af', 't.id', '=', 'af.asset_inventory_transfer_id')
            ->leftJoin('warehouse_outlets as wf', 't.from_warehouse_outlet_id', '=', 'wf.id')
            ->leftJoin('warehouse_outlets as wt', 't.to_warehouse_outlet_id', '=', 'wt.id')
            ->join('users as creator', 't.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('t.status', 'submitted');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                't.id', 't.transfer_number as number', 't.transfer_date as date', 't.status', 't.notes',
                'wf.name as from_warehouse', 'wt.name as to_warehouse',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) return true;
                $lowerPending = DB::table('asset_inventory_transfer_approval_flows')
                    ->where('asset_inventory_transfer_id', $header->id)
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
        $transfer = DB::table('asset_inventory_transfers as t')
            ->leftJoin('warehouse_outlets as wf', 't.from_warehouse_outlet_id', '=', 'wf.id')
            ->leftJoin('warehouse_outlets as wt', 't.to_warehouse_outlet_id', '=', 'wt.id')
            ->join('users as creator', 't.created_by', '=', 'creator.id')
            ->where('t.id', $id)
            ->select('t.*', 'wf.name as from_warehouse', 'wt.name as to_warehouse', 'creator.nama_lengkap as creator_name')
            ->first();

        if (!$transfer) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = DB::table('asset_inventory_transfer_items as ti')
            ->join('items as i', 'ti.item_id', '=', 'i.id')
            ->where('ti.asset_inventory_transfer_id', $id)
            ->select('ti.*', 'i.name as item_name')
            ->get();

        $approvalFlows = DB::table('asset_inventory_transfer_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.asset_inventory_transfer_id', $id)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name', 'j.nama_jabatan as approver_jabatan')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $transfer,
            'items' => $items,
            'approval_flows' => $approvalFlows,
        ]);
    }

    private function sendNotificationToNextApprover($transferId)
    {
        try {
            $nextApprover = DB::table('asset_inventory_transfer_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.asset_inventory_transfer_id', $transferId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap')
                ->first();

            if (!$nextApprover) return;

            $transfer = DB::table('asset_inventory_transfers as t')
                ->leftJoin('warehouse_outlets as wf', 't.warehouse_outlet_from_id', '=', 'wf.id')
                ->join('users as creator', 't.created_by', '=', 'creator.id')
                ->where('t.id', $transferId)
                ->select('t.*', 'wf.name as from_warehouse', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$transfer) return;

            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'asset_inventory_transfer_approval',
                'title' => 'Approval Asset Transfer',
                'message' => "Asset Transfer {$transfer->transfer_number} dari {$transfer->from_warehouse} oleh {$transfer->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending Asset Transfer notification: ' . $e->getMessage());
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────

    private function persistTransferApprovers(AssetInventoryTransfer $transfer, array $approverIds, bool $submit = false): void
    {
        $transfer->approvalFlows()->delete();

        foreach ($approverIds as $index => $approverId) {
            AssetInventoryTransferApprovalFlow::create([
                'asset_inventory_transfer_id' => $transfer->id,
                'approver_id' => $approverId,
                'approval_level' => $index + 1,
                'status' => 'PENDING',
            ]);
        }

        if ($submit) {
            $transfer->update([
                'status' => 'submitted',
                'approval_by' => null,
                'approval_at' => null,
                'approval_notes' => null,
            ]);
            $this->sendNotificationToNextApprover($transfer->id);
        }
    }

    private function assertUserCanView($user, AssetInventoryTransfer $transfer): void
    {
        if ((int) ($user->id_outlet ?? 0) === 1) {
            return;
        }

        $uid = (int) ($user->id_outlet ?? 0);
        if ($uid <= 0) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        if ((int) $transfer->owner_outlet_id !== $uid) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
