<?php

namespace App\Http\Controllers;

use App\Models\SopDevelopmentCompletion;
use App\Models\SopDevelopmentCompletionApprovalFlow;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SopDevelopmentCompletionController extends Controller
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    private const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx';

    public function indexPage(Request $request): Response
    {
        $user = Auth::user();
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all');
        $perPage = (int) $request->get('per_page', 15);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $query = SopDevelopmentCompletion::query()
            ->with([
                'user:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        if (! $this->isSuperAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate($perPage)->withQueryString();

        return Inertia::render('SopDevelopmentCompletion/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'isSuperAdmin' => $this->isSuperAdmin($user),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'required|date|after_or_equal:today',
        ]);

        $record = SopDevelopmentCompletion::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
            'status' => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SOP development berhasil dibuat.',
            'data' => $record->load(['user:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap']),
        ], 201);
    }

    public function update(Request $request, SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $this->ensureOwner($sopDevelopmentCompletion);

        if (! $sopDevelopmentCompletion->isEditableByOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya SOP dengan status draft yang dapat diubah.',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'required|date',
        ]);

        $sopDevelopmentCompletion->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'SOP development berhasil diperbarui.',
            'data' => $sopDevelopmentCompletion->fresh(['user:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap']),
        ]);
    }

    public function destroy(SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $user = Auth::user();
        $isSuperadmin = $this->isSuperAdmin($user);

        if (! $isSuperadmin && (int) $sopDevelopmentCompletion->user_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $isSuperadmin && ! $sopDevelopmentCompletion->canBeDeletedByOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'SOP yang sudah selesai tidak dapat dihapus.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            if ($sopDevelopmentCompletion->file_path) {
                Storage::disk('public')->delete($sopDevelopmentCompletion->file_path);
            }

            $sopDevelopmentCompletion->approvalFlows()->delete();
            $sopDevelopmentCompletion->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SOP development berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error deleting SOP development: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus SOP development.',
            ], 500);
        }
    }

    public function show(SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $user = Auth::user();

        if (! $this->canView($sopDevelopmentCompletion, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $sopDevelopmentCompletion->load([
            'user:id,nama_lengkap,avatar',
            'approvalFlows.approver:id,nama_lengkap',
        ]);

        return response()->json([
            'success' => true,
            'data' => $sopDevelopmentCompletion,
            'can_approve' => $this->canApprove($sopDevelopmentCompletion),
            'current_approval_flow' => $this->resolveApprovalFlow($sopDevelopmentCompletion),
        ]);
    }

    public function submitForApproval(Request $request, SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $this->ensureOwner($sopDevelopmentCompletion);

        if (! $sopDevelopmentCompletion->canSubmitForApproval()) {
            return response()->json([
                'success' => false,
                'message' => 'SOP ini tidak dapat diajukan untuk approval.',
            ], 400);
        }

        $validated = $request->validate([
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id|distinct',
            'file' => 'required|file|mimes:'.self::ALLOWED_MIMES.'|max:20480',
        ]);

        $approverIds = array_map('intval', $validated['approvers']);
        if (in_array((int) Auth::id(), $approverIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Approver tidak boleh sama dengan pembuat SOP.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($sopDevelopmentCompletion->file_path) {
                Storage::disk('public')->delete($sopDevelopmentCompletion->file_path);
            }

            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $fileName = 'sop_'.$sopDevelopmentCompletion->id.'_'.time().'_'.uniqid().'.'.$extension;
            $filePath = $file->storeAs('sop_developments', $fileName, 'public');

            $isResubmit = $sopDevelopmentCompletion->status === 'rejected';

            $sopDevelopmentCompletion->update([
                'file_path' => $filePath,
                'file_original_name' => $file->getClientOriginalName(),
                'status' => 'pending',
                'approval_notes' => null,
                'submitted_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
                'resubmission_count' => $isResubmit
                    ? $sopDevelopmentCompletion->resubmission_count + 1
                    : $sopDevelopmentCompletion->resubmission_count,
            ]);

            $this->syncApprovalFlows($sopDevelopmentCompletion, $approverIds);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $isResubmit
                    ? 'SOP berhasil diajukan ulang untuk approval.'
                    : 'SOP berhasil diajukan untuk approval.',
                'data' => $sopDevelopmentCompletion->fresh(['user:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error submitting SOP for approval: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan SOP untuk approval.',
            ], 500);
        }
    }

    public function getPendingApprovals(Request $request)
    {
        $this->repairLegacyApprovalFlows();

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => [],
            ], 401);
        }

        $limit = (int) $request->get('limit', 100);
        $isSuperadmin = $this->isSuperAdmin($user);

        $query = SopDevelopmentCompletion::query()
            ->with([
                'user:id,nama_lengkap,avatar',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->where('status', 'pending');

        if ($isSuperadmin) {
            $records = $query->whereHas('approvalFlows', fn ($q) => $q->where('status', 'PENDING'))->get();
        } else {
            $records = $query->whereHas('approvalFlows', function ($q) use ($user) {
                $q->where('approver_id', $user->id)->where('status', 'PENDING');
            })->get();
        }

        $mapped = $records
            ->filter(fn (SopDevelopmentCompletion $record) => $this->isVisiblePendingForUser($record, (int) $user->id, $isSuperadmin))
            ->map(function (SopDevelopmentCompletion $record) {
                $nextFlow = $record->approvalFlows
                    ->where('status', 'PENDING')
                    ->sortBy('approval_level')
                    ->first();

                return [
                    'id' => $record->id,
                    'title' => $record->title,
                    'description' => $record->description,
                    'due_date' => $record->due_date?->format('Y-m-d'),
                    'status' => $record->status,
                    'file_path' => $record->file_path,
                    'file_original_name' => $record->file_original_name,
                    'submitted_at' => $record->submitted_at?->toIso8601String(),
                    'created_at' => $record->created_at?->toIso8601String(),
                    'user' => $record->user,
                    'creator_name' => $record->user?->nama_lengkap,
                    'approval_flows' => $record->approvalFlows,
                    'approval_level' => $nextFlow?->approval_level,
                    'approval_flow_id' => $nextFlow?->id,
                    'approver_name' => $nextFlow?->approver?->nama_lengkap,
                    'can_approve' => true,
                ];
            })
            ->sortBy('submitted_at')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $mapped,
            'items' => $mapped,
        ]);
    }

    public function approve(Request $request, SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        $flow = $this->resolveApprovalFlow($sopDevelopmentCompletion);
        if (! $flow) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized atau SOP sudah diproses.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $flow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'rejected_at' => null,
                'comments' => $request->approval_notes,
            ]);

            $pendingCount = SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $sopDevelopmentCompletion->id)
                ->where('status', 'PENDING')
                ->count();

            if ($pendingCount === 0) {
                $sopDevelopmentCompletion->update([
                    'status' => 'approved',
                    'approval_notes' => $request->approval_notes,
                    'approved_at' => now(),
                    'rejected_at' => null,
                ]);

                NotificationService::insert([
                    'user_id' => $sopDevelopmentCompletion->user_id,
                    'type' => 'sop_development_approved',
                    'message' => "SOP \"{$sopDevelopmentCompletion->title}\" Anda telah disetujui sepenuhnya.",
                    'url' => config('app.url').'/sop-development-completion',
                    'is_read' => 0,
                ]);
            } else {
                $next = SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $sopDevelopmentCompletion->id)
                    ->where('status', 'PENDING')
                    ->orderBy('approval_level')
                    ->first();

                if ($next) {
                    NotificationService::insert([
                        'user_id' => $next->approver_id,
                        'type' => 'sop_development_approval',
                        'message' => "SOP \"{$sopDevelopmentCompletion->title}\" menunggu approval Anda (level {$next->approval_level}).",
                        'url' => config('app.url').'/home',
                        'is_read' => 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $pendingCount === 0
                    ? 'SOP berhasil disetujui sepenuhnya.'
                    : 'Approval level Anda berhasil. Menunggu approver berikutnya.',
                'data' => $sopDevelopmentCompletion->fresh(['user:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error approving SOP: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui SOP.',
            ], 500);
        }
    }

    public function reject(Request $request, SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $request->validate([
            'approval_notes' => 'required|string|max:500',
        ]);

        $flow = $this->resolveApprovalFlow($sopDevelopmentCompletion);
        if (! $flow) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized atau SOP sudah diproses.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $flow->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'approved_at' => null,
                'comments' => $request->approval_notes,
            ]);

            $sopDevelopmentCompletion->update([
                'status' => 'rejected',
                'approval_notes' => $request->approval_notes,
                'rejected_at' => now(),
                'approved_at' => null,
            ]);

            NotificationService::insert([
                'user_id' => $sopDevelopmentCompletion->user_id,
                'type' => 'sop_development_rejected',
                'message' => "SOP \"{$sopDevelopmentCompletion->title}\" Anda ditolak. Silakan upload ulang dan ajukan approval kembali.",
                'url' => config('app.url').'/sop-development-completion',
                'is_read' => 0,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SOP berhasil ditolak.',
                'data' => $sopDevelopmentCompletion->fresh(['user:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error rejecting SOP: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak SOP.',
            ], 500);
        }
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->where('users.status', 'A')
            ->where('users.id', '!=', Auth::id())
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

    public function serveFile(SopDevelopmentCompletion $sopDevelopmentCompletion)
    {
        $user = Auth::user();

        if (! $this->canView($sopDevelopmentCompletion, $user)) {
            abort(403, 'Unauthorized');
        }

        if (! $sopDevelopmentCompletion->file_path) {
            abort(404, 'File not found');
        }

        if (! Storage::disk('public')->exists($sopDevelopmentCompletion->file_path)) {
            abort(404, 'File not found');
        }

        $fileName = $sopDevelopmentCompletion->file_original_name ?: basename($sopDevelopmentCompletion->file_path);

        return Storage::disk('public')->response(
            $sopDevelopmentCompletion->file_path,
            $fileName
        );
    }

    // --- Mobile API (approval-app) ---

    public function apiIndex(Request $request)
    {
        $user = Auth::user();
        $search = trim((string) $request->get('search', ''));
        $status = (string) $request->get('status', 'all');
        $perPage = (int) $request->get('per_page', 15);

        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $query = SopDevelopmentCompletion::query()
            ->with([
                'user:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        if (! $this->isSuperAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'records' => collect($paginator->items())
                ->map(fn (SopDevelopmentCompletion $record) => $this->serializeListRecord($record, $user))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'is_super_admin' => $this->isSuperAdmin($user),
        ]);
    }

    public function apiShow(int $id)
    {
        $record = SopDevelopmentCompletion::with([
            'user:id,nama_lengkap',
            'approvalFlows.approver:id,nama_lengkap',
        ])->findOrFail($id);

        $user = Auth::user();
        if (! $this->canView($record, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'record' => $this->serializeDetailRecord($record, $user),
            'can_edit' => $record->isEditableByOwner() && $this->isOwnerOrSuperAdmin($record, $user),
            'can_submit' => $record->canSubmitForApproval() && $this->isOwnerOrSuperAdmin($record, $user),
            'can_delete' => $this->canDeleteRecord($record, $user),
            'can_approve' => $this->canApprove($record),
        ]);
    }

    public function apiStore(Request $request)
    {
        return $this->store($request);
    }

    public function apiUpdate(Request $request, int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->update($request, $record);
    }

    public function apiDestroy(int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->destroy($record);
    }

    public function apiSearchApprovers(Request $request)
    {
        return $this->getApprovers($request);
    }

    public function apiPendingApprovals(Request $request)
    {
        return $this->getPendingApprovals($request);
    }

    public function apiSubmitForApproval(Request $request, int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->submitForApproval($request, $record);
    }

    public function apiApprove(Request $request, int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->approve($request, $record);
    }

    public function apiReject(Request $request, int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->reject($request, $record);
    }

    public function apiServeFile(int $id)
    {
        $record = SopDevelopmentCompletion::findOrFail($id);

        return $this->serveFile($record);
    }

    /**
     * @param  list<int>  $approverIds
     */
    private function syncApprovalFlows(SopDevelopmentCompletion $record, array $approverIds): void
    {
        SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $record->id)->delete();

        foreach ($approverIds as $index => $approverId) {
            SopDevelopmentCompletionApprovalFlow::create([
                'sop_development_completion_id' => $record->id,
                'approver_id' => (int) $approverId,
                'approval_level' => $index + 1,
                'status' => 'PENDING',
            ]);
        }

        $first = SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $record->id)
            ->where('approval_level', 1)
            ->first();

        if ($first) {
            $creator = Auth::user();
            NotificationService::insert([
                'user_id' => $first->approver_id,
                'type' => 'sop_development_approval',
                'message' => "SOP \"{$record->title}\" dari {$creator->nama_lengkap} membutuhkan persetujuan Anda (level 1).",
                'url' => config('app.url').'/home',
                'is_read' => 0,
            ]);
        }
    }

    private function isSuperAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        return (string) $user->id_role === self::SUPERADMIN_ROLE_ID
            || (int) ($user->id_jabatan ?? 0) === 160;
    }

    private function isOwnerOrSuperAdmin(SopDevelopmentCompletion $record, $user): bool
    {
        return $this->isSuperAdmin($user) || (int) $record->user_id === (int) $user->id;
    }

    private function canDeleteRecord(SopDevelopmentCompletion $record, $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ((int) $record->user_id !== (int) $user->id) {
            return false;
        }

        return $record->canBeDeletedByOwner();
    }

    private function serializeListRecord(SopDevelopmentCompletion $record, $user): array
    {
        return [
            'id' => $record->id,
            'title' => $record->title,
            'description' => $record->description,
            'due_date' => $record->due_date?->format('Y-m-d'),
            'status' => $record->status,
            'status_text' => $record->status_text,
            'file_path' => $record->file_path,
            'file_original_name' => $record->file_original_name,
            'is_overdue' => $record->is_overdue,
            'submitted_at' => $record->submitted_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
            'creator_name' => $record->user?->nama_lengkap,
            'user_id' => $record->user_id,
            'approval_flows' => $record->approvalFlows,
            'can_delete' => $this->canDeleteRecord($record, $user),
            'can_submit' => $record->canSubmitForApproval() && $this->isOwnerOrSuperAdmin($record, $user),
        ];
    }

    private function serializeDetailRecord(SopDevelopmentCompletion $record, $user): array
    {
        return array_merge($this->serializeListRecord($record, $user), [
            'approval_notes' => $record->approval_notes,
            'approved_at' => $record->approved_at?->toIso8601String(),
            'rejected_at' => $record->rejected_at?->toIso8601String(),
            'resubmission_count' => $record->resubmission_count,
            'user' => $record->user,
        ]);
    }

    private function ensureOwner(SopDevelopmentCompletion $record): void
    {
        if ((int) $record->user_id !== (int) Auth::id() && ! $this->isSuperAdmin(Auth::user())) {
            abort(403, 'Unauthorized');
        }
    }

    private function canView(SopDevelopmentCompletion $record, $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ((int) $record->user_id === (int) $user->id) {
            return true;
        }

        return $record->approvalFlows()
            ->where('approver_id', $user->id)
            ->exists();
    }

    private function canApprove(SopDevelopmentCompletion $record): bool
    {
        return $this->resolveApprovalFlow($record) !== null;
    }

    private function resolveApprovalFlow(SopDevelopmentCompletion $record): ?SopDevelopmentCompletionApprovalFlow
    {
        if ($record->status !== 'pending') {
            return null;
        }

        if ($this->isSuperAdmin(Auth::user())) {
            return SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $record->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        $userFlow = SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $record->id)
            ->where('approver_id', Auth::id())
            ->where('status', 'PENDING')
            ->first();

        if (! $userFlow) {
            return null;
        }

        $lowestPending = SopDevelopmentCompletionApprovalFlow::where('sop_development_completion_id', $record->id)
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if (! $lowestPending || (int) $lowestPending->id !== (int) $userFlow->id) {
            return null;
        }

        return $userFlow;
    }

    private function isVisiblePendingForUser(SopDevelopmentCompletion $record, int $userId, bool $isSuperadmin): bool
    {
        if ($isSuperadmin) {
            return true;
        }

        $record->loadMissing('approvalFlows');

        $userFlow = $record->approvalFlows->first(
            fn ($flow) => (int) $flow->approver_id === $userId && $flow->status === 'PENDING'
        );

        if (! $userFlow) {
            return false;
        }

        $lowestPending = $record->approvalFlows
            ->where('status', 'PENDING')
            ->sortBy('approval_level')
            ->first();

        return $lowestPending && (int) $lowestPending->id === (int) $userFlow->id;
    }

    private function repairLegacyApprovalFlows(): void
    {
        if (! Schema::hasTable('sop_development_completion_approval_flows')) {
            return;
        }

        if (! Schema::hasColumn('sop_development_completions', 'approver_id')) {
            return;
        }

        $rows = DB::table('sop_development_completions')
            ->whereNotNull('approver_id')
            ->where('status', 'pending')
            ->get(['id', 'approver_id']);

        foreach ($rows as $row) {
            $hasFlow = DB::table('sop_development_completion_approval_flows')
                ->where('sop_development_completion_id', $row->id)
                ->exists();

            if ($hasFlow) {
                continue;
            }

            DB::table('sop_development_completion_approval_flows')->insert([
                'sop_development_completion_id' => $row->id,
                'approver_id' => $row->approver_id,
                'approval_level' => 1,
                'status' => 'PENDING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
