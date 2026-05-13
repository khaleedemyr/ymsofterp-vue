<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PosVoidItemRequestController extends Controller
{
    /**
     * @param  array<int>  $userIds
     */
    private function forgetPendingCachesForUserIds(array $userIds): void
    {
        foreach (array_unique($userIds) as $uid) {
            Cache::forget('all_pending_approvals_'.$uid);
        }
    }

    private function forgetPendingCachesForRequest(int $requestPk): void
    {
        $ids = DB::table('pos_void_item_request_approvers')
            ->where('pos_void_item_request_id', $requestPk)
            ->pluck('user_id')
            ->all();
        $this->forgetPendingCachesForUserIds($ids);
    }

    /**
     * Validasi user HO: status A, id_outlet = 1.
     *
     * @param  array<int>  $userIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function validateHoApprovers(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (count($userIds) === 0) {
            throw new \InvalidArgumentException('Minimal satu approver.');
        }

        $found = DB::table('users')
            ->whereIn('id', $userIds)
            ->where('status', 'A')
            ->where('id_outlet', 1)
            ->pluck('id');

        if ($found->count() !== count($userIds)) {
            throw new \InvalidArgumentException('Salah satu approver tidak valid (pastikan status=A dan id_outlet=1).');
        }

        return $found->values();
    }

    /**
     * POS: autocomplete approvers — users.status = A, id_outlet = 1 (HO).
     */
    public function searchApproversForPos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_outlet' => 'required|string',
            'q' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $outlet = DB::table('tbl_data_outlet')->where('qr_code', $request->input('kode_outlet'))->first();
        if (!$outlet) {
            return response()->json(['success' => false, 'message' => 'Outlet not found', 'data' => []], 404);
        }

        $q = trim((string) $request->input('q', ''));

        $query = DB::table('users')
            ->where('status', 'A')
            ->where('id_outlet', 1)
            ->select('id', 'nama_lengkap', 'email')
            ->orderBy('nama_lengkap')
            ->limit(30);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('nama_lengkap', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            });
        }

        $users = $query->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * POS: buat permintaan void item (menunggu approve di ERP).
     * approver_user_ids: array — siapa pun yang terdaftar boleh approve (OR).
     */
    public function storeFromPos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_outlet' => 'required|string',
            'order_id' => 'required',
            'order_item_id' => 'required',
            'reason' => 'required|string|max:2000',
            'approver_user_ids' => 'nullable|array',
            'approver_user_ids.*' => 'integer',
            'approver_user_id' => 'nullable|integer',
            'requester_user_id' => 'nullable|integer',
            'requester_username' => 'nullable|string|max:255',
            'item_snapshot' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $ids = $request->input('approver_user_ids');
        if (! is_array($ids) || count($ids) === 0) {
            $single = $request->input('approver_user_id');
            $ids = $single ? [(int) $single] : [];
        }

        try {
            $approverIds = array_values(array_unique($this->validateHoApprovers($ids)->toArray()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $kodeOutlet = $request->input('kode_outlet');
        $orderId = $request->input('order_id');
        $orderItemId = $request->input('order_item_id');

        $outlet = DB::table('tbl_data_outlet')->where('qr_code', $kodeOutlet)->first();
        if (! $outlet) {
            return response()->json(['success' => false, 'message' => 'Outlet not found'], 404);
        }

        $row = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.kode_outlet', $kodeOutlet)
            ->where('oi.order_id', $orderId)
            ->where('oi.id', $orderItemId)
            ->select('oi.id', 'oi.order_id', 'o.nomor as order_nomor')
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Order item tidak ditemukan di server (pastikan order sudah sync ke pusat).',
            ], 404);
        }

        $dup = DB::table('pos_void_item_requests')
            ->where('order_item_id', $orderItemId)
            ->where('status', 'pending')
            ->first();

        if ($dup) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada permintaan void pending untuk item ini.',
                'public_token' => $dup->public_token,
                'id' => $dup->id,
            ], 409);
        }

        $publicToken = (string) Str::uuid();
        $snapshot = $request->input('item_snapshot');

        DB::beginTransaction();
        try {
            $id = DB::table('pos_void_item_requests')->insertGetId([
                'public_token' => $publicToken,
                'kode_outlet' => $kodeOutlet,
                'order_id' => (string) $orderId,
                'order_nomor' => $row->order_nomor,
                'order_item_id' => (int) $orderItemId,
                'requester_user_id' => $request->input('requester_user_id'),
                'requester_username' => $request->input('requester_username'),
                'reason' => $request->input('reason'),
                'item_snapshot' => $snapshot ? json_encode($snapshot) : null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($approverIds as $uid) {
                DB::table('pos_void_item_request_approvers')->insert([
                    'pos_void_item_request_id' => $id,
                    'user_id' => $uid,
                    'created_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS void item request insert failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan permintaan: '.$e->getMessage(),
            ], 500);
        }

        $this->forgetPendingCachesForUserIds($approverIds);

        Log::info('POS void item request created', [
            'id' => $id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'approver_user_ids' => $approverIds,
        ]);

        return response()->json([
            'success' => true,
            'public_token' => $publicToken,
            'id' => $id,
            'approver_user_ids' => $approverIds,
        ]);
    }

    /**
     * POS: ganti daftar approver selagi pending (misal salah pilih / salah satu tidak bisa approve).
     * Hanya pemohon yang sama (requester_user_id) yang boleh mengubah.
     */
    public function reassignApproversFromPos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_outlet' => 'required|string',
            'public_token' => 'required|string',
            'requester_user_id' => 'nullable|integer',
            'approver_user_ids' => 'required|array|min:1',
            'approver_user_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $newApproverIds = array_values(array_unique($this->validateHoApprovers($request->input('approver_user_ids'))->toArray()));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $rec = DB::table('pos_void_item_requests')
            ->where('public_token', $request->input('public_token'))
            ->where('kode_outlet', $request->input('kode_outlet'))
            ->first();

        if (! $rec) {
            return response()->json(['success' => false, 'message' => 'Request tidak ditemukan'], 404);
        }

        if ($rec->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan pending yang bisa diganti approver-nya'], 400);
        }

        $reqUid = $request->input('requester_user_id');
        if ($rec->requester_user_id !== null) {
            if ($reqUid === null || (int) $rec->requester_user_id !== (int) $reqUid) {
                return response()->json(['success' => false, 'message' => 'Hanya kasir yang sama (requester_user_id) yang bisa mengganti approver'], 403);
            }
        }

        $this->forgetPendingCachesForRequest((int) $rec->id);

        DB::transaction(function () use ($rec, $newApproverIds) {
            DB::table('pos_void_item_request_approvers')
                ->where('pos_void_item_request_id', $rec->id)
                ->delete();

            foreach ($newApproverIds as $uid) {
                DB::table('pos_void_item_request_approvers')->insert([
                    'pos_void_item_request_id' => $rec->id,
                    'user_id' => $uid,
                    'created_at' => now(),
                ]);
            }

            DB::table('pos_void_item_requests')->where('id', $rec->id)->update([
                'updated_at' => now(),
            ]);
        });

        $this->forgetPendingCachesForUserIds($newApproverIds);

        return response()->json([
            'success' => true,
            'message' => 'Daftar approver diperbarui.',
            'approver_user_ids' => $newApproverIds,
        ]);
    }

    /**
     * POS: polling status sampai approved/rejected.
     */
    public function pollStatusFromPos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_outlet' => 'required|string',
            'public_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $rec = DB::table('pos_void_item_requests')
            ->where('public_token', $request->input('public_token'))
            ->where('kode_outlet', $request->input('kode_outlet'))
            ->first();

        if (! $rec) {
            return response()->json(['success' => false, 'message' => 'Request tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $rec->status,
            'rejection_note' => $rec->rejection_note,
            'approved_at' => $rec->approved_at,
            'rejected_at' => $rec->rejected_at,
        ]);
    }

    /**
     * Home / pending-aggregates: siapa pun yang ada di junction boleh melihat di inbox (OR).
     */
    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized', 'headers' => []], 401);
        }

        $rows = DB::table('pos_void_item_requests as r')
            ->join('pos_void_item_request_approvers as a', 'a.pos_void_item_request_id', '=', 'r.id')
            ->where('r.status', 'pending')
            ->where('a.user_id', $currentUser->id)
            ->select(
                'r.id',
                'r.public_token',
                'r.kode_outlet',
                'r.order_id',
                'r.order_nomor',
                'r.order_item_id',
                'r.reason',
                'r.requester_username',
                'r.created_at',
                'r.item_snapshot'
            )
            ->orderByDesc('r.created_at')
            ->limit(50)
            ->get();

        $headers = $rows->map(function ($r) {
            $snap = $r->item_snapshot ? json_decode($r->item_snapshot, true) : null;
            $itemName = is_array($snap) ? ($snap['name'] ?? $snap['item_name'] ?? '-') : '-';

            return (object) [
                'id' => $r->id,
                'number' => $r->order_nomor ?: $r->order_id,
                'order_nomor' => $r->order_nomor,
                'order_id' => $r->order_id,
                'order_item_id' => $r->order_item_id,
                'item_name' => $itemName,
                'outlet_name' => $r->kode_outlet,
                'creator_name' => $r->requester_username ?? '-',
                'reason' => $r->reason,
                'date' => $r->created_at,
                'approver_name' => '',
            ];
        });

        return response()->json([
            'success' => true,
            'headers' => $headers,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $rec = DB::table('pos_void_item_requests')->where('id', $id)->first();
        if (! $rec) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($rec->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Status tidak pending'], 400);
        }

        $isApprover = DB::table('pos_void_item_request_approvers')
            ->where('pos_void_item_request_id', $rec->id)
            ->where('user_id', $currentUser->id)
            ->exists();

        if (! $isApprover) {
            return response()->json(['success' => false, 'message' => 'Anda bukan salah satu approver untuk permintaan ini'], 403);
        }

        $this->forgetPendingCachesForRequest((int) $rec->id);

        DB::table('pos_void_item_requests')->where('id', $id)->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_user_id' => $currentUser->id,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Void item disetujui. Kasir dapat melanjutkan di POS.',
        ]);
    }

    public function reject(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $rec = DB::table('pos_void_item_requests')->where('id', $id)->first();
        if (! $rec) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($rec->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Status tidak pending'], 400);
        }

        $isApprover = DB::table('pos_void_item_request_approvers')
            ->where('pos_void_item_request_id', $rec->id)
            ->where('user_id', $currentUser->id)
            ->exists();

        if (! $isApprover) {
            return response()->json(['success' => false, 'message' => 'Anda bukan salah satu approver untuk permintaan ini'], 403);
        }

        $note = $request->input('rejection_reason') ?? $request->input('note') ?? '';

        $this->forgetPendingCachesForRequest((int) $rec->id);

        DB::table('pos_void_item_requests')->where('id', $id)->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_note' => $note ? (string) $note : null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan void item ditolak.',
        ]);
    }
}
