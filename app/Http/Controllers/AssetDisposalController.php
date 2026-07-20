<?php

namespace App\Http\Controllers;

use App\Support\AssetOwnership;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalItem;
use App\Models\AssetDisposalPhoto;
use App\Models\AssetDisposalApprovalFlow;
use App\Services\NotificationService;
use App\Services\AssetInventoryStockService;

class AssetDisposalController extends Controller
{
    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_disposals as d')
            ->leftJoin('tbl_data_outlet as oo', 'd.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 'd.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'd.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'd.created_by', '=', 'u.id')
            ->select(
                'd.id', 'd.number', 'd.date', 'd.type', 'd.description',
                'd.buyer_name', 'd.total_sale_price',
                'd.status', 'd.created_by', 'd.created_at',
                DB::raw(AssetOwnership::ownerNameSql('d.owner_outlet_id', 'oo.nama_outlet') . ' as owner_outlet_name'),
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('d.owner_outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('d.number', 'like', "%{$search}%")
                  ->orWhere('d.description', 'like', "%{$search}%")
                  ->orWhere('d.buyer_name', 'like', "%{$search}%");
            });
        }
        if ($request->from) {
            $query->whereDate('d.date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('d.date', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('d.status', $request->status);
        }
        if ($request->type) {
            $query->where('d.type', $request->type);
        }
        if ($request->outlet_id) {
            $query->where('d.id_outlet', $request->outlet_id);
        }

        $disposals = $query->orderByDesc('d.created_at')->paginate(15)->withQueryString();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        return inertia('AssetDisposal/Index', [
            'disposals' => $disposals,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'type', 'outlet_id']),
            'user' => $user,
            'outlets' => $outlets,
            'locationOutlets' => $locationOutlets ?? AssetOwnership::locationOptions(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return inertia('AssetDisposal/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'locationOutlets' => $locationOutlets ?? AssetOwnership::locationOptions(),
            'warehouseOutlets' => $warehouseOutlets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_outlet_id' => ['required', 'integer', AssetOwnership::ownerIdRule()],
            'date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'type' => 'required|in:discard,sold',
            'description' => 'required|string',
            'buyer_name' => 'nullable|string|max:255',
            'buyer_contact' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
            'photo_paths' => 'nullable|array',
            'photo_paths.*' => 'required|string',
            'approvers' => 'required|array|min:1',
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

            $number = AssetDisposal::generateNumber();

            $totalSalePrice = 0;
            if ($validated['type'] === 'sold') {
                foreach ($validated['items'] as $item) {
                    $totalSalePrice += ($item['sale_price'] ?? 0) * $item['qty'];
                }
            }

            $disposal = AssetDisposal::create([
                'number' => $number,
                'date' => $validated['date'],
                'owner_outlet_id' => $validated['owner_outlet_id'],
                'id_outlet' => $locationOutletId,
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'buyer_name' => $validated['type'] === 'sold' ? ($validated['buyer_name'] ?? null) : null,
                'buyer_contact' => $validated['type'] === 'sold' ? ($validated['buyer_contact'] ?? null) : null,
                'total_sale_price' => $totalSalePrice,
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                AssetDisposalItem::create([
                    'disposal_id' => $disposal->id,
                    'item_id' => $itemData['item_id'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['selected_unit'],
                    'sale_price' => $validated['type'] === 'sold' ? ($itemData['sale_price'] ?? 0) : 0,
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            if (!empty($validated['photo_paths'])) {
                foreach ($validated['photo_paths'] as $path) {
                    AssetDisposalPhoto::create([
                        'disposal_id' => $disposal->id,
                        'photo_path' => $path,
                        'created_at' => now(),
                    ]);
                }
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetDisposalApprovalFlow::create([
                    'disposal_id' => $disposal->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'asset_disposal',
                'description' => 'Create asset disposal: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($disposal->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->sendNotificationToNextApprover($disposal->id);

            return redirect()->route('asset-disposals.show', $disposal->id)
                ->with('success', 'Disposal berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error store asset disposal', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal membuat disposal: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $disposal = AssetDisposal::with([
            'items.item',
            'photos',
            'outlet',
            'warehouseOutlet',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $disposal);

        $canApprove = false;
        if ($disposal->status === 'waiting_approval') {
            $nextFlow = $disposal->approvalFlows()
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

        $disposalData = [
            'id' => $disposal->id,
            'number' => $disposal->number,
            'date' => $disposal->date->format('Y-m-d'),
            'type' => $disposal->type,
            'description' => $disposal->description,
            'buyer_name' => $disposal->buyer_name,
            'buyer_contact' => $disposal->buyer_contact,
            'total_sale_price' => (float) $disposal->total_sale_price,
            'status' => $disposal->status,
            'owner_outlet_name' => AssetOwnership::name((int) $disposal->owner_outlet_id) ?? '-',
            'outlet_name' => optional($disposal->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($disposal->warehouseOutlet)->name,
            'creator_name' => optional($disposal->creator)->nama_lengkap,
            'photos' => $disposal->photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'path' => $photo->photo_path,
                    'url' => asset('storage/' . $photo->photo_path),
                    'caption' => $photo->caption,
                ];
            }),
            'items' => $disposal->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty' => $item->qty,
                    'sale_price' => (float) $item->sale_price,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $disposal->approvalFlows->map(function ($flow) {
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

        return inertia('AssetDisposal/Show', [
            'disposal' => $disposalData,
            'canApprove' => $canApprove,
            'user' => $user,
        ]);
    }

    public function destroy($id)
    {
        $disposal = AssetDisposal::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $disposal);

        if ($disposal->status !== 'waiting_approval') {
            return redirect()->back()->with('error', 'Hanya disposal waiting approval yang bisa dihapus.');
        }

        DB::beginTransaction();
        try {
            foreach ($disposal->photos as $photo) {
                Storage::disk('public')->delete($photo->photo_path);
            }
            $disposal->photos()->delete();
            $disposal->approvalFlows()->delete();
            $disposal->items()->delete();
            $disposal->delete();
            DB::commit();
            return redirect()->route('asset-disposals.index')
                ->with('success', 'Disposal berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    // ─── PHOTO HANDLING ──────────────────────────────────────────────

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $request->file('photo')->store('asset-disposals', 'public');

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }

    public function deletePhoto($id)
    {
        $photo = AssetDisposalPhoto::findOrFail($id);
        $disposal = AssetDisposal::findOrFail($photo->disposal_id);
        $this->assertUserCanView(auth()->user(), $disposal);

        if ($disposal->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Foto tidak bisa dihapus'], 422);
        }

        Storage::disk('public')->delete($photo->photo_path);
        $photo->delete();

        return response()->json(['success' => true, 'message' => 'Foto berhasil dihapus']);
    }

    // ─── APPROVAL ────────────────────────────────────────────────────

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $disposal = AssetDisposal::with(['approvalFlows', 'items'])->findOrFail($id);
        $this->assertUserCanView($user, $disposal);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate(['comments' => 'required|string']);
        }

        if ($disposal->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Tidak bisa approve disposal ini'], 400);
        }

        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        DB::beginTransaction();
        try {
            $nextFlow = $disposal->approvalFlows()
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

                $hasPending = $disposal->approvalFlows()->where('status', 'PENDING')->count() > 0;
                if (!$hasPending) {
                    $disposal->update(['status' => 'approved']);
                    $this->processStockOut($disposal->fresh()->load('items'));
                } else {
                    $this->sendNotificationToNextApprover($disposal->id);
                }
            } else {
                $nextFlow->reject($validated['comments'] ?? null);
                $disposal->update(['status' => 'rejected']);
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => $validated['action'],
                'module' => 'asset_disposal',
                'description' => ucfirst($validated['action']) . ' asset disposal: ' . $disposal->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($disposal->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $validated['action'] === 'approve' ? 'Disposal berhasil disetujui.' : 'Disposal ditolak.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approve asset disposal', ['error' => $e->getMessage()]);
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

        $query = DB::table('asset_disposals as d')
            ->leftJoin('tbl_data_outlet as oo', 'd.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 'd.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'd.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'd.created_by', '=', 'u.id')
            ->select(
                'd.id', 'd.number', 'd.date', 'd.type', 'd.description',
                'd.buyer_name', 'd.total_sale_price',
                'd.status', 'd.created_at',
                DB::raw(AssetOwnership::ownerNameSql('d.owner_outlet_id', 'oo.nama_outlet') . ' as owner_outlet_name'),
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('d.owner_outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('d.number', 'like', "%{$request->search}%")
                  ->orWhere('d.description', 'like', "%{$request->search}%")
                  ->orWhere('d.buyer_name', 'like', "%{$request->search}%");
            });
        }
        if ($request->date_from) {
            $query->whereDate('d.date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('d.date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('d.status', $request->status);
        }
        if ($request->type) {
            $query->where('d.type', $request->type);
        }
        if ($request->outlet_id) {
            $query->where('d.id_outlet', $request->outlet_id);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $disposals = $query->orderByDesc('d.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($disposals);
    }

    public function apiCreateData(Request $request)
    {
        $user = Auth::user();

        $outlets = AssetOwnership::options();
        $locationOutlets = AssetOwnership::locationOptions();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'outlets' => $outlets,
            'locationOutlets' => $locationOutlets ?? AssetOwnership::locationOptions(),
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
        $disposal = AssetDisposal::with([
            'items.item',
            'photos',
            'outlet',
            'warehouseOutlet',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $disposal);

        $canApprove = false;
        if ($disposal->status === 'waiting_approval') {
            $nextFlow = $disposal->approvalFlows()
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
            'id' => $disposal->id,
            'number' => $disposal->number,
            'date' => $disposal->date->format('Y-m-d'),
            'type' => $disposal->type,
            'description' => $disposal->description,
            'buyer_name' => $disposal->buyer_name,
            'buyer_contact' => $disposal->buyer_contact,
            'total_sale_price' => (float) $disposal->total_sale_price,
            'status' => $disposal->status,
            'owner_outlet_name' => AssetOwnership::name((int) $disposal->owner_outlet_id) ?? '-',
            'outlet_name' => optional($disposal->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($disposal->warehouseOutlet)->name,
            'creator_name' => optional($disposal->creator)->nama_lengkap,
            'can_approve' => $canApprove,
            'photos' => $disposal->photos->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'path' => $photo->photo_path,
                    'url' => asset('storage/' . $photo->photo_path),
                    'caption' => $photo->caption,
                ];
            }),
            'items' => $disposal->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty' => $item->qty,
                    'sale_price' => (float) $item->sale_price,
                    'note' => $item->note,
                ];
            }),
            'approval_flows' => $disposal->approvalFlows->map(function ($flow) {
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
            'owner_outlet_id' => ['required', 'integer', AssetOwnership::ownerIdRule()],
            'date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'type' => 'required|in:discard,sold',
            'description' => 'required|string',
            'buyer_name' => 'nullable|string|max:255',
            'buyer_contact' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
            'photo_paths' => 'nullable|array',
            'photo_paths.*' => 'required|string',
            'approvers' => 'required|array|min:1',
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

            $number = AssetDisposal::generateNumber();

            $totalSalePrice = 0;
            if ($validated['type'] === 'sold') {
                foreach ($validated['items'] as $item) {
                    $totalSalePrice += ($item['sale_price'] ?? 0) * $item['qty'];
                }
            }

            $disposal = AssetDisposal::create([
                'number' => $number,
                'date' => $validated['date'],
                'owner_outlet_id' => $validated['owner_outlet_id'],
                'id_outlet' => $locationOutletId,
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'buyer_name' => $validated['type'] === 'sold' ? ($validated['buyer_name'] ?? null) : null,
                'buyer_contact' => $validated['type'] === 'sold' ? ($validated['buyer_contact'] ?? null) : null,
                'total_sale_price' => $totalSalePrice,
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                AssetDisposalItem::create([
                    'disposal_id' => $disposal->id,
                    'item_id' => $itemData['item_id'],
                    'qty' => $itemData['qty'],
                    'unit' => $itemData['selected_unit'],
                    'sale_price' => $validated['type'] === 'sold' ? ($itemData['sale_price'] ?? 0) : 0,
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            if (!empty($validated['photo_paths'])) {
                foreach ($validated['photo_paths'] as $path) {
                    AssetDisposalPhoto::create([
                        'disposal_id' => $disposal->id,
                        'photo_path' => $path,
                        'created_at' => now(),
                    ]);
                }
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetDisposalApprovalFlow::create([
                    'disposal_id' => $disposal->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            $this->sendNotificationToNextApprover($disposal->id);

            return response()->json([
                'success' => true,
                'message' => 'Disposal berhasil dibuat.',
                'disposal_id' => $disposal->id,
                'number' => $number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error apiStore asset disposal', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $disposal = AssetDisposal::findOrFail($id);
        $this->assertUserCanView(Auth::user(), $disposal);

        if ($disposal->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Hanya disposal waiting approval yang bisa dihapus.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($disposal->photos as $photo) {
                Storage::disk('public')->delete($photo->photo_path);
            }
            $disposal->photos()->delete();
            $disposal->approvalFlows()->delete();
            $disposal->items()->delete();
            $disposal->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Disposal berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── INVENTORY PROCESSING ────────────────────────────────────────

    private function processStockOut($disposal)
    {
        $warehouseOutlet = DB::table('warehouse_outlets')
            ->where('id', $disposal->warehouse_outlet_id)
            ->first();

        if (!$warehouseOutlet) {
            throw new \Exception('Warehouse outlet tidak ditemukan');
        }

        $ownerOutletId = (int) $disposal->owner_outlet_id;
        $locationOutletId = (int) $disposal->id_outlet;

        foreach ($disposal->items as $dispItem) {
            $itemMaster = DB::table('items')->where('id', $dispItem->item_id)->first();
            if (!$itemMaster) continue;

            $inventoryItem = DB::table('asset_inventory_items')
                ->where('item_id', $dispItem->item_id)
                ->first();

            if (!$inventoryItem) {
                $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                    'item_id' => $dispItem->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = $inventoryItem->id;
            }

            $converted = $this->convertUnits($itemMaster, $dispItem->unit, $dispItem->qty);

            $stock = AssetInventoryStockService::findStock(
                $inventoryItemId,
                $ownerOutletId,
                $disposal->warehouse_outlet_id
            );

            if (!$stock) {
                throw new \Exception('Stok tidak ditemukan untuk item: ' . $itemMaster->name);
            }

            $costSmall = $stock->last_cost_small ?? 0;
            $costMedium = $stock->last_cost_medium ?? 0;
            $costLarge = $stock->last_cost_large ?? 0;

            $saldoSmall = $stock->qty_small - $converted['qty_small'];
            $saldoMedium = $stock->qty_medium - $converted['qty_medium'];
            $saldoLarge = $stock->qty_large - $converted['qty_large'];

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
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $disposal->warehouse_outlet_id,
                'date' => $disposal->date,
                'reference_type' => 'asset_disposal',
                'reference_id' => $disposal->id,
                'in_qty_small' => 0,
                'in_qty_medium' => 0,
                'in_qty_large' => 0,
                'out_qty_small' => $converted['qty_small'],
                'out_qty_medium' => $converted['qty_medium'],
                'out_qty_large' => $converted['qty_large'],
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => 0,
                'value_out' => $converted['qty_small'] * $costSmall,
                'saldo_qty_small' => $saldoSmall,
                'saldo_qty_medium' => $saldoMedium,
                'saldo_qty_large' => $saldoLarge,
                'saldo_value' => $saldoSmall * $costSmall,
                'description' => 'Stock Out - Asset Disposal (' . $disposal->type . ')',
                'created_at' => now(),
            ]);

            $lastCostQuery = DB::table('asset_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId);
            AssetInventoryStockService::applyOwnerWarehouseScope(
                $lastCostQuery,
                $ownerOutletId,
                $disposal->warehouse_outlet_id
            );
            $lastCostHistory = $lastCostQuery
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();

            DB::table('asset_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventoryItemId,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $disposal->warehouse_outlet_id,
                'date' => $disposal->date,
                'reference_type' => 'asset_disposal',
                'reference_id' => $disposal->id,
                'old_cost_small' => $lastCostHistory ? $lastCostHistory->new_cost_small : $costSmall,
                'old_cost_medium' => $lastCostHistory ? $lastCostHistory->new_cost_medium : $costMedium,
                'old_cost_large' => $lastCostHistory ? $lastCostHistory->new_cost_large : $costLarge,
                'new_cost_small' => $costSmall,
                'new_cost_medium' => $costMedium,
                'new_cost_large' => $costLarge,
                'qty' => $converted['qty_small'],
                'value' => $converted['qty_small'] * $costSmall,
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

        $query = DB::table('asset_disposals as d')
            ->join('asset_disposal_approval_flows as af', 'd.id', '=', 'af.disposal_id')
            ->leftJoin('tbl_data_outlet as o', 'd.id_outlet', '=', 'o.id_outlet')
            ->join('users as creator', 'd.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('d.status', 'waiting_approval');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'd.id', 'd.number', 'd.date', 'd.status', 'd.type', 'd.description as notes',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) return true;
                $lowerPending = DB::table('asset_disposal_approval_flows')
                    ->where('disposal_id', $header->id)
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
        $disposal = DB::table('asset_disposals as d')
            ->leftJoin('tbl_data_outlet as o', 'd.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'd.warehouse_outlet_id', '=', 'wo.id')
            ->join('users as creator', 'd.created_by', '=', 'creator.id')
            ->where('d.id', $id)
            ->select('d.*', 'o.nama_outlet as outlet_name', 'wo.name as warehouse_name', 'creator.nama_lengkap as creator_name')
            ->first();

        if (!$disposal) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = DB::table('asset_disposal_items as di')
            ->join('items as i', 'di.item_id', '=', 'i.id')
            ->where('di.disposal_id', $id)
            ->select('di.*', 'i.name as item_name')
            ->get();

        $photos = DB::table('asset_disposal_photos')
            ->where('disposal_id', $id)
            ->get()
            ->map(function ($photo) {
                $photo->url = asset('storage/' . $photo->photo_path);
                return $photo;
            });

        $approvalFlows = DB::table('asset_disposal_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.disposal_id', $id)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name', 'j.nama_jabatan as approver_jabatan')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $disposal,
            'items' => $items,
            'photos' => $photos,
            'approval_flows' => $approvalFlows,
        ]);
    }

    private function sendNotificationToNextApprover($disposalId)
    {
        try {
            $nextApprover = DB::table('asset_disposal_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.disposal_id', $disposalId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap')
                ->first();

            if (!$nextApprover) return;

            $disposal = DB::table('asset_disposals as d')
                ->leftJoin('tbl_data_outlet as o', 'd.id_outlet', '=', 'o.id_outlet')
                ->join('users as creator', 'd.created_by', '=', 'creator.id')
                ->where('d.id', $disposalId)
                ->select('d.*', 'o.nama_outlet', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$disposal) return;

            $typeLabel = $disposal->type === 'sold' ? 'Dijual' : 'Dibuang';

            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'asset_disposal_approval',
                'title' => 'Approval Asset Disposal',
                'message' => "Asset Disposal {$disposal->number} ({$typeLabel}) dari outlet {$disposal->nama_outlet} oleh {$disposal->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending Asset Disposal notification: ' . $e->getMessage());
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────

    private function convertUnits($itemMaster, $selectedUnit, $qty)
    {
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $smallUnitName = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $mediumUnitName = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
        $largeUnitName = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');

        if ($selectedUnit == $mediumUnitName) {
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

        return [
            'qty_small' => $qty_small,
            'qty_medium' => $qty_medium,
            'qty_large' => $qty_large,
        ];
    }

    private function assertUserCanView($user, AssetDisposal $disposal): void
    {
        if ((int) ($user->id_outlet ?? 0) === 1) {
            return;
        }
        if ((int) ($user->id_outlet ?? 0) !== (int) $disposal->owner_outlet_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
