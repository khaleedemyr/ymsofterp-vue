<?php

namespace App\Http\Controllers;

use App\Support\AssetOwnership;

use App\Models\AssetOwnerTransfer;
use App\Models\AssetOwnerTransferApprovalFlow;
use App\Models\AssetOwnerTransferItem;
use App\Services\AssetInventoryStockService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetOwnerTransferController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_owner_transfers as t')
            ->leftJoin('tbl_data_outlet as of', 't.owner_outlet_from_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 't.owner_outlet_to_id', '=', 'ot.id_outlet')
            ->leftJoin('tbl_data_outlet as ol', 't.outlet_id', '=', 'ol.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 't.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->select(
                't.id', 't.transfer_number', 't.transfer_date', 't.status',
                't.notes', 't.created_at',
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_from_id', 'of.nama_outlet') . ' as owner_from_name'),
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_to_id', 'ot.nama_outlet') . ' as owner_to_name'),
                'ol.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $uid = (int) $user->id_outlet;
            $query->where(function ($q) use ($uid) {
                $q->where('t.owner_outlet_from_id', $uid)
                    ->orWhere('t.owner_outlet_to_id', $uid);
            });
        }

        if ($request->search) {
            $query->where('t.transfer_number', 'like', '%' . $request->search . '%');
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
            $query->where(function ($q) use ($request) {
                $q->where('t.owner_outlet_from_id', $request->owner_outlet_id)
                    ->orWhere('t.owner_outlet_to_id', $request->owner_outlet_id);
            });
        }

        $transfers = $query->orderByDesc('t.created_at')->paginate(15)->withQueryString();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        return inertia('AssetOwnerTransfer/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'owner_outlet_id']),
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return inertia('AssetOwnerTransfer/Create', [
            'user' => $user,
            'outlets' => AssetOwnership::options(),
            'locationOutlets' => AssetOwnership::locationOptions(),
            'warehouseOutlets' => DB::table('warehouse_outlets')->select('id', 'name', 'outlet_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'owner_outlet_from_id' => ['required', 'integer', AssetOwnership::ownerIdRule()],
            'owner_outlet_to_id' => ['required', 'integer', 'different:owner_outlet_from_id', AssetOwnership::ownerIdRule()],
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer',
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
            AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                (int) $validated['warehouse_outlet_id'],
                (int) $validated['outlet_id']
            );
            $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
                (int) $validated['outlet_id'],
                (int) $validated['warehouse_outlet_id']
            );

            $transfer = AssetOwnerTransfer::create([
                'transfer_number' => AssetOwnerTransfer::generateNumber(),
                'transfer_date' => $validated['transfer_date'],
                'owner_outlet_from_id' => $validated['owner_outlet_from_id'],
                'owner_outlet_to_id' => $validated['owner_outlet_to_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'outlet_id' => $locationOutletId,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $converted = $this->convertItemQty($itemData['item_id'], $itemData['unit_id'] ?? null, $itemData['qty']);
                AssetOwnerTransferItem::create([
                    'asset_owner_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $converted['unit_id'],
                    'qty' => $itemData['qty'],
                    'qty_small' => $converted['qty_small'],
                    'qty_medium' => $converted['qty_medium'],
                    'qty_large' => $converted['qty_large'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            if (!empty($validated['approvers'])) {
                foreach ($validated['approvers'] as $index => $approverId) {
                    AssetOwnerTransferApprovalFlow::create([
                        'asset_owner_transfer_id' => $transfer->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('asset-owner-transfers.show', $transfer->id)
                ->with('success', 'Transfer kepemilikan berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error store asset owner transfer', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Gagal membuat transfer: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $transfer = AssetOwnerTransfer::with([
            'items.item', 'items.unit',
            'warehouseOutlet',
            'creator', 'approver',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $transfer);

        $ownerFromName = AssetOwnership::name((int) $transfer->owner_outlet_from_id) ?? '-';
        $ownerToName = AssetOwnership::name((int) $transfer->owner_outlet_to_id) ?? '-';
        $location = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->outlet_id)->first();

        $canApprove = false;
        if ($transfer->status === 'submitted') {
            $nextFlow = $transfer->approvalFlows()->where('status', 'PENDING')->orderBy('approval_level')->first();
            if ($nextFlow && ($nextFlow->approver_id == $user->id || ($user->id_role === '5af56935b011a' && $user->status === 'A'))) {
                $canApprove = true;
            }
        }

        return inertia('AssetOwnerTransfer/Show', [
            'transfer' => [
                'id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'transfer_date' => $transfer->transfer_date->format('Y-m-d'),
                'status' => $transfer->status,
                'notes' => $transfer->notes,
                'owner_from_name' => $ownerFromName,
                'owner_to_name' => $ownerToName,
                'location_outlet_name' => $location->nama_outlet ?? '-',
                'warehouse_outlet_name' => optional($transfer->warehouseOutlet)->name,
                'creator_name' => optional($transfer->creator)->nama_lengkap,
                'approval_by_name' => optional($transfer->approver)->nama_lengkap,
                'approval_at' => $transfer->approval_at,
                'approval_notes' => $transfer->approval_notes,
                'items' => $transfer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit_name' => optional($item->unit)->name ?? '-',
                    'qty' => $item->qty,
                    'note' => $item->note,
                ]),
                'approval_flows' => $transfer->approvalFlows->map(function ($flow) {
                    $jabatan = DB::table('tbl_data_jabatan')
                        ->where('id_jabatan', optional($flow->approver)->id_jabatan)
                        ->value('nama_jabatan');

                    return [
                        'approver_name' => optional($flow->approver)->nama_lengkap,
                        'approver_jabatan' => $jabatan ?? '-',
                        'approval_level' => $flow->approval_level,
                        'status' => $flow->status,
                        'approved_at' => $flow->approved_at,
                        'rejected_at' => $flow->rejected_at,
                        'comments' => $flow->comments,
                    ];
                }),
            ],
            'canApprove' => $canApprove,
            'user' => $user,
        ]);
    }

    public function destroy($id)
    {
        $transfer = AssetOwnerTransfer::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $transfer);

        if ($transfer->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya draft yang bisa dihapus.');
        }

        DB::beginTransaction();
        try {
            $transfer->approvalFlows()->delete();
            $transfer->items()->delete();
            $transfer->delete();
            DB::commit();

            return redirect()->route('asset-owner-transfers.index')->with('success', 'Transfer dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function submit(Request $request, $id)
    {
        $transfer = AssetOwnerTransfer::with('approvalFlows')->findOrFail($id);
        $this->assertUserCanView(auth()->user(), $transfer);

        if ($transfer->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya draft yang bisa di-submit'], 422);
        }

        $existingFlows = $transfer->approvalFlows()->orderBy('approval_level')->get();

        DB::beginTransaction();
        try {
            if ($existingFlows->isEmpty()) {
                $validated = $request->validate([
                    'approvers' => 'required|array|min:1',
                    'approvers.*' => 'required|integer|exists:users,id',
                ]);
                foreach ($validated['approvers'] as $index => $approverId) {
                    AssetOwnerTransferApprovalFlow::create([
                        'asset_owner_transfer_id' => $transfer->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }
            $transfer->update(['status' => 'submitted', 'approval_by' => null, 'approval_at' => null, 'approval_notes' => null]);
            DB::commit();
            $this->notifyNextApprover($transfer->id);

            return response()->json(['success' => true, 'message' => 'Transfer kepemilikan di-submit.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $transfer = AssetOwnerTransfer::with(['approvalFlows', 'items'])->findOrFail($id);
        $this->assertUserCanView($user, $transfer);

        // Swal tanpa input bisa mengirim comments=true (boolean) — paksa jadi string.
        if ($request->has('comments') && ! is_string($request->input('comments')) && $request->input('comments') !== null) {
            $request->merge(['comments' => '']);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);
        if ($validated['action'] === 'reject') {
            $request->validate(['comments' => 'required|string']);
        }
        if ($transfer->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Status tidak valid'], 400);
        }

        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        DB::beginTransaction();
        try {
            $nextFlow = $transfer->approvalFlows()->where('status', 'PENDING')->orderBy('approval_level')->first();
            if (!$nextFlow) {
                return response()->json(['success' => false, 'message' => 'Tidak ada approval pending'], 400);
            }
            if (!$isSuperadmin && $nextFlow->approver_id != $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($validated['action'] === 'approve') {
                $nextFlow->approve($validated['comments'] ?? null);
                if ($transfer->approvalFlows()->where('status', 'PENDING')->count() === 0) {
                    $transfer->update([
                        'status' => 'approved',
                        'approval_by' => $user->id,
                        'approval_at' => now(),
                        'approval_notes' => $validated['comments'] ?? null,
                    ]);
                    $this->processOwnerTransfer($transfer->fresh()->load('items'));
                } else {
                    $this->notifyNextApprover($transfer->id);
                }
            } else {
                $nextFlow->reject($validated['comments'] ?? null);
                $transfer->update(['status' => 'rejected', 'approval_by' => $user->id, 'approval_at' => now(), 'approval_notes' => $validated['comments']]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => $validated['action'] === 'approve' ? 'Disetujui.' : 'Ditolak.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve asset owner transfer', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApprovers(Request $request)
    {
        $search = $request->get('search', '');
        $usersQuery = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->where('users.status', 'A');

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%")
                    ->orWhere('tbl_data_outlet.nama_outlet', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->select(
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan',
                'tbl_data_outlet.nama_outlet as outlet'
            )
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'users' => $users]);
    }

    // ─── API (approval-app / mobile) ─────────────────────────────────

    public function apiIndex(Request $request)
    {
        $user = Auth::user();

        $query = DB::table('asset_owner_transfers as t')
            ->leftJoin('tbl_data_outlet as of', 't.owner_outlet_from_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 't.owner_outlet_to_id', '=', 'ot.id_outlet')
            ->leftJoin('tbl_data_outlet as ol', 't.outlet_id', '=', 'ol.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 't.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 't.created_by', '=', 'u.id')
            ->select(
                't.id', 't.transfer_number', 't.transfer_date', 't.status',
                't.notes', 't.created_at',
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_from_id', 'of.nama_outlet') . ' as owner_from_name'),
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_to_id', 'ot.nama_outlet') . ' as owner_to_name'),
                'ol.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ((int) ($user->id_outlet ?? 0) !== 1) {
            $uid = (int) $user->id_outlet;
            $query->where(function ($q) use ($uid) {
                $q->where('t.owner_outlet_from_id', $uid)
                    ->orWhere('t.owner_outlet_to_id', $uid);
            });
        }

        if ($request->search) {
            $query->where('t.transfer_number', 'like', '%' . $request->search . '%');
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
        if ($request->owner_outlet_id) {
            $oid = (int) $request->owner_outlet_id;
            $query->where(function ($q) use ($oid) {
                $q->where('t.owner_outlet_from_id', $oid)
                    ->orWhere('t.owner_outlet_to_id', $oid);
            });
        }

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        return response()->json(
            $query->orderByDesc('t.created_at')->paginate($perPage, ['*'], 'page', $page)
        );
    }

    public function apiCreateData(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'outlets' => AssetOwnership::options(),
            'locationOutlets' => AssetOwnership::locationOptions(),
            'warehouseOutlets' => DB::table('warehouse_outlets')->select('id', 'name', 'outlet_id')->orderBy('name')->get(),
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
        $transfer = AssetOwnerTransfer::with([
            'items.item', 'items.unit',
            'warehouseOutlet',
            'creator', 'approver',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $transfer);

        $ownerFromName = AssetOwnership::name((int) $transfer->owner_outlet_from_id) ?? '-';
        $ownerToName = AssetOwnership::name((int) $transfer->owner_outlet_to_id) ?? '-';
        $location = DB::table('tbl_data_outlet')->where('id_outlet', $transfer->outlet_id)->first();

        $canApprove = false;
        if ($transfer->status === 'submitted') {
            $nextFlow = $transfer->approvalFlows()->where('status', 'PENDING')->orderBy('approval_level')->first();
            if ($nextFlow && ($nextFlow->approver_id == $user->id || ($user->id_role === '5af56935b011a' && $user->status === 'A'))) {
                $canApprove = true;
            }
        }

        return response()->json([
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date->format('Y-m-d'),
            'status' => $transfer->status,
            'notes' => $transfer->notes,
            'owner_outlet_from_id' => $transfer->owner_outlet_from_id,
            'owner_outlet_to_id' => $transfer->owner_outlet_to_id,
            'owner_from_name' => $ownerFromName,
            'owner_to_name' => $ownerToName,
            'location_outlet_name' => $location->nama_outlet ?? '-',
            'warehouse_outlet_name' => optional($transfer->warehouseOutlet)->name,
            'creator_name' => optional($transfer->creator)->nama_lengkap,
            'approval_by_name' => optional($transfer->approver)->nama_lengkap,
            'approval_at' => $transfer->approval_at,
            'approval_notes' => $transfer->approval_notes,
            'can_approve' => $canApprove,
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'item_name' => optional($item->item)->name ?? '-',
                'unit_id' => $item->unit_id,
                'unit_name' => optional($item->unit)->name ?? '-',
                'qty' => $item->qty,
                'note' => $item->note,
            ]),
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
            'transfer_date' => 'required|date',
            'owner_outlet_from_id' => ['required', 'integer', AssetOwnership::ownerIdRule()],
            'owner_outlet_to_id' => ['required', 'integer', 'different:owner_outlet_from_id', AssetOwnership::ownerIdRule()],
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'warehouse_outlet_id' => 'required|integer',
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
            AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                (int) $validated['warehouse_outlet_id'],
                (int) $validated['outlet_id']
            );
            $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
                (int) $validated['outlet_id'],
                (int) $validated['warehouse_outlet_id']
            );

            $transfer = AssetOwnerTransfer::create([
                'transfer_number' => AssetOwnerTransfer::generateNumber(),
                'transfer_date' => $validated['transfer_date'],
                'owner_outlet_from_id' => $validated['owner_outlet_from_id'],
                'owner_outlet_to_id' => $validated['owner_outlet_to_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'outlet_id' => $locationOutletId,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $converted = $this->convertItemQty($itemData['item_id'], $itemData['unit_id'] ?? null, $itemData['qty']);
                AssetOwnerTransferItem::create([
                    'asset_owner_transfer_id' => $transfer->id,
                    'item_id' => $itemData['item_id'],
                    'unit_id' => $converted['unit_id'],
                    'qty' => $itemData['qty'],
                    'qty_small' => $converted['qty_small'],
                    'qty_medium' => $converted['qty_medium'],
                    'qty_large' => $converted['qty_large'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            if (!empty($validated['approvers'])) {
                foreach ($validated['approvers'] as $index => $approverId) {
                    AssetOwnerTransferApprovalFlow::create([
                        'asset_owner_transfer_id' => $transfer->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer kepemilikan berhasil dibuat.',
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error apiStore asset owner transfer', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $transfer = AssetOwnerTransfer::findOrFail($id);
        $this->assertUserCanView(Auth::user(), $transfer);

        if ($transfer->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya draft yang bisa dihapus.'], 422);
        }

        DB::beginTransaction();
        try {
            $transfer->approvalFlows()->delete();
            $transfer->items()->delete();
            $transfer->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Transfer dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApprovalDetails($id)
    {
        $transfer = DB::table('asset_owner_transfers as t')
            ->leftJoin('tbl_data_outlet as of', 't.owner_outlet_from_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 't.owner_outlet_to_id', '=', 'ot.id_outlet')
            ->leftJoin('tbl_data_outlet as ol', 't.outlet_id', '=', 'ol.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 't.warehouse_outlet_id', '=', 'wo.id')
            ->join('users as creator', 't.created_by', '=', 'creator.id')
            ->where('t.id', $id)
            ->select(
                't.*',
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_from_id', 'of.nama_outlet') . ' as owner_from_name'),
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_to_id', 'ot.nama_outlet') . ' as owner_to_name'),
                'ol.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                'creator.nama_lengkap as creator_name'
            )
            ->first();

        if (!$transfer) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = DB::table('asset_owner_transfer_items as ti')
            ->join('items as i', 'ti.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'ti.unit_id', '=', 'u.id')
            ->where('ti.asset_owner_transfer_id', $id)
            ->select('ti.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();

        $approvalFlows = DB::table('asset_owner_transfer_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.asset_owner_transfer_id', $id)
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

    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'headers' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = DB::table('asset_owner_transfers as t')
            ->join('asset_owner_transfer_approval_flows as af', 't.id', '=', 'af.asset_owner_transfer_id')
            ->leftJoin('tbl_data_outlet as of', 't.owner_outlet_from_id', '=', 'of.id_outlet')
            ->leftJoin('tbl_data_outlet as ot', 't.owner_outlet_to_id', '=', 'ot.id_outlet')
            ->join('users as creator', 't.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('t.status', 'submitted');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                't.id',
                't.transfer_number as number',
                't.transfer_date as date',
                't.status',
                't.notes',
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_from_id', 'of.nama_outlet') . ' as owner_from_name'),
                DB::raw(AssetOwnership::ownerNameSql('t.owner_outlet_to_id', 'ot.nama_outlet') . ' as owner_to_name'),
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) {
                    return true;
                }
                $lowerPending = DB::table('asset_owner_transfer_approval_flows')
                    ->where('asset_owner_transfer_id', $header->id)
                    ->where('approval_level', '<', $header->approval_level)
                    ->where('status', 'PENDING')
                    ->count();

                return $lowerPending === 0;
            })
            ->unique('id')
            ->values();

        return response()->json(['success' => true, 'headers' => $pendingHeaders]);
    }

    private function processOwnerTransfer(AssetOwnerTransfer $transfer): void
    {
        $ownerFrom = (int) $transfer->owner_outlet_from_id;
        $ownerTo = (int) $transfer->owner_outlet_to_id;
        $warehouseId = (int) $transfer->warehouse_outlet_id;
        $locationOutletId = (int) $transfer->outlet_id;

        foreach ($transfer->items as $item) {
            $inventoryItem = DB::table('asset_inventory_items')->where('item_id', $item->item_id)->first();
            if (!$inventoryItem) {
                continue;
            }
            $inventoryItemId = (int) $inventoryItem->id;
            $qtySmall = (float) ($item->qty_small ?? 0);
            $qtyMedium = (float) ($item->qty_medium ?? 0);
            $qtyLarge = (float) ($item->qty_large ?? 0);

            $stockFrom = AssetInventoryStockService::findStock($inventoryItemId, $ownerFrom, $warehouseId);
            if (!$stockFrom) {
                throw new \Exception('Stok pemilik asal tidak ditemukan untuk item: ' . optional($item->item)->name);
            }

            $costSmall = $stockFrom->last_cost_small ?? 0;
            $costMedium = $stockFrom->last_cost_medium ?? 0;
            $costLarge = $stockFrom->last_cost_large ?? 0;

            DB::table('asset_inventory_stocks')->where('id', $stockFrom->id)->update([
                'qty_small' => $stockFrom->qty_small - $qtySmall,
                'qty_medium' => $stockFrom->qty_medium - $qtyMedium,
                'qty_large' => $stockFrom->qty_large - $qtyLarge,
                'value' => ($stockFrom->qty_small - $qtySmall) * $costSmall,
                'updated_at' => now(),
            ]);

            $stockTo = AssetInventoryStockService::findStock($inventoryItemId, $ownerTo, $warehouseId);
            if (!$stockTo) {
                DB::table('asset_inventory_stocks')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'owner_outlet_id' => $ownerTo,
                    'outlet_id' => $locationOutletId,
                    'warehouse_outlet_id' => $warehouseId,
                    'qty_small' => $qtySmall,
                    'qty_medium' => $qtyMedium,
                    'qty_large' => $qtyLarge,
                    'value' => $qtySmall * $costSmall,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $saldoToSmall = $qtySmall;
            } else {
                DB::table('asset_inventory_stocks')->where('id', $stockTo->id)->update([
                    'qty_small' => $stockTo->qty_small + $qtySmall,
                    'qty_medium' => $stockTo->qty_medium + $qtyMedium,
                    'qty_large' => $stockTo->qty_large + $qtyLarge,
                    'value' => ($stockTo->qty_small + $qtySmall) * $costSmall,
                    'updated_at' => now(),
                ]);
                $saldoToSmall = $stockTo->qty_small + $qtySmall;
            }

            $saldoFromSmall = $stockFrom->qty_small - $qtySmall;

            DB::table('asset_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerFrom,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseId,
                'date' => $transfer->transfer_date,
                'reference_type' => 'asset_owner_transfer',
                'reference_id' => $transfer->id,
                'out_qty_small' => $qtySmall,
                'out_qty_medium' => $qtyMedium,
                'out_qty_large' => $qtyLarge,
                'in_qty_small' => 0,
                'in_qty_medium' => 0,
                'in_qty_large' => 0,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_out' => $qtySmall * $costSmall,
                'value_in' => 0,
                'saldo_qty_small' => $saldoFromSmall,
                'saldo_qty_medium' => $stockFrom->qty_medium - $qtyMedium,
                'saldo_qty_large' => $stockFrom->qty_large - $qtyLarge,
                'saldo_value' => $saldoFromSmall * $costSmall,
                'description' => 'Transfer kepemilikan — keluar (pemilik asal)',
                'created_at' => now(),
            ]);

            DB::table('asset_inventory_cards')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerTo,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseId,
                'date' => $transfer->transfer_date,
                'reference_type' => 'asset_owner_transfer',
                'reference_id' => $transfer->id,
                'in_qty_small' => $qtySmall,
                'in_qty_medium' => $qtyMedium,
                'in_qty_large' => $qtyLarge,
                'out_qty_small' => 0,
                'out_qty_medium' => 0,
                'out_qty_large' => 0,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => $qtySmall * $costSmall,
                'value_out' => 0,
                'saldo_qty_small' => $saldoToSmall,
                'saldo_qty_medium' => ($stockTo->qty_medium ?? 0) + $qtyMedium,
                'saldo_qty_large' => ($stockTo->qty_large ?? 0) + $qtyLarge,
                'saldo_value' => $saldoToSmall * $costSmall,
                'description' => 'Transfer kepemilikan — masuk (pemilik tujuan)',
                'created_at' => now(),
            ]);
        }
    }

    private function convertItemQty(int $itemId, ?int $unitId, $qty): array
    {
        $itemMaster = DB::table('items')->where('id', $itemId)->first();
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
        $unitId = $unitId ?? $itemMaster->small_unit_id;

        if ($unitId == $itemMaster->small_unit_id) {
            $qtySmall = $qty;
            $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
            $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
        } elseif ($unitId == $itemMaster->medium_unit_id) {
            $qtyMedium = $qty;
            $qtySmall = $qty * $mediumConv;
            $qtyLarge = $smallConv > 0 ? ($qty * $mediumConv) / $smallConv : 0;
        } elseif ($unitId == $itemMaster->large_unit_id) {
            $qtyLarge = $qty;
            $qtySmall = $qty * $smallConv;
            $qtyMedium = $mediumConv > 0 ? ($qty * $smallConv) / $mediumConv : 0;
        } else {
            $qtySmall = $qty;
            $qtyMedium = $mediumConv > 0 ? $qty / $mediumConv : 0;
            $qtyLarge = $smallConv > 0 ? $qty / $smallConv : 0;
        }

        return [
            'unit_id' => $unitId,
            'qty_small' => $qtySmall,
            'qty_medium' => $qtyMedium,
            'qty_large' => $qtyLarge,
        ];
    }

    private function assertUserCanView($user, AssetOwnerTransfer $transfer): void
    {
        if ((int) ($user->id_outlet ?? 0) === 1) {
            return;
        }
        $uid = (int) ($user->id_outlet ?? 0);
        if ($uid !== (int) $transfer->owner_outlet_from_id && $uid !== (int) $transfer->owner_outlet_to_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }

    private function notifyNextApprover(int $transferId): void
    {
        try {
            $next = DB::table('asset_owner_transfer_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.asset_owner_transfer_id', $transferId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id')
                ->first();
            if (!$next) {
                return;
            }
            $t = DB::table('asset_owner_transfers')->where('id', $transferId)->first();
            NotificationService::insert([
                'user_id' => $next->id,
                'type' => 'asset_owner_transfer_approval',
                'title' => 'Approval Transfer Kepemilikan',
                'message' => "Transfer kepemilikan {$t->transfer_number} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Notify asset owner transfer: ' . $e->getMessage());
        }
    }
}
