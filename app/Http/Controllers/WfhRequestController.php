<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WfhRequest;
use App\Models\WfhRequestApprovalFlow;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WfhRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $user = auth()->user();
        $isSuperadmin = $this->isSuperadmin($user);

        $records = WfhRequest::query()
            ->with([
                'user:id,nama_lengkap,nik,id_jabatan,division_id',
                'user.jabatan:id_jabatan,nama_jabatan',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->withCount('tasks')
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('user_id', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->orWhereHas('approvalFlows', fn ($f) => $f->where('approver_id', $user->id));
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('WfhRequest/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
            'canDelete' => $this->canDeleteWfh($user),
        ]);
    }

    public function create(): Response
    {
        $user = auth()->user()->load(['jabatan:id_jabatan,nama_jabatan', 'divisi:id,nama_divisi']);

        return Inertia::render('WfhRequest/Create', [
            'employee' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nik' => $user->nik,
                'jabatan' => $user->jabatan?->nama_jabatan,
                'divisi' => $user->divisi?->nama_divisi,
            ],
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function show($id): Response
    {
        $request = $this->findVisibleRequest($id);
        $user = auth()->user();
        $canApprove = (bool) $this->resolveCurrentApprovalFlow($request, $user);

        return Inertia::render('WfhRequest/Show', [
            'record' => $request,
            'canApprove' => $canApprove,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'wfh_date' => 'required|date',
            'reason' => 'required|string|max:2000',
            'tasks' => 'required|array|min:1|max:10',
            'tasks.*.description' => 'required|string|max:500',
            'approvers' => 'required|array|min:1',
            'approvers.*' => 'required|integer|exists:users,id',
        ], [
            'tasks.required' => 'Isi minimal 1 pekerjaan yang akan dikerjakan.',
            'tasks.min' => 'Isi minimal 1 pekerjaan yang akan dikerjakan.',
            'approvers.required' => 'Pilih minimal 1 approver sebelum menyimpan.',
            'approvers.min' => 'Pilih minimal 1 approver sebelum menyimpan.',
        ]);

        $user = auth()->user();
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

        $shiftContext = $this->resolveShiftContext($user->id, $validated['wfh_date']);
        if (! $shiftContext['ok']) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $shiftContext['message'],
                    'errors' => ['wfh_date' => [$shiftContext['message']]],
                ], 422);
            }

            return back()->withErrors(['wfh_date' => $shiftContext['message']])->withInput();
        }

        $duplicate = WfhRequest::query()
            ->where('user_id', $user->id)
            ->where('wfh_date', $validated['wfh_date'])
            ->whereIn('status', [WfhRequest::STATUS_SUBMITTED, WfhRequest::STATUS_APPROVED])
            ->exists();

        if ($duplicate) {
            $msg = 'Sudah ada pengajuan WFH aktif untuk tanggal ini.';
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'errors' => ['wfh_date' => [$msg]],
                ], 422);
            }

            return back()->withErrors(['wfh_date' => $msg])->withInput();
        }

        DB::beginTransaction();
        try {
            $wfh = WfhRequest::create([
                'number' => $this->generateNumber(),
                'user_id' => $user->id,
                'wfh_date' => $validated['wfh_date'],
                'reason' => $validated['reason'],
                'status' => WfhRequest::STATUS_SUBMITTED,
                'outlet_id' => $shiftContext['outlet_id'],
                'shift_id' => $shiftContext['shift_id'],
                'shift_name' => $shiftContext['shift_name'],
                'time_start' => $shiftContext['time_start'],
                'time_end' => $shiftContext['time_end'],
                'sn' => $shiftContext['sn'],
                'pin' => $shiftContext['pin'],
                'created_by' => $user->id,
            ]);

            foreach (array_values($validated['tasks']) as $index => $task) {
                $description = trim((string) ($task['description'] ?? ''));
                if ($description === '') {
                    continue;
                }
                $wfh->tasks()->create([
                    'sort_order' => $index + 1,
                    'description' => $description,
                ]);
            }

            if ($wfh->tasks()->count() === 0) {
                throw new \RuntimeException('Isi minimal 1 pekerjaan yang akan dikerjakan.');
            }

            foreach ($approverIds as $index => $approverId) {
                WfhRequestApprovalFlow::create([
                    'wfh_request_id' => $wfh->id,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                ]);
            }

            $this->notifyNextApprover($wfh);

            DB::commit();
            $this->clearPendingApprovalsCache($user->id);
            foreach ($approverIds as $approverId) {
                $this->clearPendingApprovalsCache($approverId);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['wfh_date' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            $wfh->load([
                'user:id,nama_lengkap,nik',
                'creator:id,nama_lengkap',
                'tasks',
                'approvalFlows.approver:id,nama_lengkap',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan WFH berhasil disimpan dan menunggu approval.',
                'request' => $wfh,
            ]);
        }

        return redirect()->route('wfh-requests.index')
            ->with('success', 'Pengajuan WFH berhasil disimpan dan menunggu approval.');
    }

    public function apiIndex(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $user = auth()->user();
        $isSuperadmin = $this->isSuperadmin($user);
        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));

        $records = WfhRequest::query()
            ->with([
                'user:id,nama_lengkap,nik,id_jabatan,division_id',
                'user.jabatan:id_jabatan,nama_jabatan',
                'creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->withCount('tasks')
            ->when(! $isSuperadmin, function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('user_id', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->orWhereHas('approvalFlows', fn ($f) => $f->where('approver_id', $user->id));
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('nama_lengkap', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $records,
            'can_delete' => $this->canDeleteWfh($user),
        ]);
    }

    public function apiCreateMeta()
    {
        $user = auth()->user()->load(['jabatan:id_jabatan,nama_jabatan', 'divisi:id,nama_divisi']);

        return response()->json([
            'success' => true,
            'employee' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nik' => $user->nik,
                'jabatan' => $user->jabatan?->nama_jabatan,
                'divisi' => $user->divisi?->nama_divisi,
            ],
            'today' => now()->format('Y-m-d'),
            'can_delete' => $this->canDeleteWfh($user),
        ]);
    }

    public function apiDestroy($id)
    {
        $user = auth()->user();
        if (! $this->canDeleteWfh($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak menghapus pengajuan WFH.',
            ], 403);
        }

        $wfhRequest = WfhRequest::findOrFail($id);

        try {
            DB::beginTransaction();
            if ($wfhRequest->status === WfhRequest::STATUS_APPROVED || $wfhRequest->att_log_written_at) {
                $this->removeAttendanceLogs($wfhRequest);
            }
            $wfhRequest->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: '.$e->getMessage(),
            ], 500);
        }

        $this->clearPendingApprovalsCache($user?->id);
        $this->clearPendingApprovalsCache((int) $wfhRequest->created_by);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan WFH berhasil dihapus beserta data absensi WFH-nya.',
        ]);
    }

    public function destroy(WfhRequest $wfhRequest)
    {
        if (! $this->canDeleteWfh(auth()->user())) {
            abort(403, 'Anda tidak berhak menghapus pengajuan WFH.');
        }

        DB::beginTransaction();
        try {
            if ($wfhRequest->status === WfhRequest::STATUS_APPROVED || $wfhRequest->att_log_written_at) {
                $this->removeAttendanceLogs($wfhRequest);
            }

            $wfhRequest->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('wfh-requests.index')
                ->with('error', 'Gagal menghapus pengajuan WFH: '.$e->getMessage());
        }

        $this->clearPendingApprovalsCache(auth()->id());
        $this->clearPendingApprovalsCache((int) $wfhRequest->created_by);

        return redirect()->route('wfh-requests.index')->with('success', 'Pengajuan WFH berhasil dihapus beserta data absensi WFH-nya.');
    }

    public function checkShift(Request $request)
    {
        $validated = $request->validate([
            'wfh_date' => 'required|date',
        ]);

        $context = $this->resolveShiftContext(auth()->id(), $validated['wfh_date']);

        return response()->json([
            'success' => $context['ok'],
            'message' => $context['message'] ?? null,
            'shift' => $context['ok'] ? [
                'shift_id' => $context['shift_id'],
                'shift_name' => $context['shift_name'],
                'time_start' => $context['time_start'],
                'time_end' => $context['time_end'],
                'outlet_id' => $context['outlet_id'],
                'outlet_name' => $context['outlet_name'],
            ] : null,
        ]);
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
                DB::raw('j.nama_jabatan as jabatan_name'),
            ]);

        return response()->json(['success' => true, 'approvers' => $users]);
    }

    public function getPendingApprovals()
    {
        $currentUser = auth()->user();
        if (! $currentUser) {
            return response()->json(['success' => false, 'requests' => []], 401);
        }

        $isSuperadmin = $this->isSuperadmin($currentUser);

        $query = WfhRequest::query()
            ->where('status', WfhRequest::STATUS_SUBMITTED)
            ->whereHas('approvalFlows', function ($q) use ($currentUser, $isSuperadmin) {
                $q->where('status', 'PENDING');
                if (! $isSuperadmin) {
                    $q->where('approver_id', $currentUser->id);
                }
            })
            ->with([
                'user:id,nama_lengkap,nik',
                'creator:id,nama_lengkap',
                'tasks',
                'approvalFlows.approver:id,nama_lengkap',
            ])
            ->orderByDesc('created_at');

        $pending = $query->get();

        if (! $isSuperadmin) {
            $pending = $pending->filter(function (WfhRequest $item) use ($currentUser) {
                $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();

                return $next && (int) $next->approver_id === (int) $currentUser->id;
            })->values();
        }

        $pending = $pending->map(function (WfhRequest $item) {
            $next = $item->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();
            $item->approver_name = $next?->approver?->nama_lengkap;
            $item->approval_level = $next?->approval_level;

            return $item;
        });

        return response()->json([
            'success' => true,
            'requests' => $pending->values(),
        ]);
    }

    public function getApprovalDetails($id)
    {
        $user = auth()->user();
        $record = WfhRequest::with([
            'user:id,nama_lengkap,nik,id_jabatan,division_id',
            'user.jabatan:id_jabatan,nama_jabatan',
            'user.divisi:id,nama_divisi',
            'creator:id,nama_lengkap',
            'tasks',
            'approvalFlows.approver:id,nama_lengkap',
        ])->findOrFail($id);

        $canApprove = false;
        if ($user && $record->status === WfhRequest::STATUS_SUBMITTED) {
            $canApprove = (bool) $this->resolveCurrentApprovalFlow($record, $user);
        }
        $record->setAttribute('can_approve', $canApprove);

        return response()->json([
            'success' => true,
            'request' => $record,
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

            $wfh = WfhRequest::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($wfh->status !== WfhRequest::STATUS_SUBMITTED) {
                throw new \RuntimeException('Pengajuan tidak dalam status menunggu approval.');
            }

            $flow = $this->resolveCurrentApprovalFlow($wfh, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk pengajuan ini.');
            }

            $flow->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'comments' => $comments,
            ]);

            $stillPending = $wfh->approvalFlows()
                ->where('status', 'PENDING')
                ->exists();

            if ($stillPending) {
                $this->notifyNextApprover($wfh->fresh('approvalFlows'));
            } else {
                $this->writeAttendanceLogs($wfh);
                $wfh->update([
                    'status' => WfhRequest::STATUS_APPROVED,
                    'att_log_written_at' => now(),
                    'updated_by' => $user->id,
                ]);
                $this->notifyCreatorApproved($wfh);
            }

            DB::commit();

            $this->clearPendingApprovalsCache($user->id);
            $this->clearPendingApprovalsCache((int) $wfh->created_by);
            foreach ($wfh->approvalFlows as $flowRow) {
                $this->clearPendingApprovalsCache((int) $flowRow->approver_id);
            }

            return response()->json([
                'success' => true,
                'message' => $stillPending
                    ? 'Approval berhasil. Menunggu approver berikutnya.'
                    : 'Pengajuan WFH fully approved. Absensi sudah dicatat dari jam shift.',
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

            $wfh = WfhRequest::with('approvalFlows')->lockForUpdate()->findOrFail($id);
            if ($wfh->status !== WfhRequest::STATUS_SUBMITTED) {
                throw new \RuntimeException('Pengajuan tidak dalam status menunggu approval.');
            }

            $flow = $this->resolveCurrentApprovalFlow($wfh, $user);
            if (! $flow) {
                throw new \RuntimeException('Anda bukan approver berikutnya untuk pengajuan ini.');
            }

            $flow->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
                'comments' => $comments,
            ]);

            $wfh->update([
                'status' => WfhRequest::STATUS_REJECTED,
                'updated_by' => $user->id,
            ]);

            $this->notifyCreatorRejected($wfh, $comments);

            DB::commit();

            $this->clearPendingApprovalsCache($user->id);
            $this->clearPendingApprovalsCache((int) $wfh->created_by);
            foreach ($wfh->approvalFlows as $flowRow) {
                $this->clearPendingApprovalsCache((int) $flowRow->approver_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan WFH ditolak.',
            ]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal reject: '.$e->getMessage()], 500);
        }
    }

    /**
     * @return array{ok: bool, message?: string, outlet_id?: int|null, outlet_name?: string|null, shift_id?: int|null, shift_name?: string|null, time_start?: string|null, time_end?: string|null, sn?: string|null, pin?: string|null}
     */
    private function resolveShiftContext(int $userId, string $wfhDate): array
    {
        $userShift = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.tanggal', $wfhDate)
            ->select(
                'us.id',
                'us.outlet_id',
                'us.shift_id',
                's.shift_name',
                's.time_start',
                's.time_end'
            )
            ->first();

        if (! $userShift) {
            return [
                'ok' => false,
                'message' => 'Shift mingguan untuk tanggal ini belum diinput. Silakan isi Input Shift Mingguan terlebih dahulu.',
            ];
        }

        if (empty($userShift->shift_id) || strtolower((string) $userShift->shift_name) === 'off') {
            return [
                'ok' => false,
                'message' => 'Tanggal ini berstatus OFF. WFH hanya boleh diajukan pada hari dengan shift kerja.',
            ];
        }

        if (empty($userShift->time_start) || empty($userShift->time_end)) {
            return [
                'ok' => false,
                'message' => 'Jam shift tidak lengkap. Periksa master jam kerja untuk shift ini.',
            ];
        }

        $user = User::find($userId);
        $outletId = $userShift->outlet_id ?: ($user?->id_outlet);

        if (! $outletId) {
            return [
                'ok' => false,
                'message' => 'Outlet shift / home outlet tidak ditemukan.',
            ];
        }

        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (! $outlet || empty($outlet->sn)) {
            return [
                'ok' => false,
                'message' => 'Outlet tidak memiliki serial number mesin absensi (SN). Hubungi admin.',
            ];
        }

        $userPin = DB::table('user_pins')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('is_active', 1)
            ->first();

        if (! $userPin) {
            return [
                'ok' => false,
                'message' => 'PIN absensi user tidak ditemukan untuk outlet shift. Hubungi admin.',
            ];
        }

        return [
            'ok' => true,
            'outlet_id' => (int) $outletId,
            'outlet_name' => $outlet->nama_outlet ?? null,
            'shift_id' => (int) $userShift->shift_id,
            'shift_name' => $userShift->shift_name,
            'time_start' => substr((string) $userShift->time_start, 0, 8),
            'time_end' => substr((string) $userShift->time_end, 0, 8),
            'sn' => $outlet->sn,
            'pin' => $userPin->pin,
        ];
    }

    private function writeAttendanceLogs(WfhRequest $wfh): void
    {
        $context = $this->resolveShiftContext((int) $wfh->user_id, $wfh->wfh_date->format('Y-m-d'));
        if (! $context['ok']) {
            throw new \RuntimeException($context['message'] ?? 'Gagal resolve shift untuk menulis absensi.');
        }

        $sn = $context['sn'];
        $pin = $context['pin'];
        $timeStart = $context['time_start'];
        $timeEnd = $context['time_end'];
        $wfhDate = $wfh->wfh_date->format('Y-m-d');

        $inScan = $wfhDate.' '.$timeStart;
        $outDate = $timeEnd <= $timeStart
            ? date('Y-m-d', strtotime($wfhDate.' +1 day'))
            : $wfhDate;
        $outScan = $outDate.' '.$timeEnd;

        $wfh->update([
            'outlet_id' => $context['outlet_id'],
            'shift_id' => $context['shift_id'],
            'shift_name' => $context['shift_name'],
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'sn' => $sn,
            'pin' => $pin,
        ]);

        $this->upsertAttLog($sn, $pin, $inScan, 1);
        $this->upsertAttLog($sn, $pin, $outScan, 2);
    }

    private function removeAttendanceLogs(WfhRequest $wfh): void
    {
        $scans = $this->resolveWfhScanDatetimes($wfh);
        if ($scans === null) {
            return;
        }

        foreach ([$scans['in'], $scans['out']] as $scanDate) {
            DB::table('att_log')
                ->where('sn', $scans['sn'])
                ->where('pin', $scans['pin'])
                ->where('scan_date', $scanDate)
                ->where('device_ip', 'WFH')
                ->delete();
        }
    }

    /**
     * @return array{sn: string, pin: string, in: string, out: string}|null
     */
    private function resolveWfhScanDatetimes(WfhRequest $wfh): ?array
    {
        $sn = $wfh->sn;
        $pin = $wfh->pin;
        $timeStart = $wfh->time_start ? substr((string) $wfh->time_start, 0, 8) : null;
        $timeEnd = $wfh->time_end ? substr((string) $wfh->time_end, 0, 8) : null;
        $wfhDate = $wfh->wfh_date?->format('Y-m-d');

        if ((! $sn || ! $pin || ! $timeStart || ! $timeEnd || ! $wfhDate) && $wfhDate) {
            $context = $this->resolveShiftContext((int) $wfh->user_id, $wfhDate);
            if ($context['ok']) {
                $sn = $sn ?: $context['sn'];
                $pin = $pin ?: $context['pin'];
                $timeStart = $timeStart ?: $context['time_start'];
                $timeEnd = $timeEnd ?: $context['time_end'];
            }
        }

        if (! $sn || ! $pin || ! $timeStart || ! $timeEnd || ! $wfhDate) {
            return null;
        }

        $inScan = $wfhDate.' '.$timeStart;
        $outDate = $timeEnd <= $timeStart
            ? date('Y-m-d', strtotime($wfhDate.' +1 day'))
            : $wfhDate;

        return [
            'sn' => $sn,
            'pin' => $pin,
            'in' => $inScan,
            'out' => $outDate.' '.$timeEnd,
        ];
    }

    private function upsertAttLog(string $sn, string $pin, string $scanDate, int $inoutmode): void
    {
        $existing = DB::table('att_log')
            ->where('sn', $sn)
            ->where('pin', $pin)
            ->where('scan_date', $scanDate)
            ->first();

        $payload = [
            'verifymode' => 1,
            'inoutmode' => $inoutmode,
            'device_ip' => 'WFH',
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('att_log')
                ->where('sn', $sn)
                ->where('pin', $pin)
                ->where('scan_date', $scanDate)
                ->update($payload);

            return;
        }

        DB::table('att_log')->insert(array_merge($payload, [
            'sn' => $sn,
            'pin' => $pin,
            'scan_date' => $scanDate,
            'created_at' => now(),
        ]));
    }

    private function resolveCurrentApprovalFlow(WfhRequest $wfh, $user): ?WfhRequestApprovalFlow
    {
        $pending = $wfh->approvalFlows->where('status', 'PENDING')->sortBy('approval_level');
        $next = $pending->first();
        if (! $next) {
            return null;
        }

        if ($this->isSuperadmin($user) || (int) $next->approver_id === (int) $user->id) {
            return $next;
        }

        return null;
    }

    private function findVisibleRequest($id): WfhRequest
    {
        $user = auth()->user();
        $query = WfhRequest::with([
            'user:id,nama_lengkap,nik,id_jabatan,division_id',
            'user.jabatan:id_jabatan,nama_jabatan',
            'user.divisi:id,nama_divisi',
            'creator:id,nama_lengkap',
            'tasks',
            'approvalFlows.approver:id,nama_lengkap',
        ]);

        if (! $this->isSuperadmin($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('approvalFlows', fn ($f) => $f->where('approver_id', $user->id));
            });
        }

        return $query->findOrFail($id);
    }

    private function notifyNextApprover(WfhRequest $wfh): void
    {
        try {
            $next = $wfh->approvalFlows()
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();

            if (! $next) {
                return;
            }

            $employeeName = User::where('id', $wfh->user_id)->value('nama_lengkap') ?? 'User';
            $message = "Pengajuan WFH menunggu approval Anda:\n\n";
            $message .= "No: {$wfh->number}\n";
            $message .= "Karyawan: {$employeeName}\n";
            $message .= 'Tanggal WFH: '.$wfh->wfh_date->format('d/m/Y');

            NotificationService::insert([
                'user_id' => $next->approver_id,
                'task_id' => $wfh->id,
                'type' => 'wfh_request_approval',
                'message' => $message,
                'url' => config('app.url').'/wfh-requests/'.$wfh->id,
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to notify WFH approver', [
                'wfh_request_id' => $wfh->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyCreatorApproved(WfhRequest $wfh): void
    {
        try {
            NotificationService::insert([
                'user_id' => $wfh->created_by,
                'task_id' => $wfh->id,
                'type' => 'wfh_request_approved',
                'message' => "Pengajuan WFH {$wfh->number} telah disetujui. Absensi dicatat dari jam shift.",
                'url' => config('app.url').'/wfh-requests/'.$wfh->id,
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to notify WFH creator approved', [
                'wfh_request_id' => $wfh->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyCreatorRejected(WfhRequest $wfh, ?string $comments): void
    {
        try {
            $message = "Pengajuan WFH {$wfh->number} ditolak.";
            if ($comments) {
                $message .= "\nAlasan: {$comments}";
            }

            NotificationService::insert([
                'user_id' => $wfh->created_by,
                'task_id' => $wfh->id,
                'type' => 'wfh_request_rejected',
                'message' => $message,
                'url' => config('app.url').'/wfh-requests/'.$wfh->id,
                'is_read' => 0,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to notify WFH creator rejected', [
                'wfh_request_id' => $wfh->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'WFH'.now()->format('Ymd');
        $last = WfhRequest::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function isSuperadmin($user): bool
    {
        return $user && (string) ($user->id_role ?? '') === '5af56935b011a';
    }

    private function canDeleteWfh($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isSuperadmin($user)) {
            return true;
        }

        return (int) ($user->division_id ?? 0) === 6;
    }

    private function clearPendingApprovalsCache(?int $userId = null): void
    {
        $ids = collect([$userId, auth()->id()])->filter()->unique();
        foreach ($ids as $id) {
            Cache::forget('all_pending_approvals_v3_'.$id);
            Cache::forget('all_pending_approvals_v2_'.$id);
            Cache::forget('all_pending_approvals_'.$id);
        }
    }
}
