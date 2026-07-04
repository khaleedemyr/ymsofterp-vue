<?php

namespace App\Services;

use App\Models\EmployeeOnboarding;
use App\Models\EmployeeOnboardingApprovalFlow;
use App\Models\EmployeeOnboardingItem;
use App\Models\EmployeeOnboardingWeekSubmission;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateArea;
use App\Models\OnboardingTemplateItem;
use App\Models\OnboardingTemplateWeek;
use App\Models\OnboardingTemplateWeekApprover;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeOnboardingService
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    public function isSuperAdmin(): bool
    {
        return (string) (Auth::user()?->id_role ?? '') === self::SUPERADMIN_ROLE_ID;
    }

    public function searchUsers(string $search = '', int $limit = 20): Collection
    {
        return User::query()
            ->where('users.status', 'A')
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->when(trim($search) !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('j.nama_jabatan', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nama_lengkap')
            ->limit($limit)
            ->get([
                'users.id',
                'users.nama_lengkap as name',
                'users.email',
                DB::raw('j.nama_jabatan as jabatan'),
            ]);
    }

    public function searchEmployees(string $search = '', int $limit = 20): Collection
    {
        return $this->searchUsers($search, $limit);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncTemplate(OnboardingTemplate $template, array $payload): OnboardingTemplate
    {
        $template->update([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'total_weeks' => (int) $payload['total_weeks'],
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'notes' => $payload['notes'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        OnboardingTemplateWeekApprover::where('template_id', $template->id)->delete();
        OnboardingTemplateItem::where('template_id', $template->id)->delete();
        OnboardingTemplateArea::where('template_id', $template->id)->delete();
        OnboardingTemplateWeek::where('template_id', $template->id)->delete();

        foreach ($payload['weeks'] ?? [] as $weekIndex => $weekData) {
            $week = OnboardingTemplateWeek::create([
                'template_id' => $template->id,
                'week_number' => (int) $weekData['week_number'],
                'week_label' => $weekData['week_label'] ?? null,
                'sort_order' => $weekIndex,
            ]);

            foreach ($weekData['areas'] ?? [] as $areaIndex => $areaData) {
                $area = OnboardingTemplateArea::create([
                    'template_id' => $template->id,
                    'week_id' => $week->id,
                    'area_name' => $areaData['area_name'],
                    'sort_order' => $areaIndex,
                ]);

                foreach ($areaData['items'] ?? [] as $itemIndex => $itemData) {
                    OnboardingTemplateItem::create([
                        'template_id' => $template->id,
                        'area_id' => $area->id,
                        'checklist_text' => $itemData['checklist_text'],
                        'pic_role_hint' => $itemData['pic_role_hint'] ?? null,
                        'sort_order' => $itemIndex,
                    ]);
                }
            }
        }

        foreach ($payload['week_approvers'] ?? [] as $approverRow) {
            OnboardingTemplateWeekApprover::create([
                'template_id' => $template->id,
                'week_number' => (int) $approverRow['week_number'],
                'approver_user_id' => (int) $approverRow['approver_user_id'],
                'approval_level' => (int) $approverRow['approval_level'],
            ]);
        }

        return $template->fresh(['weeks.areas.items', 'weekApprovers.approver']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createInstance(array $payload): EmployeeOnboarding
    {
        $template = OnboardingTemplate::with(['weeks.areas.items'])->findOrFail($payload['template_id']);
        $employee = User::findOrFail($payload['employee_user_id']);
        $outlet = ! empty($payload['outlet_id']) ? Outlet::find($payload['outlet_id']) : null;

        $onboarding = EmployeeOnboarding::create([
            'number' => $this->generateNumber(),
            'template_id' => $template->id,
            'template_name' => $template->name,
            'employee_user_id' => $employee->id,
            'outlet_id' => $outlet?->id_outlet,
            'outlet_name' => $outlet?->nama_outlet,
            'start_date' => $payload['start_date'],
            'current_week' => 1,
            'unlocked_week' => 1,
            'total_weeks' => $template->total_weeks,
            'status' => 'in_progress',
            'notes' => $payload['notes'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $assignments = collect($payload['item_assignments'] ?? [])->keyBy('template_item_id');
        $sort = 0;

        foreach ($template->weeks as $week) {
            foreach ($week->areas as $area) {
                foreach ($area->items as $item) {
                    $assignment = $assignments->get($item->id);
                    EmployeeOnboardingItem::create([
                        'onboarding_id' => $onboarding->id,
                        'template_item_id' => $item->id,
                        'week_number' => $week->week_number,
                        'area_name' => $area->area_name,
                        'checklist_text' => $item->checklist_text,
                        'pic_role_hint' => $item->pic_role_hint,
                        'assigned_pic_user_id' => $assignment['assigned_pic_user_id'] ?? null,
                        'status' => 'pending',
                        'sort_order' => $sort++,
                        'updated_by' => Auth::id(),
                    ]);
                }
            }
        }

        return $onboarding->fresh(['items.assignedPic', 'employee', 'weekSubmissions.approvalFlows']);
    }

    public function canManageOnboarding(EmployeeOnboarding $onboarding): bool
    {
        return $this->isSuperAdmin() || (int) Auth::id() === (int) $onboarding->created_by;
    }

    public function canEditWeek(EmployeeOnboarding $onboarding, int $weekNumber): bool
    {
        if ($onboarding->status === 'completed' || $onboarding->status === 'cancelled') {
            return false;
        }

        if ($this->canManageOnboarding($onboarding)) {
            return $weekNumber <= $onboarding->unlocked_week;
        }

        if ($weekNumber > $onboarding->unlocked_week) {
            return false;
        }

        $submission = EmployeeOnboardingWeekSubmission::where('onboarding_id', $onboarding->id)
            ->where('week_number', $weekNumber)
            ->first();
        if ($submission && in_array($submission->status, ['submitted', 'approved'], true)) {
            return false;
        }

        return true;
    }

    public function canUpdateItem(EmployeeOnboardingItem $item): bool
    {
        $onboarding = $item->onboarding;
        if (! $this->canEditWeek($onboarding, (int) $item->week_number)) {
            return false;
        }

        if ($this->canManageOnboarding($onboarding)) {
            return true;
        }

        return (int) Auth::id() === (int) $item->assigned_pic_user_id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function updateItems(EmployeeOnboarding $onboarding, array $items): void
    {
        foreach ($items as $row) {
            $item = EmployeeOnboardingItem::where('onboarding_id', $onboarding->id)
                ->where('id', $row['id'])
                ->firstOrFail();

            if (! $this->canUpdateItem($item)) {
                throw ValidationException::withMessages([
                    'items' => "Anda tidak memiliki akses mengubah item #{$item->id}.",
                ]);
            }

            $item->update([
                'assigned_pic_user_id' => array_key_exists('assigned_pic_user_id', $row)
                    ? ($row['assigned_pic_user_id'] ?: null)
                    : $item->assigned_pic_user_id,
                'status' => $row['status'] ?? $item->status,
                'remark' => array_key_exists('remark', $row) ? ($row['remark'] ?: null) : $item->remark,
                'updated_by' => Auth::id(),
            ]);
        }
    }

    /**
     * @param  list<int>  $approverIds
     */
    public function submitWeek(EmployeeOnboarding $onboarding, int $weekNumber, array $approverIds = []): EmployeeOnboardingWeekSubmission
    {
        if (! $this->canEditWeek($onboarding, $weekNumber) && ! $this->canManageOnboarding($onboarding)) {
            throw ValidationException::withMessages(['week' => 'Minggu ini tidak dapat disubmit.']);
        }

        if ($weekNumber !== (int) $onboarding->unlocked_week) {
            throw ValidationException::withMessages(['week' => 'Hanya minggu aktif yang dapat disubmit.']);
        }

        $weekItems = $onboarding->items()->where('week_number', $weekNumber)->get();
        if ($weekItems->isEmpty()) {
            throw ValidationException::withMessages(['week' => 'Tidak ada checklist pada minggu ini.']);
        }

        if ($weekItems->contains(fn ($item) => $item->status !== 'done')) {
            throw ValidationException::withMessages(['week' => 'Semua checklist minggu ini harus berstatus Done sebelum submit.']);
        }

        if ($weekItems->contains(fn ($item) => empty($item->assigned_pic_user_id))) {
            throw ValidationException::withMessages(['week' => 'Semua item harus memiliki PIC sebelum submit.']);
        }

        $existing = EmployeeOnboardingWeekSubmission::where('onboarding_id', $onboarding->id)
            ->where('week_number', $weekNumber)
            ->first();

        if ($existing && $existing->status === 'approved') {
            throw ValidationException::withMessages(['week' => 'Minggu ini sudah disetujui.']);
        }

        if ($existing) {
            EmployeeOnboardingApprovalFlow::where('week_submission_id', $existing->id)->delete();
            $existing->delete();
        }

        $approverIds = $this->resolveApproverIds($onboarding, $weekNumber, $approverIds);
        if (empty($approverIds)) {
            throw ValidationException::withMessages(['approvers' => 'Pilih minimal satu approver.']);
        }

        $submission = EmployeeOnboardingWeekSubmission::create([
            'onboarding_id' => $onboarding->id,
            'week_number' => $weekNumber,
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
        ]);

        foreach ($approverIds as $index => $approverId) {
            EmployeeOnboardingApprovalFlow::create([
                'week_submission_id' => $submission->id,
                'approver_id' => (int) $approverId,
                'approval_level' => $index + 1,
                'status' => 'PENDING',
            ]);
        }

        $onboarding->update(['current_week' => $weekNumber, 'updated_by' => Auth::id()]);

        return $submission->fresh('approvalFlows.approver');
    }

    public function approveWeekSubmission(EmployeeOnboardingWeekSubmission $submission, string $action, ?string $comments = null): void
    {
        $flow = $this->resolveApprovalFlow($submission);
        if (! $flow) {
            throw ValidationException::withMessages(['approval' => 'Anda tidak memiliki hak approval untuk minggu ini.']);
        }

        if (in_array($action, ['reject', 'requires_revision'], true) && empty(trim((string) $comments))) {
            throw ValidationException::withMessages([
                'comments' => $action === 'requires_revision' ? 'Catatan revisi wajib diisi.' : 'Alasan penolakan wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($submission, $flow, $action, $comments) {
            $onboarding = $submission->onboarding()->lockForUpdate()->first();

            if ($action === 'approve') {
                $flow->update([
                    'status' => 'APPROVED',
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'comments' => $comments,
                ]);

                $pending = EmployeeOnboardingApprovalFlow::where('week_submission_id', $submission->id)
                    ->where('status', 'PENDING')
                    ->count();

                if ($pending === 0) {
                    $submission->update(['status' => 'approved', 'approved_at' => now()]);
                    $nextWeek = $submission->week_number + 1;

                    if ($nextWeek > $onboarding->total_weeks) {
                        $onboarding->update([
                            'status' => 'completed',
                            'updated_by' => Auth::id(),
                        ]);
                    } else {
                        $onboarding->update([
                            'unlocked_week' => $nextWeek,
                            'current_week' => $nextWeek,
                            'updated_by' => Auth::id(),
                        ]);
                    }
                }
            } elseif ($action === 'requires_revision') {
                $flow->update([
                    'status' => 'REQUIRES_REVISION',
                    'rejected_at' => now(),
                    'approved_at' => null,
                    'comments' => $comments,
                ]);
                $submission->update(['status' => 'requires_revision']);
            } else {
                $flow->update([
                    'status' => 'REJECTED',
                    'rejected_at' => now(),
                    'approved_at' => null,
                    'comments' => $comments,
                ]);
                $submission->update(['status' => 'rejected']);
            }
        });
    }

    public function getPendingApprovals(): Collection
    {
        $userId = Auth::id();
        $isSuperadmin = $this->isSuperAdmin();

        $query = EmployeeOnboardingWeekSubmission::query()
            ->where('status', 'submitted')
            ->whereHas('approvalFlows', fn ($q) => $q->where('status', 'PENDING'))
            ->with([
                'onboarding.employee:id,nama_lengkap',
                'onboarding.creator:id,nama_lengkap',
                'approvalFlows.approver:id,nama_lengkap',
            ]);

        if (! $isSuperadmin) {
            $query->whereHas('approvalFlows', fn ($q) => $q->where('approver_id', $userId)->where('status', 'PENDING'));
        }

        return $query->orderByDesc('submitted_at')->get()->filter(function (EmployeeOnboardingWeekSubmission $submission) use ($userId, $isSuperadmin) {
            if ($isSuperadmin) {
                return true;
            }

            $pending = $submission->approvalFlows->where('status', 'PENDING')->sortBy('approval_level')->first();

            return $pending && (int) $pending->approver_id === (int) $userId;
        })->values()->map(fn (EmployeeOnboardingWeekSubmission $submission) => [
            'id' => $submission->onboarding_id,
            'submission_id' => $submission->id,
            'week_number' => $submission->week_number,
            'number' => $submission->onboarding->number,
            'template_name' => $submission->onboarding->template_name,
            'employee_name' => $submission->onboarding->employee?->nama_lengkap,
            'outlet_name' => $submission->onboarding->outlet_name,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
        ]);
    }

    public function serializeTemplate(OnboardingTemplate $template): array
    {
        $template->load(['weeks.areas.items', 'weekApprovers.approver:id,nama_lengkap']);

        return [
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'total_weeks' => $template->total_weeks,
            'is_active' => $template->is_active,
            'notes' => $template->notes,
            'weeks' => $template->weeks->map(fn ($week) => [
                'id' => $week->id,
                'week_number' => $week->week_number,
                'week_label' => $week->week_label,
                'areas' => $week->areas->map(fn ($area) => [
                    'id' => $area->id,
                    'area_name' => $area->area_name,
                    'items' => $area->items->map(fn ($item) => [
                        'id' => $item->id,
                        'checklist_text' => $item->checklist_text,
                        'pic_role_hint' => $item->pic_role_hint,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'week_approvers' => $template->weekApprovers->map(fn ($row) => [
                'week_number' => $row->week_number,
                'approval_level' => $row->approval_level,
                'approver_user_id' => $row->approver_user_id,
                'approver_name' => $row->approver?->nama_lengkap,
            ])->values()->all(),
        ];
    }

    public function serializeOnboarding(EmployeeOnboarding $onboarding): array
    {
        $onboarding->load([
            'employee:id,nama_lengkap,email',
            'creator:id,nama_lengkap',
            'items.assignedPic:id,nama_lengkap',
            'weekSubmissions.approvalFlows.approver:id,nama_lengkap',
        ]);

        $weeks = [];
        for ($w = 1; $w <= $onboarding->total_weeks; $w++) {
            $submission = $onboarding->weekSubmissions->firstWhere('week_number', $w);
            $weeks[] = [
                'week_number' => $w,
                'is_unlocked' => $w <= $onboarding->unlocked_week,
                'is_current' => $w === $onboarding->unlocked_week,
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'approved_at' => $submission->approved_at?->toIso8601String(),
                    'approval_flows' => $submission->approvalFlows->map(fn ($flow) => [
                        'id' => $flow->id,
                        'approval_level' => $flow->approval_level,
                        'status' => $flow->status,
                        'comments' => $flow->comments,
                        'approver_name' => $flow->approver?->nama_lengkap,
                    ])->values()->all(),
                ] : null,
                'items' => $onboarding->items->where('week_number', $w)->values()->map(fn ($item) => [
                    'id' => $item->id,
                    'template_item_id' => $item->template_item_id,
                    'area_name' => $item->area_name,
                    'checklist_text' => $item->checklist_text,
                    'pic_role_hint' => $item->pic_role_hint,
                    'assigned_pic_user_id' => $item->assigned_pic_user_id,
                    'assigned_pic_name' => $item->assignedPic?->nama_lengkap,
                    'status' => $item->status,
                    'remark' => $item->remark,
                    'can_edit' => $this->canUpdateItem($item),
                ])->values()->all(),
            ];
        }

        return [
            'id' => $onboarding->id,
            'number' => $onboarding->number,
            'template_id' => $onboarding->template_id,
            'template_name' => $onboarding->template_name,
            'employee_user_id' => $onboarding->employee_user_id,
            'employee_name' => $onboarding->employee?->nama_lengkap,
            'outlet_id' => $onboarding->outlet_id,
            'outlet_name' => $onboarding->outlet_name,
            'start_date' => $onboarding->start_date?->format('Y-m-d'),
            'current_week' => $onboarding->current_week,
            'unlocked_week' => $onboarding->unlocked_week,
            'total_weeks' => $onboarding->total_weeks,
            'status' => $onboarding->status,
            'notes' => $onboarding->notes,
            'weeks' => $weeks,
            'can_manage' => $this->canManageOnboarding($onboarding),
        ];
    }

    /**
     * @param  list<int>  $overrideIds
     * @return list<int>
     */
    private function resolveApproverIds(EmployeeOnboarding $onboarding, int $weekNumber, array $overrideIds): array
    {
        if (! empty($overrideIds)) {
            return array_values(array_unique(array_map('intval', $overrideIds)));
        }

        return OnboardingTemplateWeekApprover::where('template_id', $onboarding->template_id)
            ->where('week_number', $weekNumber)
            ->orderBy('approval_level')
            ->pluck('approver_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function resolveApprovalFlowForUser(EmployeeOnboardingWeekSubmission $submission): ?EmployeeOnboardingApprovalFlow
    {
        return $this->resolveApprovalFlow($submission);
    }

    private function resolveApprovalFlow(EmployeeOnboardingWeekSubmission $submission): ?EmployeeOnboardingApprovalFlow
    {
        if ($this->isSuperAdmin()) {
            return EmployeeOnboardingApprovalFlow::where('week_submission_id', $submission->id)
                ->where('status', 'PENDING')
                ->orderBy('approval_level')
                ->first();
        }

        $userFlow = EmployeeOnboardingApprovalFlow::where('week_submission_id', $submission->id)
            ->where('approver_id', Auth::id())
            ->where('status', 'PENDING')
            ->first();

        if (! $userFlow) {
            return null;
        }

        $lowestPending = EmployeeOnboardingApprovalFlow::where('week_submission_id', $submission->id)
            ->where('status', 'PENDING')
            ->orderBy('approval_level')
            ->first();

        if ($lowestPending && (int) $lowestPending->id !== (int) $userFlow->id) {
            return null;
        }

        return $userFlow;
    }

    private function generateNumber(): string
    {
        $prefix = 'ONB-'.now()->format('Ymd').'-';
        $last = EmployeeOnboarding::withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
