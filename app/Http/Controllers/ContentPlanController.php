<?php

namespace App\Http\Controllers;

use App\Models\ContentPlan;
use App\Models\ContentPlanApprovalFlow;
use App\Models\User;
use App\Support\PendingApprovalCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $platform = trim((string) $request->get('platform', ''));
        $jobStatus = trim((string) $request->get('job_status', ''));
        $workflowStatus = trim((string) $request->get('workflow_status', ''));

        $records = $this->visibleQuery()
            ->with([
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('caption', 'like', "%{$search}%")
                        ->orWhere('objective', 'like', "%{$search}%")
                        ->orWhere('brief', 'like', "%{$search}%")
                        ->orWhere('pillar', 'like', "%{$search}%");
                });
            })
            ->when($platform !== '', fn ($q) => $q->where('platform', $platform))
            ->when($jobStatus !== '', fn ($q) => $q->where('job_status', $jobStatus))
            ->when($workflowStatus !== '', fn ($q) => $q->where('workflow_status', $workflowStatus))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ContentPlan/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'platform' => $platform,
                'job_status' => $jobStatus,
                'workflow_status' => $workflowStatus,
            ],
            'platforms' => ContentPlan::PLATFORMS,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
            'workflowStatuses' => ContentPlan::WORKFLOW_STATUSES,
            'canDelete' => true,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ContentPlan/Create', $this->formOptions());
    }

    public function show($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();

        return Inertia::render('ContentPlan/Show', [
            'record' => $record,
            'canApprove' => (bool) $this->resolveCurrentApprovalFlow($record, $user),
            'canEdit' => $this->canEdit($record, $user),
            'canDelete' => $this->canDelete($record, $user),
            'jobStatuses' => ContentPlan::JOB_STATUSES,
            'workflowStatuses' => ContentPlan::WORKFLOW_STATUSES,
        ]);
    }

    public function edit($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Content Plan ini.');
        }

        return Inertia::render('ContentPlan/Edit', array_merge($this->formOptions(), [
            'record' => $record,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $user = auth()->user();
        $approverIds = $this->normalizeApproverIds($validated['approvers'] ?? []);

        DB::beginTransaction();
        try {
            $hasApprovers = $approverIds !== [];
            $plan = ContentPlan::create($this->payloadFromValidated($validated, $user, $hasApprovers, true));

            foreach ($approverIds as $index => $approverId) {
                ContentPlanApprovalFlow::create([
                    'content_plan_id' => $plan->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();
            $this->clearCaches($plan, $approverIds, [$user->id]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['platform' => $e->getMessage()])->withInput();
        }

        return redirect()->route('content-plans.show', $plan->id)
            ->with('success', $hasApprovers
                ? 'Content Plan disimpan dan Submitted.'
                : 'Content Plan disimpan sebagai Draft.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEdit($record, $user)) {
            abort(403, 'Tidak dapat mengedit Content Plan ini.');
        }

        $validated = $this->validatePayload($request);
        $approverIds = $this->normalizeApproverIds($validated['approvers'] ?? []);

        DB::beginTransaction();
        try {
            $wasDraft = $record->workflow_status === 'draft';
            $hasApprovers = $approverIds !== [];
            $becomesSubmitted = $wasDraft && $hasApprovers;

            $data = $this->payloadFromValidated($validated, $user, $hasApprovers, false);
            unset($data['number'], $data['created_by']);
            if ($becomesSubmitted) {
                $data['workflow_status'] = 'submitted';
                $data['submitted_at'] = now();
            } else {
                unset($data['workflow_status'], $data['submitted_at']);
            }

            $record->update($data);

            if (in_array($record->fresh()->workflow_status, ['draft', 'submitted', 'rejected'], true)
                && $record->approvalFlows()->where('status', '!=', 'PENDING')->doesntExist()) {
                $record->approvalFlows()->delete();
                foreach ($approverIds as $index => $approverId) {
                    ContentPlanApprovalFlow::create([
                        'content_plan_id' => $record->id,
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

            return back()->withErrors(['platform' => $e->getMessage()])->withInput();
        }

        return redirect()->route('content-plans.show', $record->id)
            ->with('success', 'Content Plan berhasil diupdate.');
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

        return redirect()->route('content-plans.index')->with('success', 'Content Plan dihapus.');
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
            return response()->json(['success' => false, 'plans' => []], 401);
        }

        $isSuperadmin = $this->isSuperadmin($currentUser);

        $query = ContentPlan::query()
            ->where('workflow_status', 'submitted')
            ->whereHas('approvalFlows', function ($q) use ($currentUser, $isSuperadmin) {
                $q->where('status', 'PENDING');
                if (! $isSuperadmin) {
                    $q->where('approver_id', $currentUser->id);
                }
            })
            ->with([
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        $pending = $query->get();

        if (! $isSuperadmin) {
            $pending = $pending->filter(function (ContentPlan $item) use ($currentUser) {
                $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();

                return $next && (int) $next->approver_id === (int) $currentUser->id;
            })->values();
        }

        $pending = $pending->map(function (ContentPlan $item) {
            $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();
            $item->approver_name = $next?->approver?->nama_lengkap;
            $item->approval_level = $next?->approval_level;
            $item->job_status_label = $item->jobStatusLabel();

            return $item;
        });

        return response()->json([
            'success' => true,
            'plans' => $pending->values(),
        ]);
    }

    public function getApprovalDetails($id)
    {
        $user = auth()->user();
        $record = ContentPlan::with([
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
            'plan' => $record,
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

            $plan = ContentPlan::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($plan->workflow_status !== 'submitted') {
                throw new \RuntimeException('Content Plan tidak dalam status Submitted.');
            }

            $flow = $this->resolveCurrentApprovalFlow($plan, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk Content Plan ini.');
            }

            $flow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            $stillPending = $plan->approvalFlows()->where('status', 'PENDING')->exists();
            if (! $stillPending) {
                $plan->update([
                    'workflow_status' => 'approved',
                    'approved_at' => now(),
                ]);
            }

            DB::commit();
            $this->clearCaches($plan->fresh('approvalFlows'), [], [$user->id]);

            return response()->json([
                'success' => true,
                'message' => $stillPending
                    ? 'Approval berhasil. Menunggu approver berikutnya.'
                    : 'Content Plan fully Approved.',
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

            $plan = ContentPlan::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($plan->workflow_status !== 'submitted') {
                throw new \RuntimeException('Content Plan tidak dalam status Submitted.');
            }

            $flow = $this->resolveCurrentApprovalFlow($plan, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk Content Plan ini.');
            }

            $flow->update([
                'status' => 'REJECTED',
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            $plan->update(['workflow_status' => 'rejected']);

            DB::commit();
            $this->clearCaches($plan->fresh('approvalFlows'), [], [$user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Content Plan ditolak.',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal reject: '.$e->getMessage()], 500);
        }
    }

    private function formOptions(): array
    {
        return [
            'platforms' => ContentPlan::PLATFORMS,
            'funnels' => ContentPlan::FUNNELS,
            'pillars' => ContentPlan::PILLARS,
            'formats' => ContentPlan::FORMATS,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
        ];
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'platform' => 'required|string|in:'.implode(',', ContentPlan::PLATFORMS),
            'date_post' => 'nullable|date',
            'funnel' => 'nullable|string|in:'.implode(',', ContentPlan::FUNNELS),
            'pillar' => 'nullable|string|in:'.implode(',', ContentPlan::PILLARS),
            'format' => 'nullable|string|in:'.implode(',', ContentPlan::FORMATS),
            'objective' => 'nullable|string',
            'brief' => 'nullable|string',
            'reference' => 'nullable|string',
            'caption' => 'nullable|string',
            'job_status' => 'required|string|in:'.implode(',', array_keys(ContentPlan::JOB_STATUSES)),
            'final_output_links' => 'nullable|array',
            'final_output_links.*' => 'nullable|string|max:2000',
            'actual_post_date' => 'nullable|date',
            'actual_post_links' => 'nullable|array',
            'actual_post_links.*' => 'nullable|string|max:2000',
            'remarks' => 'nullable|string|max:5000',
            'approvers' => 'nullable|array',
            'approvers.*' => 'integer|exists:users,id',
        ]);
    }

    private function payloadFromValidated(array $validated, $user, bool $hasApprovers, bool $isCreate): array
    {
        $datePost = $validated['date_post'] ?? null;
        $dayName = null;
        if ($datePost) {
            $dayName = Carbon::parse($datePost)->locale('en')->isoFormat('dddd');
        }

        $payload = [
            'platform' => $validated['platform'],
            'date_post' => $datePost,
            'day_name' => $dayName,
            'funnel' => $validated['funnel'] ?? null,
            'pillar' => $validated['pillar'] ?? null,
            'format' => $validated['format'] ?? null,
            'objective' => $validated['objective'] ?? null,
            'brief' => $validated['brief'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'job_status' => $validated['job_status'],
            'final_output_links' => $this->normalizeLinks($validated['final_output_links'] ?? []),
            'actual_post_date' => $validated['actual_post_date'] ?? null,
            'actual_post_links' => $this->normalizeLinks($validated['actual_post_links'] ?? []),
            'remarks' => $validated['remarks'] ?? null,
        ];

        if ($isCreate) {
            $payload['number'] = $this->generateNumber();
            $payload['created_by'] = $user->id;
            $payload['workflow_status'] = $hasApprovers ? 'submitted' : 'draft';
            $payload['submitted_at'] = $hasApprovers ? now() : null;
        }

        return $payload;
    }

    private function normalizeLinks(array $links): array
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

    private function generateNumber(): string
    {
        $prefix = 'CP-'.now()->format('Ym').'-';
        $last = ContentPlan::query()
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

        return ContentPlan::query()
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('created_by', $user->id)
                        ->orWhereHas('approvalFlows', fn ($f) => $f->where('approver_id', $user->id));
                });
            });
    }

    private function findVisible($id): ContentPlan
    {
        return $this->visibleQuery()
            ->with([
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap,email',
            ])
            ->where('id', $id)
            ->firstOrFail();
    }

    private function canEdit(ContentPlan $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) $record->created_by === (int) $user->id;
    }

    private function canDelete(ContentPlan $record, $user): bool
    {
        return $this->canEdit($record, $user);
    }

    private function resolveCurrentApprovalFlow(ContentPlan $plan, $user): ?ContentPlanApprovalFlow
    {
        if (! $user || $plan->workflow_status !== 'submitted') {
            return null;
        }

        $next = $plan->approvalFlows()
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

    private function clearCaches(?ContentPlan $plan, array $extraUserIds = [], array $more = []): void
    {
        $ids = $extraUserIds;
        if ($plan) {
            $ids[] = $plan->created_by;
            $ids = array_merge($ids, $plan->approvalFlows->pluck('approver_id')->all());
        }
        $ids = array_merge($ids, $more);
        PendingApprovalCache::forgetForUsers($ids);
    }
}
