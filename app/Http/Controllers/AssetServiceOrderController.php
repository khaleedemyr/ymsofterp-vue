<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\AssetServiceOrder;
use App\Models\AssetServiceOrderItem;
use App\Models\AssetServiceOrderApprovalFlow;
use App\Services\NotificationService;

class AssetServiceOrderController extends Controller
{
    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_service_orders as s')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('suppliers as sp', 's.supplier_id', '=', 'sp.id')
            ->leftJoin('users as u', 's.created_by', '=', 'u.id')
            ->select($this->assetServiceOrderListSelect());

        if ($user->id_outlet != 1) {
            $query->where('s.outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.number', 'like', "%{$search}%")
                  ->orWhere('s.description', 'like', "%{$search}%")
                  ->orWhere('sp.name', 'like', "%{$search}%");
            });
        }
        if ($request->from) {
            $query->whereDate('s.date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('s.date', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('s.status', $request->status);
        }
        if ($request->outlet_id) {
            $query->where('s.outlet_id', $request->outlet_id);
        }
        if (Schema::hasColumn('asset_service_orders', 'service_type') && $request->filled('service_type')) {
            $query->where('s.service_type', $request->service_type);
        }

        $serviceOrders = $query->orderByDesc('s.created_at')->paginate(15)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('AssetServiceOrder/Index', [
            'serviceOrders' => $serviceOrders,
            'filters' => $request->only(['search', 'from', 'to', 'status', 'outlet_id', 'service_type']),
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

        return inertia('AssetServiceOrder/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
        ]);
    }

    public function store(Request $request)
    {
        $this->mergeDefaultAssetServiceType($request);

        $validated = $request->validate($this->rulesForAssetServiceStore($request));

        DB::beginTransaction();
        try {
            $number = AssetServiceOrder::generateNumber();

            $createPayload = [
                'number' => $number,
                'date' => $validated['date'],
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'supplier_id' => !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
                'description' => $validated['description'],
                'estimated_cost' => $validated['estimated_cost'] ?? 0,
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ];
            if (Schema::hasColumn('asset_service_orders', 'service_type')) {
                $createPayload['service_type'] = $validated['service_type'];
            }

            $order = AssetServiceOrder::create($createPayload);

            foreach ($validated['items'] as $itemData) {
                AssetServiceOrderItem::create([
                    'service_order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'qty_out' => $itemData['qty_out'],
                    'unit' => $itemData['selected_unit'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetServiceOrderApprovalFlow::create([
                    'service_order_id' => $order->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'asset_service_order',
                'description' => 'Create asset service order: ' . $number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($order->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->sendNotificationToNextApprover($order->id);

            return redirect()->route('asset-service-orders.show', $order->id)
                ->with('success', 'Service order berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error store asset service order', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Gagal membuat service order: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $order = AssetServiceOrder::with([
            'items.item',
            'outlet',
            'warehouseOutlet',
            'supplier',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $order);

        $canApprove = false;
        if ($order->status === 'waiting_approval') {
            $nextFlow = $order->approvalFlows()
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

        $canReceiveReturn = in_array($order->status, ['in_service', 'partially_returned']);

        $vendorInvoicePath = Schema::hasColumn('asset_service_orders', 'vendor_invoice_path')
            ? $order->vendor_invoice_path
            : null;

        $linkedNonFoodPayment = null;
        if (Schema::hasColumn('non_food_payments', 'asset_service_order_id')) {
            $linkedNonFoodPayment = DB::table('non_food_payments')
                ->where('asset_service_order_id', $order->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->select('id', 'payment_number', 'status', 'amount')
                ->first();
        }

        $canCreateNonFoodPayment = Schema::hasColumn('non_food_payments', 'asset_service_order_id')
            && in_array($order->status, ['in_service', 'partially_returned', 'returned'], true)
            && !$linkedNonFoodPayment
            && (!Schema::hasColumn('asset_service_orders', 'service_type') || ($order->service_type ?? 'external') === 'external');

        $orderData = [
            'id' => $order->id,
            'number' => $order->number,
            'date' => $order->date->format('Y-m-d'),
            'description' => $order->description,
            'service_type' => Schema::hasColumn('asset_service_orders', 'service_type')
                ? ($order->service_type ?? 'external')
                : 'external',
            'estimated_cost' => $order->estimated_cost,
            'actual_cost' => $order->actual_cost,
            'vendor_invoice_path' => $vendorInvoicePath,
            'status' => $order->status,
            'sent_date' => $order->sent_date ? $order->sent_date->format('Y-m-d') : null,
            'return_date' => $order->return_date ? $order->return_date->format('Y-m-d') : null,
            'outlet_name' => optional($order->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($order->warehouseOutlet)->name,
            'supplier_name' => optional($order->supplier)->name,
            'creator_name' => optional($order->creator)->nama_lengkap,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty_out' => (float) $item->qty_out,
                    'qty_returned' => (float) $item->qty_returned,
                    'note' => $item->note,
                    'return_date' => $item->return_date ? $item->return_date->format('Y-m-d') : null,
                    'return_note' => $item->return_note,
                ];
            }),
            'approval_flows' => $order->approvalFlows->map(function ($flow) {
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

        return inertia('AssetServiceOrder/Show', [
            'serviceOrder' => $orderData,
            'linkedNonFoodPayment' => $linkedNonFoodPayment,
            'canCreateNonFoodPayment' => $canCreateNonFoodPayment,
            'canApprove' => $canApprove,
            'canReceiveReturn' => $canReceiveReturn,
            'user' => $user,
        ]);
    }

    public function uploadVendorInvoice(Request $request, $id)
    {
        $request->validate([
            'invoice' => 'required|file|mimes:pdf,jpg,jpeg,png|max:15360',
        ]);

        $order = AssetServiceOrder::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $order);

        if (!Schema::hasColumn('asset_service_orders', 'vendor_invoice_path')) {
            return response()->json([
                'success' => false,
                'message' => 'Kolom vendor_invoice_path belum ada. Jalankan database/sql/asset_service_invoice_and_nfp_link.sql',
            ], 503);
        }

        if (Schema::hasColumn('asset_service_orders', 'service_type') && ($order->service_type ?? 'external') !== 'external') {
            return response()->json([
                'success' => false,
                'message' => 'Upload invoice vendor hanya untuk service tipe External (vendor luar).',
            ], 422);
        }

        $old = $order->vendor_invoice_path;
        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('invoice')->store('asset-service-invoices', 'public');
        $order->update(['vendor_invoice_path' => $path]);

        return response()->json([
            'success' => true,
            'path'  => $path,
            'url'   => asset('storage/' . $path),
        ]);
    }

    public function destroy($id)
    {
        $order = AssetServiceOrder::findOrFail($id);
        $this->assertUserCanView(auth()->user(), $order);

        if ($order->status !== 'waiting_approval') {
            return redirect()->back()->with('error', 'Hanya service order waiting approval yang bisa dihapus.');
        }

        DB::beginTransaction();
        try {
            $order->approvalFlows()->delete();
            $order->items()->delete();
            $order->delete();
            DB::commit();
            return redirect()->route('asset-service-orders.index')
                ->with('success', 'Service order berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    // ─── APPROVAL ────────────────────────────────────────────────────

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $order = AssetServiceOrder::with(['approvalFlows', 'items'])->findOrFail($id);
        $this->assertUserCanView($user, $order);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string',
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate(['comments' => 'required|string']);
        }

        if ($order->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Tidak bisa approve service order ini'], 400);
        }

        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        DB::beginTransaction();
        try {
            $nextFlow = $order->approvalFlows()
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

                $hasPending = $order->approvalFlows()->where('status', 'PENDING')->count() > 0;
                if (!$hasPending) {
                    $order->update([
                        'status' => 'in_service',
                        'sent_date' => now()->toDateString(),
                    ]);
                    $this->processStockOut($order->fresh()->load('items'));
                } else {
                    $this->sendNotificationToNextApprover($order->id);
                }
            } else {
                $nextFlow->reject($validated['comments'] ?? null);
                $order->update(['status' => 'rejected']);
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => $validated['action'],
                'module' => 'asset_service_order',
                'description' => ucfirst($validated['action']) . ' asset service order: ' . $order->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($order->fresh()->toArray()),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $validated['action'] === 'approve' ? 'Service order berhasil disetujui.' : 'Service order ditolak.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approve asset service order', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── RECEIVE RETURN ──────────────────────────────────────────────

    public function receiveReturn(Request $request, $id)
    {
        $user = Auth::user();
        $order = AssetServiceOrder::with('items')->findOrFail($id);
        $this->assertUserCanView($user, $order);

        if (!in_array($order->status, ['in_service', 'partially_returned'])) {
            return response()->json(['success' => false, 'message' => 'Service order tidak dalam status yang bisa diterima kembali'], 400);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty_returned' => 'required|numeric|min:0.01',
            'items.*.return_note' => 'nullable|string',
            'actual_cost' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $returnItem) {
                $orderItem = $order->items()->where('item_id', $returnItem['item_id'])->first();
                if (!$orderItem) continue;

                $newReturned = (float) $orderItem->qty_returned + (float) $returnItem['qty_returned'];
                if ($newReturned > (float) $orderItem->qty_out) {
                    throw new \Exception('Qty return untuk ' . optional($orderItem->item)->name . ' melebihi qty out');
                }

                $orderItem->update([
                    'qty_returned' => $newReturned,
                    'return_date' => now()->toDateString(),
                    'return_note' => $returnItem['return_note'] ?? $orderItem->return_note,
                ]);
            }

            if (isset($validated['actual_cost'])) {
                $order->update(['actual_cost' => $validated['actual_cost']]);
            }

            $this->processStockIn($order->fresh()->load('items'), $validated['items']);

            $order->refresh();
            $allReturned = $order->items->every(function ($item) {
                return (float) $item->qty_returned >= (float) $item->qty_out;
            });

            if ($allReturned) {
                $order->update([
                    'status' => 'returned',
                    'return_date' => now()->toDateString(),
                ]);
            } else {
                $order->update(['status' => 'partially_returned']);
            }

            DB::table('activity_logs')->insert([
                'user_id' => $user->id,
                'activity_type' => 'receive_return',
                'module' => 'asset_service_order',
                'description' => 'Receive return asset service order: ' . $order->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => json_encode($validated),
                'created_at' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $allReturned ? 'Semua item berhasil diterima kembali.' : 'Item berhasil diterima kembali (partial).',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error receive return asset service order', ['error' => $e->getMessage()]);
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

        $query = DB::table('asset_service_orders as s')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('suppliers as sp', 's.supplier_id', '=', 'sp.id')
            ->leftJoin('users as u', 's.created_by', '=', 'u.id')
            ->select($this->assetServiceOrderListSelect());

        if ($user->id_outlet != 1) {
            $query->where('s.outlet_id', $user->id_outlet);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('s.number', 'like', "%{$request->search}%")
                  ->orWhere('s.description', 'like', "%{$request->search}%")
                  ->orWhere('sp.name', 'like', "%{$request->search}%");
            });
        }
        if ($request->date_from) {
            $query->whereDate('s.date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('s.date', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('s.status', $request->status);
        }
        if (Schema::hasColumn('asset_service_orders', 'service_type') && $request->filled('service_type')) {
            $query->where('s.service_type', $request->service_type);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        $orders = $query->orderByDesc('s.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($orders);
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
        $order = AssetServiceOrder::with([
            'items.item',
            'outlet',
            'warehouseOutlet',
            'supplier',
            'creator',
            'approvalFlows.approver',
        ])->findOrFail($id);

        $this->assertUserCanView($user, $order);

        $canApprove = false;
        if ($order->status === 'waiting_approval') {
            $nextFlow = $order->approvalFlows()
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

        $canReceiveReturn = in_array($order->status, ['in_service', 'partially_returned']);

        return response()->json([
            'id' => $order->id,
            'number' => $order->number,
            'date' => $order->date->format('Y-m-d'),
            'description' => $order->description,
            'service_type' => Schema::hasColumn('asset_service_orders', 'service_type')
                ? ($order->service_type ?? 'external')
                : 'external',
            'estimated_cost' => $order->estimated_cost,
            'actual_cost' => $order->actual_cost,
            'status' => $order->status,
            'sent_date' => $order->sent_date ? $order->sent_date->format('Y-m-d') : null,
            'return_date' => $order->return_date ? $order->return_date->format('Y-m-d') : null,
            'outlet_name' => optional($order->outlet)->nama_outlet,
            'warehouse_outlet_name' => optional($order->warehouseOutlet)->name,
            'supplier_name' => optional($order->supplier)->name,
            'creator_name' => optional($order->creator)->nama_lengkap,
            'can_approve' => $canApprove,
            'can_receive_return' => $canReceiveReturn,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => optional($item->item)->name ?? '-',
                    'unit' => $item->unit,
                    'qty_out' => (float) $item->qty_out,
                    'qty_returned' => (float) $item->qty_returned,
                    'note' => $item->note,
                    'return_date' => $item->return_date ? $item->return_date->format('Y-m-d') : null,
                    'return_note' => $item->return_note,
                ];
            }),
            'approval_flows' => $order->approvalFlows->map(function ($flow) {
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
        $this->mergeDefaultAssetServiceType($request);

        $validated = $request->validate($this->rulesForAssetServiceStore($request));

        DB::beginTransaction();
        try {
            $number = AssetServiceOrder::generateNumber();

            $createPayload = [
                'number' => $number,
                'date' => $validated['date'],
                'outlet_id' => $validated['outlet_id'],
                'warehouse_outlet_id' => $validated['warehouse_outlet_id'],
                'supplier_id' => !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
                'description' => $validated['description'],
                'estimated_cost' => $validated['estimated_cost'] ?? 0,
                'status' => 'waiting_approval',
                'created_by' => Auth::id(),
            ];
            if (Schema::hasColumn('asset_service_orders', 'service_type')) {
                $createPayload['service_type'] = $validated['service_type'];
            }

            $order = AssetServiceOrder::create($createPayload);

            foreach ($validated['items'] as $itemData) {
                AssetServiceOrderItem::create([
                    'service_order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'qty_out' => $itemData['qty_out'],
                    'unit' => $itemData['selected_unit'],
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            foreach ($validated['approvers'] as $index => $approverId) {
                AssetServiceOrderApprovalFlow::create([
                    'service_order_id' => $order->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            $this->sendNotificationToNextApprover($order->id);

            return response()->json([
                'success' => true,
                'message' => 'Service order berhasil dibuat.',
                'service_order_id' => $order->id,
                'number' => $number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error apiStore asset service order', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $order = AssetServiceOrder::findOrFail($id);
        $this->assertUserCanView(Auth::user(), $order);

        if ($order->status !== 'waiting_approval') {
            return response()->json(['success' => false, 'message' => 'Hanya service order waiting approval yang bisa dihapus.'], 422);
        }

        DB::beginTransaction();
        try {
            $order->approvalFlows()->delete();
            $order->items()->delete();
            $order->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Service order berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── INVENTORY PROCESSING ────────────────────────────────────────

    private function processStockOut($order)
    {
        $warehouseOutlet = DB::table('warehouse_outlets')
            ->where('id', $order->warehouse_outlet_id)
            ->first();

        if (!$warehouseOutlet) {
            throw new \Exception('Warehouse outlet tidak ditemukan');
        }

        $outletId = $warehouseOutlet->outlet_id;

        foreach ($order->items as $orderItem) {
            $itemMaster = DB::table('items')->where('id', $orderItem->item_id)->first();
            if (!$itemMaster) continue;

            $inventoryItem = DB::table('asset_inventory_items')
                ->where('item_id', $orderItem->item_id)
                ->first();

            if (!$inventoryItem) {
                $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                    'item_id' => $orderItem->item_id,
                    'small_unit_id' => $itemMaster->small_unit_id,
                    'medium_unit_id' => $itemMaster->medium_unit_id,
                    'large_unit_id' => $itemMaster->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $inventoryItemId = $inventoryItem->id;
            }

            $converted = $this->convertUnits($itemMaster, $orderItem->unit, $orderItem->qty_out);

            $stock = DB::table('asset_inventory_stocks')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $outletId)
                ->where('warehouse_outlet_id', $order->warehouse_outlet_id)
                ->first();

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
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $order->warehouse_outlet_id,
                'date' => $order->date,
                'reference_type' => 'asset_service_order',
                'reference_id' => $order->id,
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
                'description' => 'Stock Out - Asset Service Order',
                'created_at' => now(),
            ]);

            $lastCostHistory = DB::table('asset_inventory_cost_histories')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $outletId)
                ->where('warehouse_outlet_id', $order->warehouse_outlet_id)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->first();
            $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;

            DB::table('asset_inventory_cost_histories')->insert([
                'inventory_item_id' => $inventoryItemId,
                'outlet_id' => $outletId,
                'warehouse_outlet_id' => $order->warehouse_outlet_id,
                'date' => $order->date,
                'old_cost' => $old_cost,
                'new_cost' => $costSmall,
                'mac' => $costSmall,
                'type' => 'asset_service_order',
                'reference_type' => 'asset_service_order',
                'reference_id' => $order->id,
                'created_at' => now(),
            ]);
        }
    }

    private function processStockIn($order, $returnItems)
    {
        $warehouseOutlet = DB::table('warehouse_outlets')
            ->where('id', $order->warehouse_outlet_id)
            ->first();

        if (!$warehouseOutlet) {
            throw new \Exception('Warehouse outlet tidak ditemukan');
        }

        $outletId = $warehouseOutlet->outlet_id;

        foreach ($returnItems as $returnItem) {
            $orderItem = $order->items()->where('item_id', $returnItem['item_id'])->first();
            if (!$orderItem) continue;

            $itemMaster = DB::table('items')->where('id', $returnItem['item_id'])->first();
            if (!$itemMaster) continue;

            $inventoryItem = DB::table('asset_inventory_items')
                ->where('item_id', $returnItem['item_id'])
                ->first();

            if (!$inventoryItem) continue;
            $inventoryItemId = $inventoryItem->id;

            $converted = $this->convertUnits($itemMaster, $orderItem->unit, $returnItem['qty_returned']);

            $stock = DB::table('asset_inventory_stocks')
                ->where('inventory_item_id', $inventoryItemId)
                ->where('outlet_id', $outletId)
                ->where('warehouse_outlet_id', $order->warehouse_outlet_id)
                ->first();

            $costSmall = $stock->last_cost_small ?? 0;
            $costMedium = $stock->last_cost_medium ?? 0;
            $costLarge = $stock->last_cost_large ?? 0;

            if (!$stock) {
                DB::table('asset_inventory_stocks')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $order->warehouse_outlet_id,
                    'qty_small' => $converted['qty_small'],
                    'qty_medium' => $converted['qty_medium'],
                    'qty_large' => $converted['qty_large'],
                    'value' => 0,
                    'last_cost_small' => 0,
                    'last_cost_medium' => 0,
                    'last_cost_large' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $saldoSmall = $converted['qty_small'];
                $saldoMedium = $converted['qty_medium'];
                $saldoLarge = $converted['qty_large'];
            } else {
                $saldoSmall = $stock->qty_small + $converted['qty_small'];
                $saldoMedium = $stock->qty_medium + $converted['qty_medium'];
                $saldoLarge = $stock->qty_large + $converted['qty_large'];

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
                'warehouse_outlet_id' => $order->warehouse_outlet_id,
                'date' => now()->toDateString(),
                'reference_type' => 'asset_service_order_return',
                'reference_id' => $order->id,
                'in_qty_small' => $converted['qty_small'],
                'in_qty_medium' => $converted['qty_medium'],
                'in_qty_large' => $converted['qty_large'],
                'out_qty_small' => 0,
                'out_qty_medium' => 0,
                'out_qty_large' => 0,
                'cost_per_small' => $costSmall,
                'cost_per_medium' => $costMedium,
                'cost_per_large' => $costLarge,
                'value_in' => $converted['qty_small'] * $costSmall,
                'value_out' => 0,
                'saldo_qty_small' => $saldoSmall,
                'saldo_qty_medium' => $saldoMedium,
                'saldo_qty_large' => $saldoLarge,
                'saldo_value' => $saldoSmall * $costSmall,
                'description' => 'Stock In - Asset Service Return',
                'created_at' => now(),
            ]);
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

    // ─── PENDING APPROVALS ──────────────────────────────────────────

    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'headers' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = DB::table('asset_service_orders as s')
            ->join('asset_service_order_approval_flows as af', 's.id', '=', 'af.service_order_id')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('suppliers as sp', 's.supplier_id', '=', 'sp.id')
            ->join('users as creator', 's.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('s.status', 'waiting_approval');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                's.id', 's.number', 's.date', 's.status', 's.description as notes',
                'o.nama_outlet as outlet_name',
                'sp.name as supplier_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) return true;
                $lowerPending = DB::table('asset_service_order_approval_flows')
                    ->where('service_order_id', $header->id)
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
        $order = DB::table('asset_service_orders as s')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('suppliers as sp', 's.supplier_id', '=', 'sp.id')
            ->join('users as creator', 's.created_by', '=', 'creator.id')
            ->where('s.id', $id)
            ->select('s.*', 'o.nama_outlet as outlet_name', 'wo.name as warehouse_name', 'sp.name as supplier_name', 'creator.nama_lengkap as creator_name')
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = DB::table('asset_service_order_items as si')
            ->join('items as i', 'si.item_id', '=', 'i.id')
            ->where('si.service_order_id', $id)
            ->select('si.*', 'i.name as item_name')
            ->get();

        $approvalFlows = DB::table('asset_service_order_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.service_order_id', $id)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name', 'j.nama_jabatan as approver_jabatan')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $order,
            'items' => $items,
            'approval_flows' => $approvalFlows,
        ]);
    }

    private function sendNotificationToNextApprover($orderId)
    {
        try {
            $nextApprover = DB::table('asset_service_order_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.service_order_id', $orderId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap')
                ->first();

            if (!$nextApprover) return;

            $order = DB::table('asset_service_orders as s')
                ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('suppliers as sp', 's.supplier_id', '=', 'sp.id')
                ->join('users as creator', 's.created_by', '=', 'creator.id')
                ->where('s.id', $orderId)
                ->select('s.*', 'o.nama_outlet', 'sp.name as supplier_name', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$order) return;

            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'asset_service_order_approval',
                'title' => 'Approval Asset Service',
                'message' => "Asset Service {$order->number}"
                    . (($order->supplier_name ?? null) ? " (Supplier: {$order->supplier_name})" : ' (Internal)')
                    . " dari outlet {$order->nama_outlet} oleh {$order->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending Asset Service notification: ' . $e->getMessage());
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────

    private function assetServiceOrderListSelect(): array
    {
        $cols = [
            's.id', 's.number', 's.date', 's.description',
            's.estimated_cost', 's.actual_cost', 's.status',
            's.sent_date', 's.return_date', 's.created_at',
            'o.nama_outlet as outlet_name',
            'wo.name as warehouse_outlet_name',
            'sp.name as supplier_name',
            'u.nama_lengkap as creator_name',
        ];
        if (Schema::hasColumn('asset_service_orders', 'service_type')) {
            array_splice($cols, 4, 0, ['s.service_type']);
        }

        return $cols;
    }

    private function mergeDefaultAssetServiceType(Request $request): void
    {
        if (!Schema::hasColumn('asset_service_orders', 'service_type')) {
            return;
        }
        $v = $request->input('service_type');
        if (!in_array($v, ['internal', 'external'], true)) {
            $request->merge(['service_type' => 'external']);
        }
    }

    private function rulesForAssetServiceStore(Request $request): array
    {
        $hasSvcType = Schema::hasColumn('asset_service_orders', 'service_type');

        $rules = [
            'date' => 'required|date',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'supplier_id' => [
                Rule::requiredIf(function () use ($request, $hasSvcType) {
                    if (!$hasSvcType) {
                        return true;
                    }

                    return ($request->input('service_type', 'external')) === 'external';
                }),
                'nullable',
                'integer',
                'exists:suppliers,id',
            ],
            'description' => 'required|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty_out' => 'required|numeric|min:0.01',
            'items.*.selected_unit' => 'required|string',
            'items.*.note' => 'nullable|string',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ];
        if ($hasSvcType) {
            $rules['service_type'] = 'required|in:internal,external';
        }

        return $rules;
    }

    private function assertUserCanView($user, AssetServiceOrder $order): void
    {
        if ((int) ($user->id_outlet ?? 0) === 1) {
            return;
        }
        if ((int) ($user->id_outlet ?? 0) !== (int) $order->outlet_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }
    }
}
