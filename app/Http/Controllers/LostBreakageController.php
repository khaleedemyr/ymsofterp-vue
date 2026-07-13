<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;
use App\Services\AssetInventoryStockService;
use App\Services\LostBreakageReplacementService;
use App\Services\LostBreakageStockService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LostBreakageExport;

class LostBreakageController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as oo', 'h.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select(
                'h.*',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
            );

        if ($user->id_outlet != 1) {
            $query->where('h.owner_outlet_id', $user->id_outlet);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('h.number', 'like', "%{$s}%")
                  ->orWhere('o.nama_outlet', 'like', "%{$s}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$s}%");
            });
        }

        if ($user->id_outlet == 1 && $request->filled('owner_outlet_id')) {
            $query->where('h.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('outlet_id')) {
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

        $headerIds = collect($data->items())->pluck('id')->toArray();
        $replacementSummary = $this->replacementSummaryByHeaderId($headerIds);
        $data->getCollection()->transform(function ($item) use ($replacementSummary) {
            $item->replacement_summary = $replacementSummary[$item->id] ?? 'none';
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

    private function lostBreakageReplacementsTableExists(): bool
    {
        try {
            return Schema::hasTable('lost_breakage_replacements');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $details
     */
    private function hydrateDetailsWithReplacements($details): void
    {
        if (!$this->lostBreakageReplacementsTableExists() || $details->isEmpty()) {
            foreach ($details as $d) {
                $d->replacements = [];
                $d->replaced_qty_total = 0;
                $d->remaining_qty = (float) $d->qty;
                $d->replacement_fulfillment = 'none';
            }

            return;
        }

        $detailIds = $details->pluck('id')->toArray();
        $rows = DB::table('lost_breakage_replacements as r')
            ->join('users as u', 'r.replaced_by', '=', 'u.id')
            ->leftJoin('items as ri', 'r.replacement_item_id', '=', 'ri.id')
            ->leftJoin('units as ru', 'r.unit_id', '=', 'ru.id')
            ->whereIn('r.detail_id', $detailIds)
            ->orderBy('r.id')
            ->select(
                'r.*',
                'u.nama_lengkap as replaced_by_name',
                'ri.name as replacement_item_name',
                'ri.sku as replacement_item_sku',
                'ru.name as replacement_unit_name'
            )
            ->get();

        $byDetail = [];
        foreach ($rows as $r) {
            $byDetail[$r->detail_id][] = $r;
        }

        foreach ($details as $d) {
            $list = $byDetail[$d->id] ?? [];
            $sum = 0.0;
            foreach ($list as $x) {
                $sum += (float) $x->qty_replaced;
            }
            $qty = (float) $d->qty;
            $d->replacements = $list;
            $d->replaced_qty_total = $sum;
            $d->remaining_qty = max(0, round($qty - $sum, 4));
            if ($sum <= 1e-9) {
                $d->replacement_fulfillment = 'none';
            } elseif ($sum >= $qty - 1e-6) {
                $d->replacement_fulfillment = 'complete';
            } else {
                $d->replacement_fulfillment = 'partial';
            }
        }
    }

    private function replacementSummaryByHeaderId(array $headerIds): array
    {
        $out = [];
        foreach ($headerIds as $id) {
            $out[$id] = 'none';
        }
        if (empty($headerIds) || !$this->lostBreakageReplacementsTableExists()) {
            return $out;
        }

        $details = DB::table('lost_breakage_details as d')
            ->whereIn('d.header_id', $headerIds)
            ->leftJoin(DB::raw('(SELECT detail_id, SUM(qty_replaced) AS rep_sum FROM lost_breakage_replacements GROUP BY detail_id) AS rs'), 'd.id', '=', 'rs.detail_id')
            ->select('d.header_id', 'd.qty', DB::raw('COALESCE(rs.rep_sum, 0) AS rep_sum'))
            ->get();

        $grouped = [];
        foreach ($details as $row) {
            $grouped[$row->header_id][] = $row;
        }

        foreach ($grouped as $headerId => $rows) {
            $anyRep = false;
            $allComplete = true;
            foreach ($rows as $row) {
                $rep = (float) $row->rep_sum;
                $qty = (float) $row->qty;
                if ($rep > 1e-9) {
                    $anyRep = true;
                }
                if ($rep < $qty - 1e-6) {
                    $allComplete = false;
                }
            }
            if (!$anyRep) {
                $out[$headerId] = 'none';
            } elseif ($allComplete) {
                $out[$headerId] = 'complete';
            } else {
                $out[$headerId] = 'partial';
            }
        }

        return $out;
    }

    private function isAssetItemId(int $itemId): bool
    {
        $assetCategoryIds = DB::table('categories')
            ->where('is_asset', '1')
            ->pluck('id')
            ->toArray();
        if (empty($assetCategoryIds)) {
            return false;
        }

        return DB::table('items')
            ->where('id', $itemId)
            ->whereIn('category_id', $assetCategoryIds)
            ->where('status', 'active')
            ->exists();
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

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return inertia('LostBreakage/Create', [
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'items'   => $items,
            'units'   => $units,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'header_id'          => 'nullable|integer',
            'date'               => 'required|date',
            'owner_outlet_id'    => 'required|integer',
            'outlet_id'          => 'required|integer',
            'warehouse_outlet_id'=> 'nullable|integer',
            'notes'              => 'nullable|string',
            'items'              => 'nullable|array',
            'items.*.item_id'    => 'required_with:items|exists:items,id',
            'items.*.type'       => 'required_with:items|in:lost,breakage',
            'items.*.qty'        => 'required_with:items|numeric|min:0.01',
            'items.*.unit_id'    => 'required_with:items|exists:units,id',
            'items.*.note'       => 'nullable|string',
            'items.*.photo'      => 'nullable|string',
        ]);

        if ($request->has('items')) {
            foreach ($request->items as $idx => $item) {
                if (($item['type'] ?? '') === 'breakage' && empty($item['photo'])) {
                    return redirect()->back()->withErrors(["items.{$idx}.photo" => 'Foto wajib untuk item bertipe Breakage.'])->withInput();
                }
            }
        }

        $userId = Auth::id();
        if (!$userId) {
            return redirect()->back()->with('error', 'User tidak terautentikasi.');
        }

        DB::beginTransaction();
        try {
            $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
                (int) $request->outlet_id,
                $request->warehouse_outlet_id ? (int) $request->warehouse_outlet_id : null
            );
            if ($request->warehouse_outlet_id) {
                AssetInventoryStockService::assertWarehouseBelongsToOutlet(
                    (int) $request->warehouse_outlet_id,
                    (int) $request->outlet_id
                );
            }

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
                    'date'                => $request->date,
                    'owner_outlet_id'     => $request->owner_outlet_id,
                    'outlet_id'           => $locationOutletId,
                    'warehouse_outlet_id' => $request->warehouse_outlet_id,
                    'notes'               => $request->notes,
                    'updated_at'          => now(),
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
                        'number'              => $draftNumber,
                        'date'                => $request->date,
                        'owner_outlet_id'     => $request->owner_outlet_id,
                        'outlet_id'           => $locationOutletId,
                        'warehouse_outlet_id' => $request->warehouse_outlet_id,
                        'notes'               => $request->notes,
                        'status'              => 'DRAFT',
                        'created_by'          => $userId,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }

            if (!empty($request->items)) {
                foreach ($request->items as $item) {
                    DB::table('lost_breakage_details')->insert([
                        'header_id'  => $headerId,
                        'item_id'    => $item['item_id'],
                        'type'       => $item['type'] ?? 'lost',
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
            ->leftJoin('tbl_data_outlet as oo', 'h.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.id', $id)
            ->select(
                'h.*',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
            )
            ->first();

        if (!$header) {
            return redirect()->route('lost-breakage.index')->with('error', 'Data tidak ditemukan.');
        }

        $user = auth()->user();
        if ($user && (int) $user->id_outlet !== 1 && (int) $header->owner_outlet_id !== (int) $user->id_outlet) {
            return redirect()->route('lost-breakage.index')->with('error', 'Tidak punya akses.');
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();

        $this->hydrateDetailsWithReplacements($details);

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

            try {
                $this->sendNotificationToNextApprover($id);
            } catch (\Exception $notifError) {
                \Log::warning('LostBreakage submit - Notification failed (but data saved):', [
                    'header_id' => $id,
                    'error' => $notifError->getMessage()
                ]);
            }

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

                $freshHeader = DB::table('lost_breakage_headers')->where('id', $id)->first();
                $details = DB::table('lost_breakage_details')->where('header_id', $id)->get()->all();
                app(LostBreakageStockService::class)->applyStockOut($freshHeader, $details);

                $message = 'Semua approval selesai. Data telah disetujui dan stok telah dikurangi.';
            }

            DB::commit();

            if ($pendingCount > 0) {
                try {
                    $this->sendNotificationToNextApprover($id);
                } catch (\Exception $e) {
                    \Log::warning('LostBreakage approve - Notification to next approver failed:', ['error' => $e->getMessage()]);
                }
            }

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

        $currentUser = auth()->user();
        $canForceDelete = $currentUser && ($currentUser->division_id == 13 || $currentUser->id_role === '5af56935b011a');

        if (!$canForceDelete && !in_array($header->status, ['DRAFT', 'REJECTED'])) {
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

    public function getPendingApprovals(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized', 'headers' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = DB::table('lost_breakage_headers as h')
            ->join('lost_breakage_approval_flows as af', 'h.id', '=', 'af.header_id')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->where('af.status', 'PENDING')
            ->where('h.status', 'SUBMITTED');

        if (!$isSuperadmin) {
            $query->where('af.approver_id', $currentUser->id);
        }

        $pendingHeaders = $query
            ->leftJoin('users as approver', 'af.approver_id', '=', 'approver.id')
            ->select(
                'h.id', 'h.number', 'h.date', 'h.status', 'h.notes',
                'o.nama_outlet as outlet_name',
                'creator.nama_lengkap as creator_name',
                'af.approval_level',
                'approver.nama_lengkap as approver_name'
            )
            ->get()
            ->filter(function ($header) use ($currentUser, $isSuperadmin) {
                if ($isSuperadmin) return true;
                $lowerPending = DB::table('lost_breakage_approval_flows')
                    ->where('header_id', $header->id)
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
        $header = DB::table('lost_breakage_headers as h')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->join('users as creator', 'h.created_by', '=', 'creator.id')
            ->where('h.id', $id)
            ->select('h.*', 'o.nama_outlet as outlet_name', 'creator.nama_lengkap as creator_name')
            ->first();

        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();

        $this->hydrateDetailsWithReplacements($details);

        $approvalFlows = DB::table('lost_breakage_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->orderBy('af.approval_level')
            ->select('af.*', 'u.nama_lengkap as approver_name', 'u.email as approver_email', 'j.nama_jabatan as approver_jabatan')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $header,
            'details' => $details,
            'approval_flows' => $approvalFlows,
        ]);
    }

    private function sendNotificationToNextApprover($headerId)
    {
        try {
            $nextApprover = DB::table('lost_breakage_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->where('af.header_id', $headerId)
                ->where('af.status', 'PENDING')
                ->orderBy('af.approval_level')
                ->select('u.id', 'u.nama_lengkap', 'u.email')
                ->first();

            if (!$nextApprover) return;

            $header = DB::table('lost_breakage_headers as h')
                ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
                ->join('users as creator', 'h.created_by', '=', 'creator.id')
                ->where('h.id', $headerId)
                ->select('h.*', 'o.nama_outlet', 'creator.nama_lengkap as creator_name')
                ->first();

            if (!$header) return;

            NotificationService::insert([
                'user_id' => $nextApprover->id,
                'type' => 'lost_breakage_approval',
                'title' => 'Approval Lost & Breakage',
                'message' => "Lost & Breakage {$header->number} dari outlet {$header->nama_outlet} oleh {$header->creator_name} menunggu approval Anda.",
                'is_read' => 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending Lost & Breakage notification: ' . $e->getMessage());
        }
    }

    public function apiGetAssetItems(Request $request)
    {
        $items = $this->getAssetItems();
        $search = $request->input('search');
        if ($search) {
            $q = strtolower($search);
            $items = $items->filter(function ($item) use ($q) {
                return str_contains(strtolower($item->name), $q) || str_contains(strtolower($item->sku ?? ''), $q);
            })->values();
        }
        return response()->json($items);
    }

    public function apiShow($id)
    {
        $header = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as oo', 'h.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->where('h.id', $id)
            ->select(
                'h.*',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_outlet_name',
                'u.nama_lengkap as creator_name',
                'u.avatar as creator_avatar'
            )
            ->first();

        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->leftJoin('units as su', 'i.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'i.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'i.large_unit_id', '=', 'lu.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name', 'su.name as small_unit_name', 'mu.name as medium_unit_name', 'lu.name as large_unit_name', 'i.small_unit_id', 'i.medium_unit_id', 'i.large_unit_id')
            ->get();

        $this->hydrateDetailsWithReplacements($details);

        $approvalFlows = DB::table('lost_breakage_approval_flows as af')
            ->join('users as u', 'af.approver_id', '=', 'u.id')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('af.header_id', $id)
            ->select('af.*', 'u.nama_lengkap as approver_name', 'u.email as approver_email', 'j.nama_jabatan as approver_jabatan')
            ->orderBy('af.approval_level')
            ->get();

        $currentApprover = $this->resolveCurrentLostBreakageApprover((int) $id);

        return response()->json([
            'success' => true,
            'header' => $header,
            'details' => $details,
            'approval_flows' => $approvalFlows,
            'current_approver' => $currentApprover,
            'can_approve' => $header->status === 'SUBMITTED' && $currentApprover !== null,
            // Penggantian lewat Asset Replacement → PR → PO → GR (sama seperti web Show.vue).
            'can_record_replacements' => false,
        ]);
    }

    private function resolveCurrentLostBreakageApprover(int $headerId): ?object
    {
        $currentUserId = auth()->id();
        if (! $currentUserId) {
            return null;
        }

        return DB::table('lost_breakage_approval_flows')
            ->where('header_id', $headerId)
            ->where('approver_id', $currentUserId)
            ->where('status', 'PENDING')
            ->first() ?: null;
    }

    public function apiFormMeta()
    {
        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'warehouse_outlets' => $warehouseOutlets,
        ]);
    }

    public function apiReplacementBacklog(Request $request)
    {
        $user = auth()->user();
        $service = app(LostBreakageReplacementService::class);
        $filters = $request->only(['search', 'owner_outlet_id', 'outlet_id', 'type', 'date_from', 'date_to']);

        return response()->json([
            'success' => true,
            'rows' => $service->pendingDetailRows($filters, $user),
            'pr_integration_ready' => $service->prLinesTableExists() && $service->replacementsTableExists(),
            'is_admin' => (int) ($user->id_outlet ?? 0) === 1,
        ]);
    }

    public function apiPreparePrFromBacklog(Request $request)
    {
        $request->validate([
            'detail_ids' => 'required|array|min:1',
            'detail_ids.*' => 'integer|min:1',
        ]);

        $service = app(LostBreakageReplacementService::class);
        if (!$service->prLinesTableExists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jalankan database/sql/lost_breakage_pr_integration.sql terlebih dahulu.',
            ], 503);
        }

        try {
            $prefill = $service->buildPrPrefill($request->detail_ids, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'prefill' => $prefill,
        ]);
    }

    public function apiReport(Request $request)
    {
        $user = auth()->user();
        $query = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select('h.*', 'o.nama_outlet as outlet_name', 'u.nama_lengkap as creator_name', 'u.avatar as creator_avatar');

        if ($user->id_outlet != 1) {
            $query->where('h.owner_outlet_id', $user->id_outlet);
        } elseif ($request->filled('owner_outlet_id')) {
            $query->where('h.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }
        if ($request->filled('status')) $query->where('h.status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('h.date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('h.date', '<=', $request->date_to);

        $data = $query->orderByDesc('h.date')->orderByDesc('h.id')->paginate(20)->withQueryString();
        $headerIds = collect($data->items())->pluck('id')->toArray();
        if (!empty($headerIds)) {
            $detailRows = DB::table('lost_breakage_details')
                ->whereIn('header_id', $headerIds)
                ->select('header_id', DB::raw('COUNT(*) as cnt'), DB::raw("SUM(CASE WHEN type='lost' THEN 1 ELSE 0 END) as lost_count"), DB::raw("SUM(CASE WHEN type='breakage' THEN 1 ELSE 0 END) as breakage_count"))
                ->groupBy('header_id')->get();
            $countMap = []; $typeMap = [];
            foreach ($detailRows as $r) { $countMap[$r->header_id] = $r->cnt; $typeMap[$r->header_id] = ['lost' => $r->lost_count, 'breakage' => $r->breakage_count]; }
            $approvalRows = DB::table('lost_breakage_approval_flows as af')->join('users as u', 'af.approver_id', '=', 'u.id')->whereIn('af.header_id', $headerIds)->orderBy('af.approval_level')->select('af.*', 'u.nama_lengkap as approver_name')->get();
            $approvalMap = [];
            foreach ($approvalRows as $af) { $approvalMap[$af->header_id][] = $af; }
            $replacementSummary = $this->replacementSummaryByHeaderId($headerIds);
            $data->getCollection()->transform(function ($item) use ($countMap, $typeMap, $approvalMap, $replacementSummary) {
                $item->item_count = $countMap[$item->id] ?? 0;
                $item->type_summary = $typeMap[$item->id] ?? ['lost' => 0, 'breakage' => 0];
                $item->approval_flows = $approvalMap[$item->id] ?? [];
                $item->replacement_summary = $replacementSummary[$item->id] ?? 'none';
                return $item;
            });
        }
        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet as id', 'nama_outlet as name')->orderBy('nama_outlet')->get();
        }
        return response()->json(['data' => $data, 'outlets' => $outlets]);
    }

    public function report(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('lost_breakage_headers as h')
            ->leftJoin('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('users as u', 'h.created_by', '=', 'u.id')
            ->select('h.*', 'o.nama_outlet as outlet_name', 'u.nama_lengkap as creator_name', 'u.avatar as creator_avatar');

        if ($user->id_outlet != 1) {
            $query->where('h.owner_outlet_id', $user->id_outlet);
        } elseif ($request->filled('owner_outlet_id')) {
            $query->where('h.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }

        if ($request->filled('status')) {
            $query->where('h.status', $request->status);
        }
        if ($request->filled('type')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('lost_breakage_details')
                    ->whereColumn('lost_breakage_details.header_id', 'h.id')
                    ->where('lost_breakage_details.type', $request->type);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('h.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('h.date', '<=', $request->date_to);
        }

        $data = $query->orderByDesc('h.date')->orderByDesc('h.id')->paginate(20)->withQueryString();

        $headerIds = collect($data->items())->pluck('id')->toArray();
        $detailCounts = [];
        $typeSummaries = [];
        $approvalMap = [];
        $replacementSummary = [];
        if (!empty($headerIds)) {
            $rows = DB::table('lost_breakage_details')
                ->whereIn('header_id', $headerIds)
                ->select('header_id', DB::raw('COUNT(*) as cnt'), DB::raw("SUM(CASE WHEN type='lost' THEN 1 ELSE 0 END) as lost_count"), DB::raw("SUM(CASE WHEN type='breakage' THEN 1 ELSE 0 END) as breakage_count"))
                ->groupBy('header_id')
                ->get();
            foreach ($rows as $r) {
                $detailCounts[$r->header_id] = $r->cnt;
                $typeSummaries[$r->header_id] = ['lost' => $r->lost_count, 'breakage' => $r->breakage_count];
            }

            $approvalRows = DB::table('lost_breakage_approval_flows as af')
                ->join('users as u', 'af.approver_id', '=', 'u.id')
                ->whereIn('af.header_id', $headerIds)
                ->orderBy('af.approval_level')
                ->select('af.*', 'u.nama_lengkap as approver_name')
                ->get();
            foreach ($approvalRows as $af) {
                $approvalMap[$af->header_id][] = $af;
            }
            $replacementSummary = $this->replacementSummaryByHeaderId($headerIds);
        }

        $data->getCollection()->transform(function ($item) use ($detailCounts, $typeSummaries, $approvalMap, $replacementSummary) {
            $item->item_count = $detailCounts[$item->id] ?? 0;
            $item->type_summary = $typeSummaries[$item->id] ?? ['lost' => 0, 'breakage' => 0];
            $item->approval_flows = $approvalMap[$item->id] ?? [];
            $item->replacement_summary = $replacementSummary[$item->id] ?? 'none';
            return $item;
        });

        $outlets = [];
        if ($user->id_outlet == 1) {
            $outlets = DB::table('tbl_data_outlet')->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')->get();
        }

        return inertia('LostBreakage/Report', [
            'data' => $data,
            'outlets' => $outlets,
            'filters' => $request->only(['outlet_id', 'status', 'type', 'date_from', 'date_to']),
        ]);
    }

    public function reportDetails($id)
    {
        $details = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.header_id', $id)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->get();

        $this->hydrateDetailsWithReplacements($details);

        return response()->json(['success' => true, 'details' => $details]);
    }

    public function assetItemsJson(Request $request)
    {
        $items = $this->getAssetItems();
        if ($request->filled('search')) {
            $q = strtolower($request->search);
            $items = $items->filter(function ($item) use ($q) {
                return str_contains(strtolower($item->name), $q) || str_contains(strtolower($item->sku ?? ''), $q);
            })->values();
        }
        $limit = min(max((int) $request->input('limit', 80), 1), 200);

        return response()->json(['items' => $items->take($limit)->values()]);
    }

    public function storeReplacement(Request $request, $headerId, $detailId)
    {
        if (!$this->lostBreakageReplacementsTableExists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel penggantian belum dibuat. Jalankan database/sql/lost_breakage_replacements.sql',
            ], 503);
        }

        $request->validate([
            'qty_replaced'          => 'required|numeric|min:0.01',
            'unit_id'               => 'required|exists:units,id',
            'replacement_item_id'   => 'nullable|integer|exists:items,id',
            'note'                  => 'nullable|string|max:2000',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $user = auth()->user();

        $header = DB::table('lost_breakage_headers')->where('id', $headerId)->first();
        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Header tidak ditemukan'], 404);
        }
        if ($header->status !== 'APPROVED') {
            return response()->json(['success' => false, 'message' => 'Penggantian hanya untuk dokumen berstatus Disetujui'], 422);
        }
        if ($user && (int) $user->id_outlet !== 1 && (int) $header->owner_outlet_id !== (int) $user->id_outlet) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses'], 403);
        }

        $detail = DB::table('lost_breakage_details')
            ->where('id', $detailId)
            ->where('header_id', $headerId)
            ->first();
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'Baris item tidak ditemukan'], 404);
        }
        if ((int) $request->unit_id !== (int) $detail->unit_id) {
            return response()->json(['success' => false, 'message' => 'Unit harus sama dengan unit pada baris Lost & Breakage'], 422);
        }

        $rawReplacement = $request->input('replacement_item_id');
        $replacementItemId = null;
        if ($rawReplacement !== null && $rawReplacement !== '') {
            $replacementItemId = (int) $rawReplacement;
            if ($replacementItemId === (int) $detail->item_id) {
                $replacementItemId = null;
            } elseif (!$this->isAssetItemId($replacementItemId)) {
                return response()->json(['success' => false, 'message' => 'Barang pengganti harus item asset aktif'], 422);
            }
        }

        $currentSum = (float) DB::table('lost_breakage_replacements')->where('detail_id', $detailId)->sum('qty_replaced');
        $qtyLine = (float) $detail->qty;
        $add = (float) $request->qty_replaced;
        $remaining = $qtyLine - $currentSum;
        if ($add > $remaining + 1e-6) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah penggantian melebihi sisa (tersisa ' . max(0, round($remaining, 4)) . ').',
            ], 422);
        }

        try {
            DB::beginTransaction();
            DB::table('lost_breakage_replacements')->insert([
                'detail_id'             => $detailId,
                'qty_replaced'          => $add,
                'unit_id'               => (int) $request->unit_id,
                'replacement_item_id' => $replacementItemId,
                'note'                  => $request->note,
                'replaced_by'           => $userId,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $detailRow = DB::table('lost_breakage_details as d')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->join('units as u', 'd.unit_id', '=', 'u.id')
            ->where('d.id', $detailId)
            ->select('d.*', 'i.name as item_name', 'u.name as unit_name')
            ->first();
        $coll = collect([$detailRow]);
        $this->hydrateDetailsWithReplacements($coll);

        return response()->json([
            'success' => true,
            'message' => 'Penggantian tercatat.',
            'detail'  => $coll->first(),
        ]);
    }

    public function replacementBacklog(Request $request)
    {
        $user = auth()->user();
        $service = app(LostBreakageReplacementService::class);

        $filters = $request->only(['search', 'owner_outlet_id', 'outlet_id', 'type', 'date_from', 'date_to']);
        $rows = $service->pendingDetailRows($filters, $user);

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('LostBreakage/ReplacementBacklog', [
            'rows' => $rows,
            'filters' => $filters,
            'outlets' => $outlets,
            'isAdmin' => (int) ($user->id_outlet ?? 0) === 1,
            'prIntegrationReady' => $service->prLinesTableExists() && $service->replacementsTableExists(),
        ]);
    }

    public function preparePrFromBacklog(Request $request)
    {
        $request->validate([
            'detail_ids' => 'required|array|min:1',
            'detail_ids.*' => 'integer|min:1',
        ]);

        $service = app(LostBreakageReplacementService::class);
        if (!$service->prLinesTableExists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jalankan database/sql/lost_breakage_pr_integration.sql terlebih dahulu.',
            ], 503);
        }

        try {
            $prefill = $service->buildPrPrefill($request->detail_ids, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $key = 'lb_pr_prefill_' . Auth::id() . '_' . uniqid('', true);
        session([$key => $prefill]);

        return response()->json([
            'success' => true,
            'redirect_url' => '/purchase-requisitions/create?mode=pr_assets&lb_prefill=' . urlencode($key),
        ]);
    }

    public function exportReport(Request $request)
    {
        $user = auth()->user();

        if (!$request->filled('date_from') || !$request->filled('date_to')) {
            return redirect()->back()->with('error', 'Filter tanggal wajib diisi untuk export.');
        }

        $outletId = ($user->id_outlet != 1) ? null : $request->input('outlet_id');
        $export = new LostBreakageExport(
            $outletId,
            $request->input('status'),
            $request->input('type'),
            $request->date_from,
            $request->date_to,
            $user->id_outlet
        );

        $fileName = 'Lost_Breakage_Report_' . $request->date_from . '_' . $request->date_to . '.xlsx';
        return Excel::download($export, $fileName);
    }
}
