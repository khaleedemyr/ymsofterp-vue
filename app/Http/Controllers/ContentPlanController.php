<?php

namespace App\Http\Controllers;

use App\Models\ContentPlan;
use App\Models\ContentPlanApprovalFlow;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PendingApprovalCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $brandId = $request->get('brand_id');
        $platform = trim((string) $request->get('platform', ''));
        $priority = trim((string) $request->get('priority', ''));
        $workflowStatus = trim((string) $request->get('workflow_status', ''));

        $user = auth()->user();

        $records = $this->visibleQuery()
            ->with([
                'brand:id,brand',
                'pic:id,nama_lengkap',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('caption', 'like', "%{$search}%")
                        ->orWhere('objective', 'like', "%{$search}%")
                        ->orWhere('brief', 'like', "%{$search}%")
                        ->orWhere('pillar', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($b) => $b->where('brand', 'like', "%{$search}%"));
                });
            })
            ->when($brandId, fn ($q) => $q->where('brand_id', (int) $brandId))
            ->when($platform !== '', fn ($q) => $q->where('platform', $platform))
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->when($workflowStatus !== '', fn ($q) => $q->where('workflow_status', $workflowStatus))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (ContentPlan $row) use ($user) {
                $row->setAttribute('can_edit', $this->canEditFlow1($row, $user));
                $row->setAttribute('can_edit_pic', $this->canEditFlow2($row, $user));
                $row->setAttribute('can_delete', $this->canDelete($row, $user));

                return $row;
            });

        return Inertia::render('ContentPlan/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'brand_id' => $brandId ? (int) $brandId : '',
                'platform' => $platform,
                'priority' => $priority,
                'workflow_status' => $workflowStatus,
            ],
            'brands' => $this->brands(),
            'platforms' => ContentPlan::PLATFORMS,
            'priorities' => ContentPlan::PRIORITIES,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
            'workflowStatuses' => ContentPlan::WORKFLOW_STATUSES,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());
        $platform = trim((string) $request->get('platform', ''));
        $funnel = trim((string) $request->get('funnel', ''));
        $pillar = trim((string) $request->get('pillar', ''));

        $base = $this->visibleQuery()
            ->when($dateFrom, fn ($q) => $q->where(function ($inner) use ($dateFrom) {
                $inner->whereDate('date_post', '>=', $dateFrom)
                    ->orWhere(function ($o) use ($dateFrom) {
                        $o->whereNull('date_post')->whereDate('created_at', '>=', $dateFrom);
                    });
            }))
            ->when($dateTo, fn ($q) => $q->where(function ($inner) use ($dateTo) {
                $inner->whereDate('date_post', '<=', $dateTo)
                    ->orWhere(function ($o) use ($dateTo) {
                        $o->whereNull('date_post')->whereDate('created_at', '<=', $dateTo);
                    });
            }))
            ->when($platform !== '', fn ($q) => $q->where('platform', $platform))
            ->when($funnel !== '', fn ($q) => $q->where('funnel', $funnel))
            ->when($pillar !== '', fn ($q) => $q->where('pillar', $pillar));

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

        $byPlatform = (clone $base)
            ->select('platform', DB::raw('COUNT(*) as total'))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->platform, 'total' => (int) $r->total])
            ->values()
            ->all();

        $byFunnel = (clone $base)
            ->whereNotNull('funnel')
            ->where('funnel', '!=', '')
            ->select('funnel', DB::raw('COUNT(*) as total'))
            ->groupBy('funnel')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->funnel, 'total' => (int) $r->total])
            ->values()
            ->all();

        $byPillar = (clone $base)
            ->whereNotNull('pillar')
            ->where('pillar', '!=', '')
            ->select('pillar', DB::raw('COUNT(*) as total'))
            ->groupBy('pillar')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->pillar, 'total' => (int) $r->total])
            ->values()
            ->all();

        $byFormat = (clone $base)
            ->whereNotNull('format')
            ->where('format', '!=', '')
            ->select('format', DB::raw('COUNT(*) as total'))
            ->groupBy('format')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->format, 'total' => (int) $r->total])
            ->values()
            ->all();

        $approved = (int) ($byWorkflow['approved'] ?? 0);
        $submitted = (int) ($byWorkflow['submitted'] ?? 0);
        $draft = (int) ($byWorkflow['draft'] ?? 0);
        $rejected = (int) ($byWorkflow['rejected'] ?? 0);
        $completionRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;

        $postedCount = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('actual_post_date')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('actual_post_links')
                            ->where('actual_post_links', '!=', '[]')
                            ->where('actual_post_links', '!=', 'null');
                    });
            })
            ->count();

        $overdueCount = (clone $base)
            ->whereNotNull('date_post')
            ->whereDate('date_post', '<', now()->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->count();

        $dueSoonCount = (clone $base)
            ->whereNotNull('date_post')
            ->whereDate('date_post', '>=', now()->toDateString())
            ->whereDate('date_post', '<=', now()->addDays(7)->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->count();

        $trend = (clone $base)
            ->whereNotNull('date_post')
            ->select(DB::raw('DATE(date_post) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(date_post)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])
            ->values()
            ->all();

        $jobStatusBreakdown = collect(ContentPlan::JOB_STATUSES)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'total' => (int) ($byJobStatus[$key] ?? 0),
        ])->values()->all();

        $recentPlans = (clone $base)
            ->with(['creator:id,nama_lengkap'])
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'number', 'platform', 'date_post', 'day_name', 'pillar', 'format', 'job_status', 'workflow_status', 'created_at']);

        $overduePlans = (clone $base)
            ->with(['creator:id,nama_lengkap'])
            ->whereNotNull('date_post')
            ->whereDate('date_post', '<', now()->toDateString())
            ->where('workflow_status', '!=', 'approved')
            ->orderBy('date_post')
            ->limit(15)
            ->get(['id', 'number', 'platform', 'date_post', 'day_name', 'pillar', 'job_status', 'workflow_status']);

        return Inertia::render('ContentPlan/Dashboard', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'platform' => $platform,
                'funnel' => $funnel,
                'pillar' => $pillar,
            ],
            'platforms' => ContentPlan::PLATFORMS,
            'funnels' => ContentPlan::FUNNELS,
            'pillars' => ContentPlan::PILLARS,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
            'workflowStatuses' => ContentPlan::WORKFLOW_STATUSES,
            'summary' => [
                'total' => $total,
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'posted' => $postedCount,
                'overdue' => $overdueCount,
                'due_soon' => $dueSoonCount,
                'completion_rate' => $completionRate,
            ],
            'byPlatform' => $byPlatform,
            'byFunnel' => $byFunnel,
            'byPillar' => $byPillar,
            'byFormat' => $byFormat,
            'byJobStatus' => $jobStatusBreakdown,
            'trend' => $trend,
            'recentPlans' => $recentPlans,
            'overduePlans' => $overduePlans,
        ]);
    }

    public function create(): Response
    {
        $user = auth()->user();

        return Inertia::render('ContentPlan/Create', array_merge($this->formOptions(), [
            'meta' => [
                'number_preview' => 'TP-'.now()->format('Ym').'-AUTO',
                'created_at' => now()->toDateTimeString(),
                'created_by_name' => $user?->nama_lengkap ?? '',
            ],
        ]));
    }

    public function show($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();

        return Inertia::render('ContentPlan/Show', [
            'record' => $record,
            'canApprove' => (bool) $this->resolveCurrentApprovalFlow($record, $user),
            'canEdit' => $this->canEditFlow1($record, $user),
            'canEditPic' => $this->canEditFlow2($record, $user),
            'canDelete' => $this->canDelete($record, $user),
            'brands' => $this->brands(),
            'priorities' => ContentPlan::PRIORITIES,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
            'workflowStatuses' => ContentPlan::WORKFLOW_STATUSES,
        ]);
    }

    public function edit($id): Response
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEditFlow1($record, $user)) {
            abort(403, 'Tidak dapat mengedit Tasking Plan ini.');
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
            $payload = $this->payloadFromValidated($validated, $user, $hasApprovers, true);
            $payload['reference_images'] = $this->storeReferenceImages($request);
            $plan = ContentPlan::create($payload);

            foreach ($approverIds as $index => $approverId) {
                ContentPlanApprovalFlow::create([
                    'content_plan_id' => $plan->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();
            $fresh = $plan->fresh(['brand', 'pic', 'creator', 'approvalFlows.approver']);
            $this->clearCaches($fresh, $approverIds, [$user->id]);
            $this->notifyPicAssigned($fresh, null);
            $this->notifyCreatorCreated($fresh);
            if ($hasApprovers) {
                $this->notifyCurrentApprover($fresh, 'Tasking Plan baru menunggu approval Anda.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['platform' => $e->getMessage()])->withInput();
        }

        return redirect()->route('content-plans.show', $plan->id)
            ->with('success', $hasApprovers
                ? 'Tasking Plan disimpan dan Submitted.'
                : 'Tasking Plan disimpan sebagai Draft.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEditFlow1($record, $user)) {
            abort(403, 'Tidak dapat mengedit Tasking Plan ini.');
        }

        $validated = $this->validatePayload($request);
        $approverIds = $this->normalizeApproverIds($validated['approvers'] ?? []);
        $previousPicId = $record->pic_user_id ? (int) $record->pic_user_id : null;

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

            $keptImages = $this->resolveExistingReferenceImages($request, $record);
            $removedImages = array_values(array_diff($record->reference_images ?? [], $keptImages));
            $newImages = $this->storeReferenceImages($request);
            $data['reference_images'] = array_values(array_merge($keptImages, $newImages));

            $record->update($data);
            $this->deleteReferenceImages($removedImages);

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
            $fresh = $record->fresh(['approvalFlows.approver', 'brand', 'pic', 'creator']);
            $this->clearCaches($fresh, $approverIds, [$user->id]);
            $this->notifyPicAssigned($fresh, $previousPicId);
            if ($becomesSubmitted || ($hasApprovers && $fresh->workflow_status === 'submitted')) {
                $this->notifyCurrentApprover($fresh, 'Tasking Plan menunggu approval Anda.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['platform' => $e->getMessage()])->withInput();
        }

        return redirect()->route('content-plans.show', $record->id)
            ->with('success', 'Tasking Plan berhasil diupdate.');
    }

    public function updatePic(Request $request, $id)
    {
        $record = $this->findVisible($id);
        $user = auth()->user();
        if (! $this->canEditFlow2($record, $user)) {
            abort(403, 'Hanya PIC yang ditugaskan yang dapat mengisi Flow 2.');
        }

        $validated = $request->validate([
            'pic_submit_date' => 'nullable|date',
            'task' => 'nullable|string|max:10000',
            'final_output_links' => 'nullable|array',
            'final_output_links.*' => 'nullable|string|max:2000',
            'actual_post_links' => 'nullable|array',
            'actual_post_links.*' => 'nullable|string|max:2000',
            'pic_remarks' => 'nullable|string|max:5000',
        ]);

        $record->update([
            'pic_submit_date' => $validated['pic_submit_date'] ?? null,
            'task' => $validated['task'] ?? null,
            'final_output_links' => $this->normalizeLinks($validated['final_output_links'] ?? []),
            'actual_post_links' => $this->normalizeLinks($validated['actual_post_links'] ?? []),
            'pic_remarks' => $validated['pic_remarks'] ?? null,
        ]);

        $fresh = $record->fresh(['brand', 'pic', 'creator', 'approvalFlows.approver']);
        $this->clearCaches($fresh, [], [$user->id]);
        $this->notifyPicFlowUpdated($fresh);

        return redirect()->route('content-plans.show', $record->id)
            ->with('success', 'Input PIC (Flow 2) berhasil disimpan. Notifikasi dikirim ke creator & approver.');
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
        $images = $record->reference_images ?? [];
        $record->delete();
        $this->deleteReferenceImages($images);
        $this->clearCaches(null, $approverIds, [$user->id, $createdBy]);

        return redirect()->route('content-plans.index')->with('success', 'Tasking Plan dihapus.');
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
                'brand:id,brand',
                'pic:id,nama_lengkap',
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
            $item->priority_label = $item->priorityLabel();
            $item->workflow_status_label = $item->workflowStatusLabel();

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
            'brand:id,brand',
            'pic:id,nama_lengkap',
            'creator:id,nama_lengkap',
            'approvalFlows.approver:id,nama_lengkap',
        ])->findOrFail($id);

        $canApprove = false;
        if ($user && $record->workflow_status === 'submitted') {
            $canApprove = (bool) $this->resolveCurrentApprovalFlow($record, $user);
        }
        $record->setAttribute('can_approve', $canApprove);
        $record->setAttribute('job_status_label', $record->jobStatusLabel());
        $record->setAttribute('priority_label', $record->priorityLabel());
        $record->setAttribute('workflow_status_label', $record->workflowStatusLabel());

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
            $fresh = $plan->fresh(['approvalFlows.approver', 'brand', 'pic', 'creator']);
            $this->clearCaches($fresh, [], [$user->id]);
            $this->notifyApprovalResult($fresh, true, $stillPending);

            return response()->json([
                'success' => true,
                'message' => $stillPending
                    ? 'Approval berhasil. Menunggu approver berikutnya.'
                    : 'Tasking Plan fully Approved.',
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
            $fresh = $plan->fresh(['approvalFlows.approver', 'brand', 'pic', 'creator']);
            $this->clearCaches($fresh, [], [$user->id]);
            $this->notifyApprovalResult($fresh, false, false);

            return response()->json([
                'success' => true,
                'message' => 'Tasking Plan ditolak.',
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
            'brands' => $this->brands(),
            'picUsers' => $this->picUsers(),
            'platforms' => ContentPlan::PLATFORMS,
            'funnels' => ContentPlan::FUNNELS,
            'pillars' => ContentPlan::PILLARS,
            'formats' => ContentPlan::FORMATS,
            'priorities' => ContentPlan::PRIORITIES,
            'jobStatuses' => ContentPlan::JOB_STATUSES,
        ];
    }

    private function brands()
    {
        return DB::table('brands')
            ->orderBy('brand')
            ->get(['id', 'brand', 'logo']);
    }

    private function picUsers()
    {
        return User::query()
            ->where('status', 'A')
            ->whereIn('division_id', [7, 17])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'division_id']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'brand_id' => 'required|integer|exists:brands,id',
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
            'platform' => 'required|string|in:'.implode(',', ContentPlan::allowedPlatforms()),
            'deadline_content' => 'nullable|date',
            'date_post' => 'nullable|date',
            'funnel' => 'nullable|string|in:'.implode(',', ContentPlan::FUNNELS),
            'pillar' => 'nullable|string|in:'.implode(',', ContentPlan::PILLARS),
            'format' => 'nullable|string|in:'.implode(',', ContentPlan::allowedFormats()),
            'objective' => 'nullable|string',
            'brief' => 'nullable|string',
            'reference' => 'nullable|string',
            'reference_images' => 'nullable|array',
            'reference_images.*' => 'nullable|image|max:5120',
            'existing_reference_images' => 'nullable|array',
            'existing_reference_images.*' => 'nullable|string|max:500',
            'existing_reference_images_json' => 'nullable|string',
            'caption' => 'nullable|string',
            'priority' => 'required|string|in:'.implode(',', array_keys(ContentPlan::PRIORITIES)),
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
            'brand_id' => $validated['brand_id'],
            'pic_user_id' => $validated['pic_user_id'],
            'platform' => $validated['platform'],
            'deadline_content' => $validated['deadline_content'] ?? null,
            'date_post' => $datePost,
            'day_name' => $dayName,
            'funnel' => $validated['funnel'] ?? null,
            'pillar' => $validated['pillar'] ?? null,
            'format' => $validated['format'] ?? null,
            'objective' => $validated['objective'] ?? null,
            'brief' => $validated['brief'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'remarks' => $validated['remarks'] ?? null,
        ];

        if ($isCreate) {
            $payload['number'] = $this->generateNumber();
            $payload['created_by'] = $user->id;
            $payload['job_status'] = 'on_crafting';
            $payload['final_output_links'] = [];
            $payload['actual_post_links'] = [];
            $payload['workflow_status'] = $hasApprovers ? 'submitted' : 'draft';
            $payload['submitted_at'] = $hasApprovers ? now() : null;
        }

        return $payload;
    }

    private function resolveExistingReferenceImages(Request $request, ContentPlan $record): array
    {
        if ($request->filled('existing_reference_images_json')) {
            $decoded = json_decode((string) $request->input('existing_reference_images_json'), true);

            return $this->normalizeLinks(is_array($decoded) ? $decoded : []);
        }

        if ($request->exists('existing_reference_images')) {
            return $this->normalizeLinks($request->input('existing_reference_images', []));
        }

        return $this->normalizeLinks($record->reference_images ?? []);
    }

    private function storeReferenceImages(Request $request): array
    {
        $paths = [];
        foreach ($request->file('reference_images', []) as $file) {
            if (! $file) {
                continue;
            }
            $paths[] = $file->store('content-plans/references', 'public');
        }

        return $paths;
    }

    private function deleteReferenceImages(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
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
        $prefix = 'TP-'.now()->format('Ym').'-';
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
        // Semua user yang punya akses menu boleh melihat seluruh Content Plan.
        // Batasan edit: creator / PIC / superadmin; delete: creator / superadmin.
        return ContentPlan::query();
    }

    private function findVisible($id): ContentPlan
    {
        return $this->visibleQuery()
            ->with([
                'brand:id,brand,logo',
                'pic:id,nama_lengkap,email,division_id',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap,email',
            ])
            ->where('id', $id)
            ->firstOrFail();
    }

    private function planUrl(ContentPlan $plan): string
    {
        return rtrim((string) config('app.url'), '/').'/content-plans/'.$plan->id;
    }

    private function planSummary(ContentPlan $plan): string
    {
        $plan->loadMissing(['brand']);
        $brandName = $plan->brand->brand ?? '-';
        $deadline = $plan->deadline_content ? $plan->deadline_content->format('Y-m-d') : '-';

        return "No: {$plan->number}\nBrand: {$brandName}\nPlatform: {$plan->platform}\nDeadline: {$deadline}\nPillar: ".($plan->pillar ?: '-');
    }

    private function notifyUser(?int $userId, ContentPlan $plan, string $type, string $message): void
    {
        if (! $userId) {
            return;
        }

        try {
            NotificationService::insert([
                'user_id' => $userId,
                'task_id' => $plan->id,
                'type' => $type,
                'message' => $message,
                'url' => $this->planUrl($plan),
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send Tasking Plan notification', [
                'content_plan_id' => $plan->id ?? null,
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifikasi ke PIC saat di-assign / diganti.
     */
    private function notifyPicAssigned(ContentPlan $plan, ?int $previousPicId = null): void
    {
        $picId = $plan->pic_user_id ? (int) $plan->pic_user_id : null;
        if (! $picId) {
            return;
        }

        if ($previousPicId !== null && $previousPicId === $picId) {
            return;
        }

        $actorId = auth()->id();
        if ($actorId && (int) $actorId === $picId) {
            return;
        }

        $assignerName = auth()->user()->nama_lengkap ?? 'System';
        $message = "Anda di-assign sebagai PIC Tasking Plan:\n\n";
        $message .= $this->planSummary($plan)."\n";
        $message .= "Assigned by: {$assignerName}\n\n";
        $message .= 'Silakan isi Flow 2 (input PIC) di halaman detail.';

        $this->notifyUser($picId, $plan, 'content_plan_pic_assigned', $message);
    }

    private function notifyCreatorCreated(ContentPlan $plan): void
    {
        $creatorId = $plan->created_by ? (int) $plan->created_by : null;
        if (! $creatorId) {
            return;
        }

        $status = strtoupper((string) ($plan->workflow_status ?: 'draft'));
        $message = "Tasking Plan berhasil dibuat:\n\n";
        $message .= $this->planSummary($plan)."\n";
        $message .= "Status Approval: {$status}\n";
        $message .= 'PIC: '.($plan->pic->nama_lengkap ?? '-');

        $this->notifyUser($creatorId, $plan, 'content_plan_created', $message);
    }

    private function notifyCurrentApprover(ContentPlan $plan, string $intro): void
    {
        $plan->loadMissing(['approvalFlows.approver']);
        $next = $plan->approvalFlows
            ->where('status', 'PENDING')
            ->sortBy('approval_level')
            ->first();

        if (! $next) {
            return;
        }

        $actorId = auth()->id();
        if ($actorId && (int) $actorId === (int) $next->approver_id) {
            return;
        }

        $message = "{$intro}\n\n";
        $message .= $this->planSummary($plan)."\n";
        $message .= 'Level approval: '.$next->approval_level."\n";
        $message .= 'PIC: '.($plan->pic->nama_lengkap ?? '-');

        $this->notifyUser((int) $next->approver_id, $plan, 'content_plan_approval_request', $message);
    }

    private function notifyPicFlowUpdated(ContentPlan $plan): void
    {
        $actorName = auth()->user()->nama_lengkap ?? 'PIC';
        $summary = $this->planSummary($plan);
        $submitDate = $plan->pic_submit_date ? $plan->pic_submit_date->format('Y-m-d') : '-';

        $base = "Update Flow 2 (input PIC) Tasking Plan:\n\n{$summary}\nSubmit Date: {$submitDate}\nUpdated by: {$actorName}";

        // Creator
        $creatorId = $plan->created_by ? (int) $plan->created_by : null;
        if ($creatorId && $creatorId !== (int) auth()->id()) {
            $this->notifyUser($creatorId, $plan, 'content_plan_pic_updated', $base."\n\nMohon ditinjau.");
        }

        // Approvers (pending + all in chain)
        $plan->loadMissing(['approvalFlows']);
        $approverIds = $plan->approvalFlows->pluck('approver_id')->map(fn ($id) => (int) $id)->unique()->all();
        foreach ($approverIds as $approverId) {
            if (! $approverId || $approverId === (int) auth()->id()) {
                continue;
            }
            $this->notifyUser($approverId, $plan, 'content_plan_pic_updated', $base."\n\nSilakan cek progress PIC.");
        }

        // PIC confirmation (self)
        $picId = $plan->pic_user_id ? (int) $plan->pic_user_id : null;
        if ($picId) {
            $this->notifyUser($picId, $plan, 'content_plan_pic_updated', "Input PIC Anda untuk Tasking Plan {$plan->number} sudah tersimpan.\n\n{$summary}");
        }
    }

    private function notifyApprovalResult(ContentPlan $plan, bool $approved, bool $stillPending): void
    {
        $actorName = auth()->user()->nama_lengkap ?? 'Approver';
        $summary = $this->planSummary($plan);

        if ($approved && $stillPending) {
            $this->notifyCurrentApprover($plan, 'Tasking Plan menunggu approval Anda (level berikutnya).');
            $statusText = 'Approval bertingkat berhasil. Menunggu approver berikutnya.';
        } elseif ($approved) {
            $statusText = 'Tasking Plan telah APPROVED.';
        } else {
            $statusText = 'Tasking Plan telah DITOLAK.';
        }

        $message = "{$statusText}\n\n{$summary}\nOleh: {$actorName}";

        $creatorId = $plan->created_by ? (int) $plan->created_by : null;
        if ($creatorId && $creatorId !== (int) auth()->id()) {
            $this->notifyUser($creatorId, $plan, $approved ? 'content_plan_approved' : 'content_plan_rejected', $message);
        }

        $picId = $plan->pic_user_id ? (int) $plan->pic_user_id : null;
        if ($picId && $picId !== (int) auth()->id()) {
            $this->notifyUser($picId, $plan, $approved ? 'content_plan_approved' : 'content_plan_rejected', $message);
        }

        // Saat fully approved/rejected, kabari juga seluruh approver di chain
        if (! $stillPending) {
            $plan->loadMissing(['approvalFlows']);
            foreach ($plan->approvalFlows as $flow) {
                $approverId = (int) $flow->approver_id;
                if (! $approverId || $approverId === (int) auth()->id()) {
                    continue;
                }
                $this->notifyUser($approverId, $plan, $approved ? 'content_plan_approved' : 'content_plan_rejected', $message);
            }
        }
    }

    private function canEditFlow1(ContentPlan $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        // Flow 1: pemberi tugas (creator)
        return (int) $record->created_by === (int) $user->id;
    }

    private function canEditFlow2(ContentPlan $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        // Flow 2: PIC yang ditugaskan
        return $record->pic_user_id
            && (int) $record->pic_user_id === (int) $user->id;
    }

    private function canDelete(ContentPlan $record, $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->isSuperadmin($user)) {
            return true;
        }

        // Hapus hanya creator (PIC boleh edit, bukan hapus).
        return (int) $record->created_by === (int) $user->id;
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
