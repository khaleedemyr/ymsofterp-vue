<?php

namespace App\Http\Controllers;

use App\Models\MarcommTracker;
use App\Models\MarcommTrackerApprovalFlow;
use App\Models\User;
use App\Support\PendingApprovalCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MarcommTrackerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $jobCategory = trim((string) $request->get('job_category', ''));
        $jobStatus = trim((string) $request->get('job_status', ''));
        $workflowStatus = trim((string) $request->get('workflow_status', ''));
        $records = $this->visibleQuery()
            ->with([
                'pic:id,nama_lengkap,division_id',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('task', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('pic', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->when($jobCategory !== '', fn ($q) => $q->where('job_category', $jobCategory))
            ->when($jobStatus !== '', fn ($q) => $q->where('job_status', $jobStatus))
            ->when($workflowStatus !== '', fn ($q) => $q->where('workflow_status', $workflowStatus))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('MarcommTracker/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'job_category' => $jobCategory,
                'job_status' => $jobStatus,
                'workflow_status' => $workflowStatus,
            ],
            'jobCategories' => MarcommTracker::JOB_CATEGORIES,
            'jobStatuses' => MarcommTracker::JOB_STATUSES,
            'workflowStatuses' => MarcommTracker::WORKFLOW_STATUSES,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $jobCategory = trim((string) $request->get('job_category', ''));
        $picUserId = $request->get('pic_user_id');

        $base = $this->visibleQuery()
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($jobCategory !== '', fn ($q) => $q->where('job_category', $jobCategory))
            ->when($picUserId, fn ($q) => $q->where('pic_user_id', (int) $picUserId));

        $total = (clone $base)->count();
        $byWorkflow = (clone $base)
            ->select('workflow_status', DB::raw('COUNT(*) as total'))
            ->groupBy('workflow_status')
            ->pluck('total', 'workflow_status')
            ->all();

        $byJobStatus = (clone $base)
            ->select('job_status', DB::raw('COUNT(*) as total'))
            ->groupBy('job_status')
            ->pluck('total', 'job_status')
            ->all();

        $byCategory = (clone $base)
            ->select('job_category', DB::raw('COUNT(*) as total'))
            ->groupBy('job_category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->job_category,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();

        $picRows = (clone $base)
            ->select('pic_user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('pic_user_id')
            ->orderByDesc('total')
            ->get();

        $picNames = User::query()
            ->whereIn('id', $picRows->pluck('pic_user_id')->filter()->all())
            ->pluck('nama_lengkap', 'id');

        $byPic = $picRows->map(function ($r) use ($base, $picNames) {
            $picBase = (clone $base)->where('pic_user_id', $r->pic_user_id);
            $approved = (clone $picBase)->where('workflow_status', 'approved')->count();
            $submitted = (clone $picBase)->where('workflow_status', 'submitted')->count();
            $overdue = (clone $picBase)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->where('workflow_status', '!=', 'approved')
                ->count();

            return [
                'pic_user_id' => $r->pic_user_id,
                'pic_name' => $picNames[$r->pic_user_id] ?? 'Unknown',
                'total' => (int) $r->total,
                'approved' => $approved,
                'submitted' => $submitted,
                'overdue' => $overdue,
                'progress_pct' => $r->total > 0 ? round(($approved / $r->total) * 100, 1) : 0,
            ];
        })->values()->all();

        $overdueCount = (clone $base)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->count();

        $dueSoonCount = (clone $base)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->count();

        $approved = (int) ($byWorkflow['approved'] ?? 0);
        $submitted = (int) ($byWorkflow['submitted'] ?? 0);
        $draft = (int) ($byWorkflow['draft'] ?? 0);
        $rejected = (int) ($byWorkflow['rejected'] ?? 0);
        $completionRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;

        $trend = (clone $base)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();

        $recentTasks = (clone $base)
            ->with(['pic:id,nama_lengkap'])
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'number', 'job_category', 'pic_user_id', 'due_date', 'job_status', 'workflow_status', 'created_at']);

        $overdueTasks = (clone $base)
            ->with(['pic:id,nama_lengkap'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->orderBy('due_date')
            ->limit(15)
            ->get(['id', 'number', 'job_category', 'pic_user_id', 'due_date', 'job_status', 'workflow_status']);

        $jobStatusBreakdown = collect(MarcommTracker::JOB_STATUSES)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'total' => (int) ($byJobStatus[$key] ?? 0),
        ])->values()->all();

        return Inertia::render('MarcommTracker/Dashboard', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'job_category' => $jobCategory,
                'pic_user_id' => $picUserId ? (int) $picUserId : '',
            ],
            'jobCategories' => MarcommTracker::JOB_CATEGORIES,
            'jobStatuses' => MarcommTracker::JOB_STATUSES,
            'workflowStatuses' => MarcommTracker::WORKFLOW_STATUSES,
            'picUsers' => $this->picUsers(),
            'summary' => [
                'total' => $total,
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'overdue' => $overdueCount,
                'due_soon' => $dueSoonCount,
                'completion_rate' => $completionRate,
            ],
            'byCategory' => $byCategory,
            'byJobStatus' => $jobStatusBreakdown,
            'byPic' => $byPic,
            'trend' => $trend,
            'recentTasks' => $recentTasks,
            'overdueTasks' => $overdueTasks,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('MarcommTracker/Create', [
            'jobCategories' => MarcommTracker::JOB_CATEGORIES,
            'jobStatuses' => MarcommTracker::JOB_STATUSES,
            'picUsers' => $this->picUsers(),
        ]);
    }

    public function show($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        $canApprove = (bool) $this->resolveCurrentApprovalFlow($record, $user);
        $canEdit = $this->canEdit($record, $user);

        return Inertia::render('MarcommTracker/Show', [
            'record' => $record,
            'canApprove' => $canApprove,
            'canEdit' => $canEdit,
            'jobCategories' => MarcommTracker::JOB_CATEGORIES,
            'jobStatuses' => MarcommTracker::JOB_STATUSES,
            'picUsers' => $this->picUsers(),
        ]);
    }

    public function edit($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit tracker ini.');
        }

        return Inertia::render('MarcommTracker/Edit', [
            'record' => $record,
            'jobCategories' => MarcommTracker::JOB_CATEGORIES,
            'jobStatuses' => MarcommTracker::JOB_STATUSES,
            'picUsers' => $this->picUsers(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $user = auth()->user();
        $approverIds = $this->normalizeApproverIds($validated['approvers'] ?? []);

        DB::beginTransaction();
        try {
            $hasApprovers = $approverIds !== [];
            $tracker = MarcommTracker::create([
                'number' => $this->generateNumber(),
                'job_category' => $validated['job_category'],
                'pic_user_id' => $validated['pic_user_id'],
                'task' => $validated['task'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'job_status' => $validated['job_status'],
                'status_remarks' => $this->normalizeStatusRemarks($validated['status_remarks'] ?? []),
                'output_links' => $this->normalizeOutputLinks($validated['output_links'] ?? []),
                'remarks' => $validated['remarks'] ?? null,
                'workflow_status' => $hasApprovers ? 'submitted' : 'draft',
                'created_by' => $user->id,
                'submitted_at' => $hasApprovers ? now() : null,
            ]);

            foreach ($approverIds as $index => $approverId) {
                MarcommTrackerApprovalFlow::create([
                    'marcomm_tracker_id' => $tracker->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();
            $this->clearCaches($tracker, $approverIds, [$user->id]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['job_category' => $e->getMessage()])->withInput();
        }

        return redirect()->route('marcomm-trackers.show', $tracker->id)
            ->with('success', $hasApprovers
                ? 'Marcomm Tracker disimpan dan Submitted.'
                : 'Marcomm Tracker disimpan sebagai Draft.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit tracker ini.');
        }

        $validated = $this->validatePayload($request, false);
        $approverIds = $this->normalizeApproverIds($validated['approvers'] ?? []);

        DB::beginTransaction();
        try {
            $wasDraft = $record->workflow_status === 'draft';
            $hasApprovers = $approverIds !== [];
            $becomesSubmitted = $wasDraft && $hasApprovers;

            $record->update([
                'job_category' => $validated['job_category'],
                'pic_user_id' => $validated['pic_user_id'],
                'task' => $validated['task'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'job_status' => $validated['job_status'],
                'status_remarks' => $this->normalizeStatusRemarks($validated['status_remarks'] ?? []),
                'output_links' => $this->normalizeOutputLinks($validated['output_links'] ?? []),
                'remarks' => $validated['remarks'] ?? null,
                'workflow_status' => $becomesSubmitted ? 'submitted' : $record->workflow_status,
                'submitted_at' => $becomesSubmitted ? now() : $record->submitted_at,
            ]);

            // Only allow changing approvers while draft / before any approval happened
            if (in_array($record->workflow_status, ['draft', 'submitted'], true)
                && $record->approvalFlows()->where('status', '!=', 'PENDING')->doesntExist()) {
                $record->approvalFlows()->delete();
                foreach ($approverIds as $index => $approverId) {
                    MarcommTrackerApprovalFlow::create([
                        'marcomm_tracker_id' => $record->id,
                        'approver_id' => $approverId,
                        'approval_level' => $index + 1,
                        'status' => 'PENDING',
                    ]);
                }
                if ($hasApprovers && $record->workflow_status === 'draft') {
                    $record->update([
                        'workflow_status' => 'submitted',
                        'submitted_at' => now(),
                    ]);
                }
            }

            DB::commit();
            $this->clearCaches($record->fresh('approvalFlows'), $approverIds, [$user->id]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['job_category' => $e->getMessage()])->withInput();
        }

        return redirect()->route('marcomm-trackers.show', $record->id)
            ->with('success', 'Marcomm Tracker berhasil diupdate.');
    }

    public function destroy($id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canDelete($record, $user)) {
            abort(403);
        }

        $approverIds = $record->approvalFlows()->pluck('approver_id')->all();
        $createdBy = $record->created_by;
        $record->delete();
        $this->clearCaches(null, $approverIds, [$user->id, $createdBy]);

        return redirect()->route('marcomm-trackers.index')->with('success', 'Marcomm Tracker dihapus.');
    }

    public function getPicUsers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $users = User::query()
            ->where('users.status', 'A')
            ->whereIn('users.division_id', [7, 17])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->get(['users.id', 'users.nama_lengkap', 'users.email', 'users.division_id']);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->where('users.status', 'A')
            ->where('users.id', '!=', auth()->id())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(40)
            ->get([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function getPendingApprovals()
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'trackers' => []], 401);
        }

        $isSuperadmin = $this->isSuperadmin($currentUser);

        $query = MarcommTracker::query()
            ->where('workflow_status', 'submitted')
            ->whereHas('approvalFlows', function ($q) use ($currentUser, $isSuperadmin) {
                $q->where('status', 'PENDING');
                if (! $isSuperadmin) {
                    $q->where('approver_id', $currentUser->id);
                }
            })
            ->with([
                'pic:id,nama_lengkap',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        $pending = $query->get();

        if (! $isSuperadmin) {
            $pending = $pending->filter(function (MarcommTracker $item) use ($currentUser) {
                $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();

                return $next && (int) $next->approver_id === (int) $currentUser->id;
            })->values();
        }

        $pending = $pending->map(function (MarcommTracker $item) {
            $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();
            $item->approver_name = $next?->approver?->nama_lengkap;
            $item->approval_level = $next?->approval_level;
            $item->job_status_label = $item->jobStatusLabel();

            return $item;
        });

        return response()->json([
            'success' => true,
            'trackers' => $pending->values(),
        ]);
    }

    public function getApprovalDetails($id)
    {
        $user = auth()->user();
        $record = MarcommTracker::with([
            'pic:id,nama_lengkap,division_id',
            'creator:id,nama_lengkap',
            'approvalFlows.approver:id,nama_lengkap',
        ])->findOrFail($id);

        $canApprove = false;
        if ($user && $record->workflow_status === 'submitted') {
            $canApprove = (bool) $this->resolveCurrentApprovalFlow($record, $user);
        }
        $record->setAttribute('can_approve', $canApprove);
        $record->setAttribute('job_status_label', $record->jobStatusLabel());

        return response()->json([
            'success' => true,
            'tracker' => $record,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notes = $request->input('notes') ?? $request->input('comments');

        try {
            DB::beginTransaction();

            $tracker = MarcommTracker::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($tracker->workflow_status !== 'submitted') {
                throw new \RuntimeException('Tracker tidak dalam status Submitted.');
            }

            $flow = $this->resolveCurrentApprovalFlow($tracker, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk tracker ini.');
            }

            $flow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            $stillPending = $tracker->approvalFlows()
                ->where('status', 'PENDING')
                ->exists();

            if (! $stillPending) {
                $tracker->update([
                    'workflow_status' => 'approved',
                    'approved_at' => now(),
                ]);
            }

            DB::commit();
            $this->clearCaches($tracker->fresh('approvalFlows'), [], [$user->id]);

            return response()->json([
                'success' => true,
                'message' => $stillPending
                    ? 'Approval berhasil. Menunggu approver berikutnya.'
                    : 'Marcomm Tracker fully Approved.',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal approve: '.$e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'comments' => 'nullable|string|max:1000',
        ]);
        $notes = $validated['rejection_reason'] ?? $validated['notes'] ?? $validated['comments'] ?? null;

        try {
            DB::beginTransaction();

            $tracker = MarcommTracker::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($tracker->workflow_status !== 'submitted') {
                throw new \RuntimeException('Tracker tidak dalam status Submitted.');
            }

            $flow = $this->resolveCurrentApprovalFlow($tracker, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk tracker ini.');
            }

            $flow->update([
                'status' => 'REJECTED',
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            $tracker->update([
                'workflow_status' => 'rejected',
            ]);

            DB::commit();
            $this->clearCaches($tracker->fresh('approvalFlows'), [], [$user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Marcomm Tracker ditolak.',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal reject: '.$e->getMessage()], 500);
        }
    }

    private function validatePayload(Request $request, bool $requireApproversOptional = true): array
    {
        return $request->validate([
            'job_category' => 'required|string|in:'.implode(',', MarcommTracker::JOB_CATEGORIES),
            'pic_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $ok = User::where('id', $value)->whereIn('division_id', [7, 17])->exists();
                    if (! $ok) {
                        $fail('PIC harus user dengan division_id 7 atau 17.');
                    }
                },
            ],
            'task' => 'nullable|string',
            'due_date' => 'nullable|date',
            'job_status' => 'required|string|in:'.implode(',', array_keys(MarcommTracker::JOB_STATUSES)),
            'status_remarks' => 'nullable|array',
            'status_remarks.on_progress' => 'nullable|string|max:5000',
            'status_remarks.rev_1' => 'nullable|string|max:5000',
            'status_remarks.rev_2' => 'nullable|string|max:5000',
            'output_links' => 'nullable|array',
            'output_links.*' => 'nullable|string|max:2000',
            'remarks' => 'nullable|string|max:5000',
            'approvers' => 'nullable|array',
            'approvers.*' => 'integer|exists:users,id',
        ]);
    }

    private function normalizeStatusRemarks(array $remarks): array
    {
        return [
            'on_progress' => (string) ($remarks['on_progress'] ?? ''),
            'rev_1' => (string) ($remarks['rev_1'] ?? ''),
            'rev_2' => (string) ($remarks['rev_2'] ?? ''),
        ];
    }

    private function normalizeOutputLinks(array $links): array
    {
        return collect($links)
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '')
            ->values()
            ->all();
    }

    private function normalizeApproverIds(array $approvers): array
    {
        return collect($approvers)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function picUsers()
    {
        return User::query()
            ->where('status', 'A')
            ->whereIn('division_id', [7, 17])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'division_id']);
    }

    private function generateNumber(): string
    {
        $prefix = 'MCT-'.now()->format('Ym').'-';
        $last = MarcommTracker::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function visibleQuery()
    {
        $user = auth()->user();
        $isSuperadmin = $this->isSuperadmin($user);

        return MarcommTracker::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('created_by', $user->id)
                        ->orWhere('pic_user_id', $user->id)
                        ->orWhereHas('approvalFlows', fn ($f) => $f->where('approver_id', $user->id));
                });
            });
    }

    private function findVisible($id): MarcommTracker
    {
        return $this->visibleQuery()
            ->with([
                'pic:id,nama_lengkap,division_id,email',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap,email',
            ])
            ->where('id', $id)
            ->firstOrFail();
    }

    private function canEdit(MarcommTracker $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id
            || (int) $record->pic_user_id === (int) $user->id;
    }

    private function canDelete(MarcommTracker $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id;
    }

    private function resolveCurrentApprovalFlow(MarcommTracker $tracker, $user): ?MarcommTrackerApprovalFlow
    {
        if (! $user || $tracker->workflow_status !== 'submitted') {
            return null;
        }

        $next = $tracker->approvalFlows()
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if (! $next) {
            return null;
        }

        if ($this->isSuperadmin($user) || (int) $next->approver_id === (int) $user->id) {
            return $next;
        }

        return null;
    }

    private function isSuperadmin($user): bool
    {
        if (! $user) {
            return false;
        }

        $roleName = strtolower((string) ($user->role_name ?? $user->role ?? ''));
        if (in_array($roleName, ['superadmin', 'super admin', 'administrator'], true)) {
            return true;
        }

        try {
            if (method_exists($user, 'roles')) {
                return $user->roles()->whereIn('name', ['superadmin', 'Superadmin', 'Super Admin'])->exists();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return (int) ($user->id ?? 0) === 1;
    }

    private function clearCaches(?MarcommTracker $tracker, array $extraUserIds = [], array $more = []): void
    {
        $ids = $extraUserIds;
        if ($tracker) {
            $ids[] = $tracker->created_by;
            $ids = array_merge($ids, $tracker->approvalFlows->pluck('approver_id')->all());
        }
        $ids = array_merge($ids, $more);
        PendingApprovalCache::forgetForUsers($ids);
    }
}
