<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\NpdPlanReport;
use App\Models\NpdPlanReportApprovalFlow;
use App\Models\NpdPlanReportItem;
use App\Models\Outlet;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NpdPlanReportController extends Controller
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    public function index(Request $request): Response
    {
        $query = NpdPlanReport::query()
            ->with(['creator:id,nama_lengkap'])
            ->withCount('items')
            ->orderByDesc('report_month')
            ->orderByDesc('id');

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            $query->whereDate('report_month', '>=', $month.'-01')
                ->whereDate('report_month', '<=', date('Y-m-t', strtotime($month.'-01')));
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->outlet_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate((int) $request->get('perPage', 15))->withQueryString();

        return Inertia::render('NpdPlanReport/Index', [
            'reports' => $reports,
            'filters' => [
                'search' => $request->get('search', ''),
                'month' => $request->get('month', ''),
                'outlet_id' => $request->get('outlet_id', ''),
                'status' => $request->get('status', ''),
                'perPage' => $request->get('perPage', 15),
            ],
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('NpdPlanReport/Form', array_merge(
            $this->formOptions(),
            ['record' => null]
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, true);

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $report = NpdPlanReport::create([
                'number' => $this->generateNumber($validated['report_month']),
                'report_month' => $validated['report_month'].'-01',
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'status' => 'submitted',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($report, $validated['items']);
            $this->syncApprovalFlows($report, $validated['approvers']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-plan-report.show', $report->id)
            ->with('success', 'NPD Plan & Report berhasil disimpan dan diajukan untuk approval.');
    }

    public function show(NpdPlanReport $npdPlanReport): Response
    {
        $npdPlanReport->load([
            'items',
            'creator:id,nama_lengkap',
            'approvalFlows.approver:id,nama_lengkap',
        ]);

        return Inertia::render('NpdPlanReport/Show', [
            'record' => $npdPlanReport,
            'purposeOptions' => $this->purposeOptions(),
            'canEdit' => in_array($npdPlanReport->status, ['rejected', 'requires_revision'], true) && $this->canManage($npdPlanReport),
            'canDelete' => in_array($npdPlanReport->status, ['rejected', 'requires_revision'], true) && $this->canManage($npdPlanReport),
            'canApprove' => $this->canApprove($npdPlanReport),
            'currentApprovalFlow' => $this->currentApprovalFlow($npdPlanReport),
        ]);
    }

    public function edit(NpdPlanReport $npdPlanReport): Response
    {
        $this->ensureRejectedEditable($npdPlanReport);
        $npdPlanReport->load('items');

        return Inertia::render('NpdPlanReport/Form', array_merge(
            $this->formOptions(),
            ['record' => $npdPlanReport]
        ));
    }

    public function update(Request $request, NpdPlanReport $npdPlanReport)
    {
        $this->ensureRejectedEditable($npdPlanReport);
        $validated = $this->validatePayload($request, true);

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $npdPlanReport->update([
                'report_month' => $validated['report_month'].'-01',
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'notes' => $validated['notes'] ?? null,
                'status' => 'submitted',
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($npdPlanReport, $validated['items']);
            $this->syncApprovalFlows($npdPlanReport, $validated['approvers']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-plan-report.show', $npdPlanReport->id)
            ->with('success', 'NPD Plan & Report berhasil diperbarui dan diajukan ulang untuk approval.');
    }

    public function destroy(NpdPlanReport $npdPlanReport)
    {
        $this->ensureRejectedEditable($npdPlanReport);
        $npdPlanReport->delete();

        return redirect()
            ->route('npd-plan-report.index')
            ->with('success', 'NPD Plan & Report berhasil dihapus.');
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(20)
            ->get([
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function submitForApproval(Request $request, NpdPlanReport $npdPlanReport)
    {
        if (! in_array($npdPlanReport->status, ['rejected', 'requires_revision'], true)) {
            return response()->json(['success' => false, 'message' => 'Hanya report ditolak/perlu revisi yang dapat diajukan ulang.'], 400);
        }

        if (! $this->canManage($npdPlanReport)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses.'], 403);
        }

        $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $this->syncApprovalFlows($npdPlanReport, $request->approvers);
            $npdPlanReport->update(['status' => 'submitted', 'updated_by' => Auth::id()]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Report berhasil diajukan ulang untuk approval.']);
    }

    public function approve(Request $request, NpdPlanReport $npdPlanReport)
    {
        $flow = $this->resolveApprovalFlow($npdPlanReport);
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak approval untuk report ini.'], 403);
        }

        $request->validate([
            'action' => 'nullable|in:approve,reject,requires_revision',
            'approved' => 'nullable|boolean',
            'comments' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
        ]);

        $comments = trim((string) ($request->input('comments') ?? $request->input('comment') ?? ''));
        $action = $request->input('action');
        if (! $action) {
            $action = $request->boolean('approved') ? 'approve' : 'reject';
        }

        if (in_array($action, ['reject', 'requires_revision'], true) && $comments === '') {
            return response()->json([
                'success' => false,
                'message' => $action === 'requires_revision'
                    ? 'Catatan revisi wajib diisi.'
                    : 'Alasan penolakan wajib diisi.',
            ], 422);
        }

        $isSuperadmin = $this->isSuperAdmin();

        DB::beginTransaction();
        try {
            $flowUpdate = [
                'comments' => $comments !== '' ? $comments : null,
            ];

            if ($action === 'approve') {
                $flowUpdate['status'] = 'APPROVED';
                $flowUpdate['approved_at'] = now();
                $flowUpdate['rejected_at'] = null;
            } elseif ($action === 'requires_revision') {
                $flowUpdate['status'] = 'REQUIRES_REVISION';
                $flowUpdate['approved_at'] = null;
                $flowUpdate['rejected_at'] = now();
            } else {
                $flowUpdate['status'] = 'REJECTED';
                $flowUpdate['approved_at'] = null;
                $flowUpdate['rejected_at'] = now();
            }

            $flow->update($flowUpdate);

            if ($action === 'approve') {
                $pending = NpdPlanReportApprovalFlow::where('report_id', $npdPlanReport->id)
                    ->where('status', 'PENDING')
                    ->count();

                if ($pending === 0) {
                    $npdPlanReport->update(['status' => 'approved', 'updated_by' => Auth::id()]);
                    $this->notifyUsers(
                        [$npdPlanReport->created_by],
                        'npd_plan_report_approved',
                        'NPD Plan & Report Disetujui',
                        "Report {$npdPlanReport->number} telah disetujui sepenuhnya.",
                        route('npd-plan-report.show', $npdPlanReport->id)
                    );
                } else {
                    $next = NpdPlanReportApprovalFlow::where('report_id', $npdPlanReport->id)
                        ->where('status', 'PENDING')
                        ->orderBy('approval_level')
                        ->first();
                    if ($next) {
                        $this->notifyUsers(
                            [$next->approver_id],
                            'npd_plan_report_approval_required',
                            'NPD Plan & Report Approval',
                            "Report {$npdPlanReport->number} menunggu approval Anda (level {$next->approval_level}).",
                            route('npd-plan-report.show', $npdPlanReport->id)
                        );
                    }
                }
            } elseif ($action === 'requires_revision') {
                $npdPlanReport->update(['status' => 'requires_revision', 'updated_by' => Auth::id()]);
                $this->notifyUsers(
                    [$npdPlanReport->created_by],
                    'npd_plan_report_requires_revision',
                    'NPD Plan & Report Perlu Revisi',
                    "Report {$npdPlanReport->number} memerlukan revisi.".($comments ? " Catatan: {$comments}" : ''),
                    route('npd-plan-report.show', $npdPlanReport->id)
                );
            } else {
                $npdPlanReport->update(['status' => 'rejected', 'updated_by' => Auth::id()]);
                $this->notifyUsers(
                    [$npdPlanReport->created_by],
                    'npd_plan_report_rejected',
                    'NPD Plan & Report Ditolak',
                    "Report {$npdPlanReport->number} ditolak.".($comments ? " Alasan: {$comments}" : ''),
                    route('npd-plan-report.show', $npdPlanReport->id)
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $messages = [
            'approve' => 'Report berhasil diapprove.',
            'reject' => 'Report berhasil ditolak (Not Approved).',
            'requires_revision' => 'Report dikembalikan untuk revisi.',
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$action] ?? 'Status approval berhasil diperbarui.',
        ]);
    }

    public function getPendingApprovals()
    {
        $userId = Auth::id();
        $isSuperadmin = $this->isSuperAdmin();

        $query = NpdPlanReport::query()
            ->whereNotIn('status', ['approved', 'rejected', 'requires_revision', 'cancelled'])
            ->whereDoesntHave('approvalFlows', fn ($q) => $q->whereIn('status', ['REJECTED', 'REQUIRES_REVISION']))
            ->whereHas('approvalFlows', fn ($q) => $q->where('status', 'PENDING'));

        if (! $isSuperadmin) {
            $query->whereHas('approvalFlows', fn ($q) => $q->where('approver_id', $userId)->where('status', 'PENDING'));
        }

        $reports = $query->with(['creator:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap'])
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (NpdPlanReport $report) => $this->isVisiblePendingForUser($report, $userId, $isSuperadmin))
            ->values()
            ->map(fn (NpdPlanReport $report) => [
                'id' => $report->id,
                'number' => $report->number,
                'report_month' => $report->report_month?->format('Y-m'),
                'outlet_name' => $report->outlet_name,
                'status' => $report->status,
                'items_count' => $report->items_count,
                'creator_name' => $report->creator?->nama_lengkap,
                'updated_at' => $report->updated_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    private function validatePayload(Request $request, bool $requireApprovers = false): array
    {
        $rules = [
            'report_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.category_id' => 'required|integer|exists:categories,id',
            'items.*.development_date' => 'nullable|date',
            'items.*.purpose' => ['required', Rule::in(['enhancement', 'new_product', 'adjustment'])],
            'items.*.proposed_launch_date' => 'nullable|date',
            'items.*.proposed_launch_outlet_ids' => 'required|array|min:1',
            'items.*.proposed_launch_outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
            'items.*.pic_user_ids' => 'nullable|array',
            'items.*.pic_user_ids.*' => 'integer|exists:users,id',
            'items.*.fb_cost' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
        ];

        if ($requireApprovers) {
            $rules['approvers'] = 'required|array|min:1';
            $rules['approvers.*'] = 'required|integer|exists:users,id';
        }

        return $request->validate($rules, [
            'items.required' => 'Tambahkan minimal satu product.',
            'items.min' => 'Tambahkan minimal satu product.',
            'items.*.category_id.required' => 'Category wajib dipilih.',
            'items.*.proposed_launch_outlet_ids.required' => 'Area/outlet launch wajib dipilih.',
            'items.*.proposed_launch_outlet_ids.min' => 'Pilih minimal satu outlet launch.',
            'approvers.required' => 'Pilih minimal satu approver.',
            'approvers.min' => 'Pilih minimal satu approver.',
        ]);
    }

    private function syncItems(NpdPlanReport $report, array $items): void
    {
        NpdPlanReportItem::where('report_id', $report->id)->delete();

        $categoryNames = Category::whereIn('id', collect($items)->pluck('category_id')->filter()->unique())
            ->pluck('name', 'id');
        $launchOutlets = Outlet::whereIn('id_outlet', collect($items)->flatMap(fn ($item) => $item['proposed_launch_outlet_ids'] ?? [])->unique())
            ->get(['id_outlet', 'nama_outlet'])
            ->keyBy('id_outlet');
        $picUsers = User::query()
            ->whereIn('users.id', collect($items)->flatMap(fn ($item) => $item['pic_user_ids'] ?? [])->unique())
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->get([
                'users.id',
                'users.nama_lengkap',
                DB::raw('j.nama_jabatan as jabatan'),
            ])
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $categoryId = (int) $item['category_id'];
            $launchOutletPayload = collect($item['proposed_launch_outlet_ids'] ?? [])
                ->map(fn ($outletId) => [
                    'id' => (int) $outletId,
                    'name' => (string) ($launchOutlets[(int) $outletId]->nama_outlet ?? ''),
                ])
                ->values()
                ->all();
            $picPayload = collect($item['pic_user_ids'] ?? [])
                ->map(fn ($userId) => [
                    'id' => (int) $userId,
                    'name' => (string) ($picUsers[(int) $userId]->nama_lengkap ?? ''),
                    'jabatan' => (string) ($picUsers[(int) $userId]->jabatan ?? ''),
                ])
                ->values()
                ->all();

            NpdPlanReportItem::create([
                'report_id' => $report->id,
                'sort_order' => $index,
                'product_name' => $item['product_name'],
                'category_id' => $categoryId,
                'category' => (string) ($categoryNames[$categoryId] ?? ''),
                'development_date' => $item['development_date'] ?? null,
                'purpose' => $item['purpose'],
                'proposed_launch_date' => $item['proposed_launch_date'] ?? null,
                'proposed_launch_area_outlet' => $launchOutletPayload,
                'pics' => $picPayload,
                'fb_cost' => (float) ($item['fb_cost'] ?? 0),
                'selling_price' => (float) ($item['selling_price'] ?? 0),
            ]);
        }
    }

    /**
     * @param  list<int>  $approverIds
     */
    private function syncApprovalFlows(NpdPlanReport $report, array $approverIds): void
    {
        NpdPlanReportApprovalFlow::where('report_id', $report->id)->delete();

        foreach ($approverIds as $index => $approverId) {
            NpdPlanReportApprovalFlow::create([
                'report_id' => $report->id,
                'approver_id' => (int) $approverId,
                'approval_level' => $index + 1,
                'status' => 'PENDING',
            ]);
        }

        $first = NpdPlanReportApprovalFlow::where('report_id', $report->id)
            ->where('approval_level', 1)
            ->first();

        if ($first) {
            $this->notifyUsers(
                [$first->approver_id],
                'npd_plan_report_approval_required',
                'NPD Plan & Report Approval',
                "Report {$report->number} menunggu approval Anda.",
                route('npd-plan-report.show', $report->id)
            );
        }
    }

    private function formOptions(): array
    {
        return [
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'launchOutlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'categories' => Category::where('show_pos', '1')->orderBy('name')->get(['id', 'name']),
            'purposeOptions' => $this->purposeOptions(),
        ];
    }

    private function generateNumber(string $reportMonth): string
    {
        $prefix = 'NPD-'.str_replace('-', '', $reportMonth).'-';
        $last = NpdPlanReport::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function purposeOptions(): array
    {
        return [
            ['value' => 'enhancement', 'label' => 'Enhancement'],
            ['value' => 'new_product', 'label' => 'New Product'],
            ['value' => 'adjustment', 'label' => 'Adjustment'],
        ];
    }

    private function isSuperAdmin(): bool
    {
        return (string) (Auth::user()?->id_role ?? '') === self::SUPERADMIN_ROLE_ID;
    }

    private function canManage(NpdPlanReport $report): bool
    {
        return $this->isSuperAdmin() || (int) Auth::id() === (int) $report->created_by;
    }

    private function ensureRejectedEditable(NpdPlanReport $report): void
    {
        if (! in_array($report->status, ['rejected', 'requires_revision'], true)) {
            abort(403, 'Report hanya dapat diubah saat status rejected atau requires revision.');
        }
        if (! $this->canManage($report)) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah report ini.');
        }
    }

    private function canApprove(NpdPlanReport $report): bool
    {
        return $this->resolveApprovalFlow($report) !== null;
    }

    private function currentApprovalFlow(NpdPlanReport $report): ?NpdPlanReportApprovalFlow
    {
        return $this->resolveApprovalFlow($report);
    }

    private function resolveApprovalFlow(NpdPlanReport $report): ?NpdPlanReportApprovalFlow
    {
        if (! in_array($report->status, ['submitted'], true)) {
            return null;
        }

        if ($this->isSuperAdmin()) {
            return NpdPlanReportApprovalFlow::where('report_id', $report->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        return NpdPlanReportApprovalFlow::where('report_id', $report->id)
            ->where('approver_id', Auth::id())
            ->where('status', 'PENDING')
            ->first();
    }

    private function isVisiblePendingForUser(NpdPlanReport $report, int $userId, bool $isSuperadmin): bool
    {
        if ($isSuperadmin) {
            return true;
        }

        $userFlow = $report->approvalFlows->first(fn ($f) => (int) $f->approver_id === $userId && $f->status === 'PENDING');
        if (! $userFlow) {
            return false;
        }

        $lowestPending = $report->approvalFlows
            ->where('status', 'PENDING')
            ->sortBy('approval_level')
            ->first();

        return $lowestPending && (int) $lowestPending->id === (int) $userFlow->id;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function notifyUsers(array $userIds, string $type, string $title, string $message, string $url): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            NotificationService::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
            ]);
        }
    }
}
