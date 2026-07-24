<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OvertimeSubmission;
use App\Models\OvertimeSubmissionApprovalFlow;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $records = OvertimeSubmission::query()
            ->with(['creator:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap'])
            ->withCount('items')
            ->withCount(['items as employee_count' => fn ($q) => $q->select(DB::raw('COUNT(DISTINCT user_id)'))])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Attendance/OvertimeSubmissionIndex', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(OvertimeSubmission $overtimeSubmission): Response
    {
        $user = auth()->user();
        $overtimeSubmission->load([
            'creator:id,nama_lengkap',
            'items.user:id,nama_lengkap,nik',
            'approvalFlows.approver:id,nama_lengkap',
        ]);

        $canApprove = false;
        if ($user && $overtimeSubmission->status === OvertimeSubmission::STATUS_SUBMITTED) {
            $canApprove = (bool) $this->resolveCurrentApprovalFlow($overtimeSubmission, $user);
        }

        return Inertia::render('Attendance/OvertimeSubmissionShow', [
            'record' => $overtimeSubmission,
            'canApprove' => $canApprove,
            'canDelete' => (string) ($user?->id_role ?? '') === '5af56935b011a',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Attendance/OvertimeSubmissionForm', [
            'outlets' => Outlet::query()
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.user_id' => 'required|integer|exists:users,id',
            'items.*.overtime_date' => 'required|date',
            'items.*.requested_hours' => 'required|integer|min:1|max:24',
            'items.*.notes' => 'nullable|string|max:255',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ], [
            'approvers.required' => 'Pilih minimal 1 approver sebelum menyimpan.',
            'approvers.min' => 'Pilih minimal 1 approver sebelum menyimpan.',
        ]);

        $approverIds = collect($validated['approvers'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($approverIds === []) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal 1 approver sebelum menyimpan.',
                ], 422);
            }

            return back()->withErrors(['approvers' => 'Pilih minimal 1 approver sebelum menyimpan.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $submission = OvertimeSubmission::create([
                'number' => $this->generateNumber(),
                'submission_date' => $validated['submission_date'],
                'notes' => $validated['notes'] ?? null,
                'status' => OvertimeSubmission::STATUS_SUBMITTED,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $submission->items()->create([
                    'user_id' => (int) $item['user_id'],
                    'overtime_date' => $item['overtime_date'],
                    'requested_hours' => (float) $item['requested_hours'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            foreach ($approverIds as $index => $approverId) {
                OvertimeSubmissionApprovalFlow::create([
                    'overtime_submission_id' => $submission->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            $this->notifyNextApprover($submission);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            $submission->load([
                'creator:id,nama_lengkap',
                'items.user:id,nama_lengkap,nik',
                'approvalFlows.approver:id,nama_lengkap',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan lembur berhasil disimpan dan menunggu approval.',
                'submission' => $submission,
            ]);
        }

        return redirect()->route('overtime-submissions.index')
            ->with('success', 'Pengajuan lembur berhasil disimpan dan menunggu approval.');
    }

    public function destroy(OvertimeSubmission $overtimeSubmission)
    {
        if ((string) (auth()->user()?->id_role ?? '') !== '5af56935b011a') {
            abort(403, 'Hanya superadmin yang dapat menghapus data pengajuan lembur.');
        }

        $overtimeSubmission->delete();

        return redirect()->route('overtime-submissions.index')->with('success', 'Pengajuan lembur berhasil dihapus.');
    }

    public function apiIndex(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));

        $records = OvertimeSubmission::query()
            ->with(['creator:id,nama_lengkap', 'approvalFlows.approver:id,nama_lengkap'])
            ->withCount('items')
            ->withCount(['items as employee_count' => fn ($q) => $q->select(DB::raw('COUNT(DISTINCT user_id)'))])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $records,
            'can_delete' => (string) (auth()->user()?->id_role ?? '') === '5af56935b011a',
        ]);
    }

    public function apiCreateMeta()
    {
        return response()->json([
            'success' => true,
            'outlets' => Outlet::query()
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet']),
            'today' => now()->format('Y-m-d'),
            'can_delete' => (string) (auth()->user()?->id_role ?? '') === '5af56935b011a',
        ]);
    }

    public function apiDestroy($id)
    {
        if ((string) (auth()->user()?->id_role ?? '') !== '5af56935b011a') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya superadmin yang dapat menghapus data pengajuan lembur.',
            ], 403);
        }

        $submission = OvertimeSubmission::findOrFail($id);
        $submission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur berhasil dihapus.',
        ]);
    }

    public function searchUsers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $outletId = $request->get('outlet_id');

        $users = User::query()
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->where('users.status', 'A')
            ->when($outletId, fn ($q) => $q->where('users.id_outlet', $outletId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.nik', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit(30)
            ->get([
                'users.id',
                'users.nik',
                'users.nama_lengkap as name',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);

        return response()->json(['success' => true, 'users' => $users]);
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $users = User::query()
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->where('users.status', 'A')
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
                DB::raw('j.nama_jabatan as jabatan_name'),
            ]);

        return response()->json(['success' => true, 'approvers' => $users]);
    }

    public function getPendingApprovals()
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'submissions' => []], 401);
        }

        $isSuperadmin = $currentUser->id_role === '5af56935b011a';

        $query = OvertimeSubmission::query()
            ->where('status', OvertimeSubmission::STATUS_SUBMITTED)
            ->whereHas('approvalFlows', function ($q) use ($currentUser, $isSuperadmin) {
                $q->where('status', 'PENDING');
                if (! $isSuperadmin) {
                    $q->where('approver_id', $currentUser->id);
                }
            })
            ->with([
                'creator:id,nama_lengkap',
                'items.user:id,nama_lengkap,nik',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        $pending = $query->get();

        if (! $isSuperadmin) {
            $pending = $pending->filter(function (OvertimeSubmission $submission) use ($currentUser) {
                $next = $submission->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();

                return $next && (int) $next->approver_id === (int) $currentUser->id;
            })->values();
        }

        $pending = $pending->map(function (OvertimeSubmission $submission) {
            $next = $submission->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();
            $submission->approver_name = $next?->approver?->nama_lengkap;
            $submission->approval_level = $next?->approval_level;
            $submission->employee_count = $submission->items->pluck('user_id')->unique()->count();
            $submission->total_hours = round((float) $submission->items->sum('requested_hours'), 2);

            return $submission;
        });

        return response()->json([
            'success' => true,
            'submissions' => $pending->values(),
        ]);
    }

    public function getApprovalDetails($id)
    {
        $user = auth()->user();
        $submission = OvertimeSubmission::with([
            'creator:id,nama_lengkap',
            'items.user:id,nama_lengkap,nik',
            'approvalFlows.approver:id,nama_lengkap',
        ])->findOrFail($id);

        $canApprove = false;
        if ($user && $submission->status === OvertimeSubmission::STATUS_SUBMITTED) {
            $canApprove = (bool) $this->resolveCurrentApprovalFlow($submission, $user);
        }
        $submission->setAttribute('can_approve', $canApprove);

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $comments = $request->input('comments');

        try {
            DB::beginTransaction();

            $submission = OvertimeSubmission::with('approvalFlows')->findOrFail($id);
            if ($submission->status !== OvertimeSubmission::STATUS_SUBMITTED) {
                throw new \RuntimeException('Pengajuan tidak dalam status menunggu approval.');
            }

            $flow = $this->resolveCurrentApprovalFlow($submission, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk pengajuan ini.');
            }

            $flow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'comments' => $comments,
            ]);

            $stillPending = $submission->approvalFlows()
                ->where('status', 'PENDING')
                ->exists();

            if ($stillPending) {
                $this->notifyNextApprover($submission->fresh('approvalFlows'));
            } else {
                $submission->update([
                    'status' => OvertimeSubmission::STATUS_APPROVED,
                    'updated_by' => $user->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $stillPending
                    ? 'Approval berhasil. Menunggu approver berikutnya.'
                    : 'Pengajuan lembur fully approved.',
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
            'rejection_reason' => 'nullable|string|max:500',
            'comments' => 'nullable|string|max:500',
        ]);
        $comments = $validated['rejection_reason'] ?? $validated['comments'] ?? null;

        try {
            DB::beginTransaction();

            $submission = OvertimeSubmission::with('approvalFlows')->findOrFail($id);
            if ($submission->status !== OvertimeSubmission::STATUS_SUBMITTED) {
                throw new \RuntimeException('Pengajuan tidak dalam status menunggu approval.');
            }

            $flow = $this->resolveCurrentApprovalFlow($submission, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk pengajuan ini.');
            }

            $flow->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'comments' => $comments,
            ]);

            $submission->update([
                'status' => OvertimeSubmission::STATUS_REJECTED,
                'updated_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan lembur ditolak.',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal reject: '.$e->getMessage()], 500);
        }
    }

    private function resolveCurrentApprovalFlow(OvertimeSubmission $submission, $user): ?OvertimeSubmissionApprovalFlow
    {
        $pending = $submission->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
        $next = $pending->first();
        if (! $next) {
            return null;
        }

        $isSuperadmin = $user->id_role === '5af56935b011a';
        if ($isSuperadmin || (int) $next->approver_id === (int) $user->id) {
            return $next;
        }

        return null;
    }

    private function notifyNextApprover(OvertimeSubmission $submission): void
    {
        try {
            $next = $submission->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();

            if (! $next) {
                return;
            }

            $creatorName = User::where('id', $submission->created_by)->value('nama_lengkap') ?? 'User';
            $message = "Pengajuan lembur menunggu approval Anda:\n\n";
            $message .= "No: {$submission->number}\n";
            $message .= "Tanggal: {$submission->submission_date}\n";
            $message .= "Dibuat oleh: {$creatorName}";

            NotificationService::insert([
                'user_id' => $next->approver_id,
                'task_id' => $submission->id,
                'type' => 'overtime_submission_approval',
                'message' => $message,
                'url' => config('app.url').'/overtime-submissions',
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to notify overtime submission approver', [
                'submission_id' => $submission->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'OTR'.now()->format('Ymd');
        $last = OvertimeSubmission::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
