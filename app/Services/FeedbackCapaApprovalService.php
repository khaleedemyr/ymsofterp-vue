<?php

namespace App\Services;

use App\Models\FeedbackCapaApprovalFlow;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeedbackCapaApprovalService
{
    private const DIVISIONS = ['service', 'kitchen', 'bar'];

    private const SUPERADMIN_ROLE = '5af56935b011a';

    /**
     * @return array<int, array{id: int, name: string, email: string|null, jabatan: string|null}>
     */
    public function searchApprovers(string $search = '', int $limit = 20): array
    {
        $search = trim($search);

        $query = User::query()
            ->where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->select(
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan'
            )
            ->orderBy('users.nama_lengkap');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get()->map(fn ($u) => [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'email' => $u->email !== null ? (string) $u->email : null,
            'jabatan' => $u->jabatan !== null ? (string) $u->jabatan : null,
        ])->all();
    }

    /**
     * @param  array<int, int>  $caseIds
     * @return array<int, array{service: array<string,mixed>, kitchen: array<string,mixed>, bar: array<string,mixed>}>
     */
    public function summariesForCases(array $caseIds): array
    {
        $caseIds = array_values(array_filter(array_map('intval', $caseIds), fn ($id) => $id > 0));
        if ($caseIds === []) {
            return [];
        }

        $flows = FeedbackCapaApprovalFlow::query()
            ->whereIn('feedback_case_id', $caseIds)
            ->with('approver:id,nama_lengkap')
            ->orderBy('feedback_case_id')
            ->orderBy('division')
            ->orderBy('approval_level')
            ->get()
            ->groupBy('feedback_case_id');

        $out = [];
        foreach ($caseIds as $caseId) {
            $grouped = ($flows->get($caseId) ?? collect())->groupBy('division');
            $out[$caseId] = [
                'service' => $this->buildSummaryFromFlows($grouped->get('service') ?? collect()),
                'kitchen' => $this->buildSummaryFromFlows($grouped->get('kitchen') ?? collect()),
                'bar' => $this->buildSummaryFromFlows($grouped->get('bar') ?? collect()),
            ];
        }

        return $out;
    }

    /**
     * @return array{state: string, flows: array<int, array<string, mixed>>, next_approver_id: int|null, can_submit: bool, can_resubmit: bool}
     */
    public function divisionSummary(int $caseId, string $division): array
    {
        $flows = $this->flowsForCaseDivision($caseId, $division);

        return $this->buildSummaryFromFlows($flows);
    }

    /**
     * @param  array<int, int>  $approverIds
     * @return array{success: bool, message: string}
     */
    public function submitForApproval(int $caseId, string $division, array $approverIds, int $submittedByUserId): array
    {
        $division = $this->normalizeDivision($division);
        $approverIds = array_values(array_unique(array_filter(array_map('intval', $approverIds), fn ($id) => $id > 0)));

        if ($approverIds === []) {
            return ['success' => false, 'message' => 'Pilih minimal satu approver.'];
        }

        $existing = $this->flowsForCaseDivision($caseId, $division);
        $summary = $this->buildSummaryFromFlows($existing);

        if ($summary['state'] === 'approved') {
            return ['success' => false, 'message' => 'CAPA divisi ini sudah disetujui semua approver.'];
        }

        if ($summary['state'] === 'pending') {
            return ['success' => false, 'message' => 'Approval masih berjalan. Tunggu hingga selesai atau ditolak sebelum mengajukan ulang.'];
        }

        $caseExists = DB::table('feedback_cases')->where('id', $caseId)->exists();
        if (! $caseExists) {
            return ['success' => false, 'message' => 'Case tidak ditemukan.'];
        }

        DB::transaction(function () use ($caseId, $division, $approverIds, $submittedByUserId, $existing) {
            if ($existing->isNotEmpty()) {
                FeedbackCapaApprovalFlow::query()
                    ->where('feedback_case_id', $caseId)
                    ->where('division', $division)
                    ->delete();
            }

            foreach ($approverIds as $index => $approverId) {
                FeedbackCapaApprovalFlow::create([
                    'feedback_case_id' => $caseId,
                    'division' => $division,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1,
                    'status' => 'PENDING',
                    'submitted_by_user_id' => $index === 0 ? $submittedByUserId : null,
                ]);
            }
        });

        $firstApproverId = $approverIds[0];
        if ($firstApproverId !== $submittedByUserId) {
            $this->notifyApprover($caseId, $firstApproverId, $division, 1);
        }

        return ['success' => true, 'message' => 'CAPA berhasil diajukan untuk approval.'];
    }

    /**
     * @return array{success: bool, message: string, summary?: array<string, mixed>}
     */
    public function approve(int $caseId, string $division, int $approverId, bool $approved, ?string $comments, bool $isSuperadmin = false): array
    {
        $division = $this->normalizeDivision($division);
        $comments = $comments !== null ? trim($comments) : null;

        $flows = $this->flowsForCaseDivision($caseId, $division);
        if ($flows->isEmpty()) {
            return ['success' => false, 'message' => 'Tidak ada approval flow untuk divisi ini.'];
        }

        if ($flows->contains(fn ($f) => $f->status === 'REJECTED')) {
            return ['success' => false, 'message' => 'Approval sudah ditolak. Ajukan ulang dengan approver baru.'];
        }

        $flow = null;
        $nextPending = $flows->first(fn ($f) => $f->status === 'PENDING');

        if ($isSuperadmin) {
            $flow = $nextPending;
        } elseif ($nextPending !== null && (int) $nextPending->approver_id === $approverId) {
            $flow = $nextPending;
        }

        if ($flow === null) {
            return ['success' => false, 'message' => 'Anda tidak memiliki hak untuk approve CAPA ini.'];
        }

        $update = [
            'status' => $approved ? 'APPROVED' : 'REJECTED',
            'approved_at' => $approved ? now() : null,
            'rejected_at' => ! $approved ? now() : null,
            'comments' => $comments !== '' ? $comments : null,
        ];
        if ($isSuperadmin) {
            $update['approver_id'] = $approverId;
        }
        $flow->update($update);

        $submitterId = (int) ($flows->first()?->submitted_by_user_id ?? 0);

        if (! $approved) {
            if ($submitterId > 0) {
                $this->notifySubmitterRejected($caseId, $submitterId, $division, $comments);
            }

            return [
                'success' => true,
                'message' => 'CAPA ditolak.',
                'summary' => $this->divisionSummary($caseId, $division),
            ];
        }

        $remaining = $this->flowsForCaseDivision($caseId, $division)
            ->first(fn ($f) => $f->status === 'PENDING');

        if ($remaining) {
            $this->notifyApprover($caseId, (int) $remaining->approver_id, $division, (int) $remaining->approval_level);
        } elseif ($submitterId > 0) {
            $this->notifySubmitterApproved($caseId, $submitterId, $division);
        }

        return [
            'success' => true,
            'message' => $remaining ? 'Approval tersimpan. Menunggu approver berikutnya.' : 'Semua approval selesai.',
            'summary' => $this->divisionSummary($caseId, $division),
        ];
    }

    /**
     * Kasus + divisi yang menunggu approval user login (level berikutnya).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingItemsForUser(int $userId, bool $isSuperadmin = false, int $limit = 30): array
    {
        if ($userId <= 0) {
            return [];
        }

        $query = FeedbackCapaApprovalFlow::query()
            ->where('status', 'PENDING')
            ->select('feedback_case_id', 'division')
            ->distinct();

        if (! $isSuperadmin) {
            $query->where('approver_id', $userId);
        }

        $pairs = $query->limit(100)->get();
        if ($pairs->isEmpty()) {
            return [];
        }

        $caseIds = $pairs->pluck('feedback_case_id')->unique()->values()->all();
        $rows = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->whereIn('c.id', $caseIds)
            ->orderByDesc('c.updated_at')
            ->limit($limit)
            ->get([
                'c.id',
                'c.event_at',
                'c.summary_id',
                'c.status',
                'c.follow_up_status',
                'c.severity',
                'o.nama_outlet',
            ]);

        $items = [];
        foreach ($rows as $row) {
            $caseId = (int) $row->id;
            $pendingDivisions = [];
            foreach (self::DIVISIONS as $division) {
                if ($this->userCanActOnDivision($caseId, $division, $userId, $isSuperadmin)) {
                    $pendingDivisions[] = $division;
                }
            }
            if ($pendingDivisions === []) {
                continue;
            }
            $items[] = [
                'id' => $caseId,
                'event_at' => $row->event_at,
                'summary_id' => $row->summary_id,
                'status' => (string) ($row->status ?? ''),
                'follow_up_status' => (string) ($row->follow_up_status ?? 'new'),
                'severity' => (string) ($row->severity ?? ''),
                'nama_outlet' => (string) ($row->nama_outlet ?? ''),
                'pending_divisions' => $pendingDivisions,
            ];
        }

        return $items;
    }

    public function userCanActOnDivision(int $caseId, string $division, int $userId, bool $isSuperadmin): bool
    {
        $flows = $this->flowsForCaseDivision($caseId, $division);
        if ($flows->isEmpty() || $flows->contains(fn ($f) => $f->status === 'REJECTED')) {
            return false;
        }

        $next = $flows->first(fn ($f) => $f->status === 'PENDING');
        if ($next === null) {
            return false;
        }

        if ($isSuperadmin) {
            return true;
        }

        return (int) $next->approver_id === $userId;
    }

    public function isSuperadmin(?User $user): bool
    {
        return $user !== null && (string) $user->id_role === self::SUPERADMIN_ROLE;
    }

    /**
     * @param  Collection<int, FeedbackCapaApprovalFlow>  $collection
     * @return array{state: string, flows: array<int, array<string, mixed>>, next_approver_id: int|null, can_submit: bool, can_resubmit: bool}
     */
    private function buildSummaryFromFlows(Collection $collection): array
    {
        if ($collection->isEmpty()) {
            return [
                'state' => 'none',
                'flows' => [],
                'next_approver_id' => null,
                'can_submit' => true,
                'can_resubmit' => false,
            ];
        }

        $flows = $collection->sortBy('approval_level')->values();
        $formatted = $flows->map(fn ($f) => $this->formatFlow($f))->all();

        if ($flows->contains(fn ($f) => $f->status === 'REJECTED')) {
            return [
                'state' => 'rejected',
                'flows' => $formatted,
                'next_approver_id' => null,
                'can_submit' => false,
                'can_resubmit' => true,
            ];
        }

        $next = $flows->first(fn ($f) => $f->status === 'PENDING');
        if ($next !== null) {
            return [
                'state' => 'pending',
                'flows' => $formatted,
                'next_approver_id' => (int) $next->approver_id,
                'can_submit' => false,
                'can_resubmit' => false,
            ];
        }

        if ($flows->every(fn ($f) => $f->status === 'APPROVED')) {
            return [
                'state' => 'approved',
                'flows' => $formatted,
                'next_approver_id' => null,
                'can_submit' => false,
                'can_resubmit' => false,
            ];
        }

        return [
            'state' => 'none',
            'flows' => $formatted,
            'next_approver_id' => null,
            'can_submit' => true,
            'can_resubmit' => false,
        ];
    }

    /**
     * @return Collection<int, FeedbackCapaApprovalFlow>
     */
    private function flowsForCaseDivision(int $caseId, string $division): Collection
    {
        return FeedbackCapaApprovalFlow::query()
            ->where('feedback_case_id', $caseId)
            ->where('division', $this->normalizeDivision($division))
            ->with('approver:id,nama_lengkap')
            ->orderBy('approval_level')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFlow(FeedbackCapaApprovalFlow $flow): array
    {
        return [
            'id' => (int) $flow->id,
            'approval_level' => (int) $flow->approval_level,
            'approver_id' => (int) $flow->approver_id,
            'status' => (string) $flow->status,
            'comments' => $flow->comments,
            'approved_at' => $flow->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $flow->rejected_at?->format('Y-m-d H:i:s'),
            'approver' => $flow->approver ? [
                'id' => (int) $flow->approver->id,
                'nama_lengkap' => (string) $flow->approver->nama_lengkap,
            ] : null,
        ];
    }

    private function normalizeDivision(string $division): string
    {
        $v = strtolower(trim($division));

        return in_array($v, self::DIVISIONS, true) ? $v : 'service';
    }

    private function notifyApprover(int $caseId, int $approverId, string $division, int $level): void
    {
        $label = ucfirst($division);
        NotificationService::create([
            'user_id' => $approverId,
            'type' => 'customer_voice_capa_approval_required',
            'title' => 'CAPA — approval diperlukan',
            'message' => "CAPA {$label} case #{$caseId} menunggu approval Anda (level {$level}).",
            'url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId.'&capa_approval=1'),
        ]);
    }

    private function notifySubmitterApproved(int $caseId, int $submitterId, string $division): void
    {
        NotificationService::create([
            'user_id' => $submitterId,
            'type' => 'customer_voice_capa_approved',
            'title' => 'CAPA — disetujui',
            'message' => 'Semua approver telah menyetujui CAPA '.ucfirst($division)." case #{$caseId}.",
            'url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
        ]);
    }

    private function notifySubmitterRejected(int $caseId, int $submitterId, string $division, ?string $comments): void
    {
        $msg = 'CAPA '.ucfirst($division)." case #{$caseId} ditolak.";
        if ($comments) {
            $msg .= ' Alasan: '.$comments;
        }
        NotificationService::create([
            'user_id' => $submitterId,
            'type' => 'customer_voice_capa_rejected',
            'title' => 'CAPA — ditolak',
            'message' => $msg,
            'url' => url('/customer-voice-command-center?show_all=1&open_case='.$caseId),
        ]);
    }
}
