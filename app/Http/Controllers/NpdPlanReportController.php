<?php

namespace App\Http\Controllers;

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
        return Inertia::render('NpdPlanReport/Form', [
            'record' => null,
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'purposeOptions' => $this->purposeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $report = NpdPlanReport::create([
                'number' => $this->generateNumber($validated['report_month']),
                'report_month' => $validated['report_month'].'-01',
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($report, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-plan-report.show', $report->id)
            ->with('success', 'NPD Plan & Report berhasil dibuat.');
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
            'canEdit' => $npdPlanReport->status === 'draft' && $this->canManage($npdPlanReport),
            'canDelete' => $npdPlanReport->status === 'draft' && $this->canManage($npdPlanReport),
            'canSubmitApproval' => $npdPlanReport->status === 'draft' && $this->canManage($npdPlanReport),
            'canApprove' => $this->canApprove($npdPlanReport),
            'currentApprovalFlow' => $this->currentApprovalFlow($npdPlanReport),
        ]);
    }

    public function edit(NpdPlanReport $npdPlanReport): Response
    {
        $this->ensureDraftEditable($npdPlanReport);
        $npdPlanReport->load('items');

        return Inertia::render('NpdPlanReport/Form', [
            'record' => $npdPlanReport,
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'purposeOptions' => $this->purposeOptions(),
        ]);
    }

    public function update(Request $request, NpdPlanReport $npdPlanReport)
    {
        $this->ensureDraftEditable($npdPlanReport);
        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $npdPlanReport->update([
                'report_month' => $validated['report_month'].'-01',
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($npdPlanReport, $validated['items']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('npd-plan-report.show', $npdPlanReport->id)
            ->with('success', 'NPD Plan & Report berhasil diperbarui.');
    }

    public function destroy(NpdPlanReport $npdPlanReport)
    {
        $this->ensureDraftEditable($npdPlanReport);
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
        if ($npdPlanReport->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya draft yang dapat diajukan untuk approval.'], 400);
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
            NpdPlanReportApprovalFlow::where('report_id', $npdPlanReport->id)->delete();

            foreach ($request->approvers as $index => $approverId) {
                NpdPlanReportApprovalFlow::create([
                    'report_id' => $npdPlanReport->id,
                    'approver_id' => (int) $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            $npdPlanReport->update(['status' => 'submitted', 'updated_by' => Auth::id()]);

            $first = NpdPlanReportApprovalFlow::where('report_id', $npdPlanReport->id)
                ->where('approval_level', 1)
                ->first();

            if ($first) {
                $this->notifyUsers(
                    [$first->approver_id],
                    'npd_plan_report_approval_required',
                    'NPD Plan & Report Approval',
                    "Report {$npdPlanReport->number} menunggu approval Anda.",
                    route('npd-plan-report.show', $npdPlanReport->id)
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Report berhasil diajukan untuk approval.']);
    }

    public function approve(Request $request, NpdPlanReport $npdPlanReport)
    {
        $flow = $this->resolveApprovalFlow($npdPlanReport);
        if (! $flow) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak approval untuk report ini.'], 403);
        }

        $request->validate([
            'approved' => 'required|boolean',
            'comments' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
        ]);

        $comments = $request->input('comments') ?? $request->input('comment');
        $isSuperadmin = $this->isSuperAdmin();

        DB::beginTransaction();
        try {
            $update = [
                'status' => $request->boolean('approved') ? 'APPROVED' : 'REJECTED',
                'approved_at' => $request->boolean('approved') ? now() : null,
                'rejected_at' => $request->boolean('approved') ? null : now(),
                'comments' => $comments,
            ];
            if ($isSuperadmin) {
                $update['approver_id'] = Auth::id();
            }
            $flow->update($update);

            if ($request->boolean('approved')) {
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

        return response()->json([
            'success' => true,
            'message' => $request->boolean('approved') ? 'Report berhasil diapprove.' : 'Report berhasil ditolak.',
        ]);
    }

    public function getPendingApprovals()
    {
        $userId = Auth::id();
        $isSuperadmin = $this->isSuperAdmin();

        $query = NpdPlanReport::query()
            ->whereNotIn('status', ['approved', 'rejected', 'cancelled'])
            ->whereDoesntHave('approvalFlows', fn ($q) => $q->where('status', 'REJECTED'))
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

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'report_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.category' => 'nullable|string|max:255',
            'items.*.development_date' => 'nullable|date',
            'items.*.purpose' => ['required', Rule::in(['enhancement', 'new_product', 'adjustment'])],
            'items.*.proposed_launch_date' => 'nullable|date',
            'items.*.proposed_launch_area_outlet' => 'nullable|string|max:255',
            'items.*.fb_cost' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
        ], [
            'items.required' => 'Tambahkan minimal satu product.',
            'items.min' => 'Tambahkan minimal satu product.',
        ]);
    }

    private function syncItems(NpdPlanReport $report, array $items): void
    {
        NpdPlanReportItem::where('report_id', $report->id)->delete();

        foreach ($items as $index => $item) {
            NpdPlanReportItem::create([
                'report_id' => $report->id,
                'sort_order' => $index,
                'product_name' => $item['product_name'],
                'category' => $item['category'] ?? null,
                'development_date' => $item['development_date'] ?? null,
                'purpose' => $item['purpose'],
                'proposed_launch_date' => $item['proposed_launch_date'] ?? null,
                'proposed_launch_area_outlet' => $item['proposed_launch_area_outlet'] ?? null,
                'fb_cost' => (float) ($item['fb_cost'] ?? 0),
                'selling_price' => (float) ($item['selling_price'] ?? 0),
            ]);
        }
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

    private function ensureDraftEditable(NpdPlanReport $report): void
    {
        if ($report->status !== 'draft') {
            abort(403, 'Report hanya dapat diubah saat status draft.');
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
