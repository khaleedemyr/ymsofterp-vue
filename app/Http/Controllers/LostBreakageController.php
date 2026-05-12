<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LostBreakageController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as creator_name'
            );

        if ($user->id_outlet != 1) {
            $query->where('h.outlet_id', $user->id_outlet);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('h.number', 'like', "%{$s}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$s}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$s}%");
            });
        }

        if ($user->id_outlet == 1 && $request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }

        if ($request->filled('status')) {
            $query->where('h.status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('h.date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('h.date', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 10);

        $data = $query->orderByDesc('h.date')
            ->orderByDesc('h.id')
            ->paginate($perPage)
            ->withQueryString();

        $headerIds = collect($data->items())->pluck('id')->toArray();
        $approvalFlows = [];
        if (!empty($headerIds)) {
            $rows = DB::table('lost_breakage_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->whereIn('af.header_id', $headerIds)
                ->select(
                    'af.header_id',
                    'af.approval_level',
                    'af.status',
                    'af.approved_at',
                    'af.rejected_at',
                    'af.comments',
                    'u.nama_lengkap as approver_name',
                    'j.nama_jabatan as approver_jabatan'
                )
                ->orderBy('af.header_id')
                ->orderBy('af.approval_level')
                ->get();
            foreach ($rows as $flow) {
                $approvalFlows[$flow->header_id][] = $flow;
            }
        }

        $data->getCollection()->transform(function ($item) use ($approvalFlows) {
            $item->approval_flows = $approvalFlows[$item->id] ?? [];
            return $item;
        });

        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $data, 'outlets' => $outlets]);
        }

        return inertia('LostBreakage/Index', [
            'data'    => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'outlet_id', 'status', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    private function getAssetItems()
    {
        $assetCategoryIds = DB::table('categories')
            ->where('is_asset', '1')
            ->pluck('id')
            ->toArray();

        if (empty($assetCategoryIds)) {
            return collect([]);
        }

        $items = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('units as su', 'items.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'items.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'items.large_unit_id', '=', 'lu.id')
            ->whereIn('items.category_id', $assetCategoryIds)
            ->where('items.status', 'active')
            ->select(
                'items.id',
                'items.name',
                'items.sku',
                'categories.name as category_name',
                'items.small_unit_id',
                'items.medium_unit_id',
                'items.large_unit_id',
                'su.name as small_unit_name',
                'mu.name as medium_unit_name',
                'lu.name as large_unit_name'
            )
            ->orderBy('items.name')
            ->get();

        $itemIds = $items->pluck('id')->toArray();
        $images = [];
        if (!empty($itemIds)) {
            $rows = DB::table('item_images')
                ->whereIn('item_id', $itemIds)
                ->select('item_id', 'path')
                ->get();
            foreach ($rows as $r) {
                if (!isset($images[$r->item_id])) {
                    $images[$r->item_id] = [];
                }
                $images[$r->item_id][] = $r->path;
            }
        }

        return $items->map(function ($item) use ($images) {
            $item->image = $images[$item->id][0] ?? null;
            return $item;
        });
    }

    public function create()
    {
        $user = auth()->user();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $items = $this->getAssetItems();
        $units = DB::table('units')->where('status', 'active')->get();

        return inertia('LostBreakage/Create', [
            'outlets' => $outlets,
            'items'   => $items,
            'units'   => $units,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'header_id'          => 'nullable|integer',
            'date'               => 'required|date',
            'outlet_id'          => 'required',
            'notes'              => 'nullable|string',
            'items'              => 'nullable|array',
            'items.*.item_id'    => 'required_with:items|exists:items,id',
            'items.*.qty'        => 'required_with:items|numeric|min:0.01',
            'items.*.unit_id'    => 'required_with:items|exists:units,id',
            'items.*.note'       => 'nullable|string',
            'items.*.photo'      => 'nullable|string',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return redirect()->back()->with('error', 'User tidak terautentikasi.');
        }

        DB::beginTransaction();
        try {
            $headerId = $request->header_id;

            if ($headerId) {
                $existing = DB::table('lost_breakage_headers')
                    ->where('id', $headerId)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->lockForUpdate()
                    ->first();

                if (!$existing) {
                    throw new \Exception('Draft tidak ditemukan atau sudah tidak bisa diedit.');
                }

                DB::table('lost_breakage_headers')->where('id', $headerId)->update([
                    'date'       => $request->date,
                    'outlet_id'  => $request->outlet_id,
                    'notes'      => $request->notes,
                    'updated_at' => now(),
                ]);

                DB::table('lost_breakage_details')->where('header_id', $headerId)->delete();
                DB::table('lost_breakage_approval_flows')->where('header_id', $headerId)->delete();
            } else {
                $existing = DB::table('lost_breakage_headers')
                    ->where('outlet_id', $request->outlet_id)
                    ->where('created_by', $userId)
                    ->where('status', 'DRAFT')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $headerId = $existing->id;
                    DB::table('lost_breakage_headers')->where('id', $headerId)->update([
                        'date'       => $request->date,
                        'notes'      => $request->notes,
                        'updated_at' => now(),
                    ]);
                    DB::table('lost_breakage_details')->where('header_id', $headerId)->delete();
                    DB::table('lost_breakage_approval_flows')->where('header_id', $headerId)->delete();
                } else {
                    $draftNumber = 'DRAFT-' . $userId . '-' . time();
                    $headerId = DB::table('lost_breakage_headers')->insertGetId([
                        'number'     => $draftNumber,
                        'date'       => $request->date,
                        'outlet_id'  => $request->outlet_id,
                        'notes'      => $request->notes,
                        'status'     => 'DRAFT',
                        'created_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (!empty($request->items)) {
                foreach ($request->items as $item) {
                    DB::table('lost_breakage_details')->insert([
                        'header_id'  => $headerId,
                        'item_id'    => $item['item_id'],
                        'qty'        => $item['qty'],
                        'unit_id'    => $item['unit_id'],
                        'note'       => $item['note'] ?? null,
                        'photo'      => $item['photo'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            if ($request->wantsJson() || $request->input('autosave')) {
                return response()->json(['success' => true, 'header_id' => $headerId]);
            }

            return redirect()->route('lost-breakage.index')->with('success', 'Draft berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = auth()->user();

        $header = DB::table('lost_breakage_headers')->where('id', $id)->first();
        if (!$header || $header->status !== 'DRAFT') {
            return redirect()->route('lost-breakage.index')->with('error', 'Hanya draft yang bisa diedit.');
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->leftJoin('units as su', 'i.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'i.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'i.large_unit_id', '=', 'lu.id')
            ->where('d.header_id', $id)
            ->select(
                'd.*',
                'i.name as item_name',
                'u.name as unit_name',
                'su.name as small_unit_name',
                'mu.name as medium_unit_name',
                'lu.name as large_unit_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id'
            )
            ->get();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $items = $this->getAssetItems();
        $units = DB::table('units')->where('status', 'active')->get();

        $approvalFlows = DB::table('lost_breakage_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->where('af.header_id', $id)
            ->select('af.*', 'u.nama_lengkap as approver_name', 'u.email as approver_email')
            ->orderBy('af.approval_level')
            ->get();

        return inertia('LostBreakage/Create', [
            'outlets'       => $outlets,
            'items'         => $items,
            'units'         => $units,
            'header'        => $header,
            'details'       => $details,
            'approvalFlows' => $approvalFlows,
            'isEdit'        => true,
        ]);
    }

    public function show($id)
    {
        $header = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.id', $id)
            ->select('h.*', 'o.nama_outlet as outlet_name', 'u.nama_lengkap as creator_name')
            ->first();

        if (!$header) {
            return redirect()->route('lost-breakage.index')->with('error', 'Data tidak ditemukan.');
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();

        $approvalFlows = DB::table('lost_breakage_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->select(
                'af.*',
                'u.nama_lengkap as approver_name',
                'u.email as approver_email',
                'j.nama_jabatan as approver_jabatan'
            )
            ->orderBy('af.approval_level')
            ->get();

        $currentApprover = null;
        $currentUserId = auth()->id();
        if ($currentUserId) {
            $currentApprover = DB::table('lost_breakage_approval_flows')
                ->where('header_id', $id)
                ->where('approver_id', $currentUserId)
                ->where('status', 'PENDING')
                ->first();
        }

        return inertia('LostBreakage/Show', [
            'header'          => $header,
            'details'         => $details,
            'approvalFlows'   => $approvalFlows,
            'currentApprover' => $currentApprover,
        ]);
    }

    public function submit(Request $request, $id)
    {
        try {
            $header = DB::table('lost_breakage_headers')->where('id', $id)->first();
            if (!$header) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }
            if ($header->status !== 'DRAFT') {
                return response()->json(['success' => false, 'message' => 'Hanya draft yang dapat di-submit'], 400);
            }

            $details = DB::table('lost_breakage_details')->where('header_id', $id)->get();
            if ($details->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Tambahkan minimal 1 item sebelum submit.'], 400);
            }

            if (empty($request->approvers) || !is_array($request->approvers)) {
                return response()->json(['success' => false, 'message' => 'Wajib menambahkan minimal 1 approver.'], 400);
            }

            DB::beginTransaction();

            DB::table('lost_breakage_approval_flows')->where('header_id', $id)->delete();

            foreach ($request->approvers as $index => $approverId) {
                $approverExists = DB::table('users')->where('id', $approverId)->exists();
                if (!$approverExists) {
                    throw new \Exception("Approver dengan ID {$approverId} tidak ditemukan.");
                }
                DB::table('lost_breakage_approval_flows')->insert([
                    'header_id'      => $id,
                    'approver_id'    => $approverId,
                    'approval_level' => $index + 1,
                    'status'         => 'PENDING',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            $date   = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $newNumber = 'LB-' . $date . '-' . $random;

            DB::table('lost_breakage_headers')->where('id', $id)->update([
                'number'       => $newNumber,
                'status'       => 'SUBMITTED',
                'submitted_at' => now(),
                'updated_at'   => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Berhasil di-submit untuk approval.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        $header = DB::table('lost_breakage_headers')->where('id', $id)->first();
        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        if ($header->status !== 'SUBMITTED') {
            return response()->json(['success' => false, 'message' => 'Hanya data SUBMITTED yang dapat di-approve'], 400);
        }

        try {
            DB::beginTransaction();
            $currentUser = auth()->user();
            $isSuperadmin = $currentUser->id_role === '5af56935b011a';
            $note = $request->input('note') ?? $request->input('comments');

            if ($request->has('approval_flow_id')) {
                $flow = DB::table('lost_breakage_approval_flows')
                    ->where('id', $request->approval_flow_id)
                    ->where('header_id', $id)
                    ->where('status', 'PENDING')
                    ->first();
            } else {
                $flow = DB::table('lost_breakage_approval_flows')
                    ->where('header_id', $id)
                    ->where('approver_id', $currentUser->id)
                    ->where('status', 'PENDING')
                    ->first();
            }

            if (!$flow && $isSuperadmin) {
                $flow = DB::table('lost_breakage_approval_flows')
                    ->where('header_id', $id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();
            }

            if (!$flow) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk approve data ini'], 403);
            }

            $lowerPending = DB::table('lost_breakage_approval_flows')
                ->where('header_id', $id)
                ->where('approval_level', '<', $flow->approval_level)
                ->where('status', 'PENDING')
                ->count();
            if ($lowerPending > 0 && !$isSuperadmin) {
                return response()->json(['success' => false, 'message' => 'Tunggu approval dari level yang lebih rendah terlebih dahulu'], 400);
            }

            DB::table('lost_breakage_approval_flows')->where('id', $flow->id)->update([
                'status'      => 'APPROVED',
                'approved_at' => now(),
                'comments'    => $note,
                'updated_at'  => now(),
            ]);

            $pendingCount = DB::table('lost_breakage_approval_flows')
                ->where('header_id', $id)
                ->where('status', 'PENDING')
                ->count();

            $message = 'Berhasil di-approve.';
            if ($pendingCount === 0) {
                DB::table('lost_breakage_headers')->where('id', $id)->update([
                    'status'     => 'APPROVED',
                    'updated_at' => now(),
                ]);
                $message = 'Semua approval selesai. Data telah disetujui.';
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $header = DB::table('lost_breakage_headers')->where('id', $id)->first();
        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        if ($header->status !== 'SUBMITTED') {
            return response()->json(['success' => false, 'message' => 'Hanya data SUBMITTED yang dapat ditolak'], 400);
        }

        $reason = $request->input('rejection_reason') ?? $request->input('reason') ?? $request->input('comments');

        try {
            DB::beginTransaction();
            $currentUser = auth()->user();
            $isSuperadmin = $currentUser->id_role === '5af56935b011a';

            $flow = DB::table('lost_breakage_approval_flows')
                ->where('header_id', $id)
                ->where('approver_id', $currentUser->id)
                ->where('status', 'PENDING')
                ->first();

            if (!$flow && $isSuperadmin) {
                $flow = DB::table('lost_breakage_approval_flows')
                    ->where('header_id', $id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();
            }

            if (!$flow) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menolak data ini'], 403);
            }

            DB::table('lost_breakage_approval_flows')->where('id', $flow->id)->update([
                'status'      => 'REJECTED',
                'rejected_at' => now(),
                'comments'    => $reason,
                'updated_at'  => now(),
            ]);

            DB::table('lost_breakage_headers')->where('id', $id)->update([
                'status'     => 'REJECTED',
                'updated_at' => now(),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data berhasil ditolak.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $header = DB::table('lost_breakage_headers')->where('id', $id)->first();
        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if (!in_array($header->status, ['DRAFT', 'REJECTED'])) {
            return response()->json(['success' => false, 'message' => 'Hanya data DRAFT atau REJECTED yang bisa dihapus'], 400);
        }

        DB::table('lost_breakage_details')->where('header_id', $id)->delete();
        DB::table('lost_breakage_approval_flows')->where('header_id', $id)->delete();
        DB::table('lost_breakage_headers')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus.']);
    }

    public function getApprovers(Request $request)
    {
        $like = $request->filled('q') ? '%' . $request->q . '%' : null;

        $users = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->where('users.status', 'A')
            ->when($like, function ($q) use ($like) {
                $q->where(function ($query) use ($like) {
                    $query->where('users.nama_lengkap', 'like', $like)
                          ->orWhere('users.email', 'like', $like)
                          ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', $like);
                });
            })
            ->select('users.id', 'users.nama_lengkap as name', 'users.email', 'tbl_data_jabatan.nama_jabatan as jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $request->file('photo')->store('lost-breakage', 'public');

        return response()->json([
            'success' => true,
            'path'    => $path,
            'url'     => asset('storage/' . $path),
        ]);
    }

    public function getItemUnits($id)
    {
        $item = DB::table('items')
            ->leftJoin('units as su', 'items.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'items.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'items.large_unit_id', '=', 'lu.id')
            ->where('items.id', $id)
            ->select(
                'items.small_unit_id',
                'items.medium_unit_id',
                'items.large_unit_id',
                'su.name as small_unit_name',
                'mu.name as medium_unit_name',
                'lu.name as large_unit_name'
            )
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan'], 404);
        }

        $units = [];
        if ($item->small_unit_id) {
            $units[] = ['id' => $item->small_unit_id, 'name' => $item->small_unit_name, 'type' => 'small'];
        }
        if ($item->medium_unit_id) {
            $units[] = ['id' => $item->medium_unit_id, 'name' => $item->medium_unit_name, 'type' => 'medium'];
        }
        if ($item->large_unit_id) {
            $units[] = ['id' => $item->large_unit_id, 'name' => $item->large_unit_name, 'type' => 'large'];
        }

        return response()->json(['success' => true, 'units' => $units]);
    }
}
