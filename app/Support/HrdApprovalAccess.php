<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class HrdApprovalAccess
{
    public const SUPERADMIN_ROLE_ID = '5af56935b011a';

    /** Human Resources Generalist — approver HRD utama */
    public const HR_APPROVER_JABATAN_ID = 309;

    /**
     * Jabatan tambahan yang boleh melihat & approve HRD
     * (izin/cuti tahap HRD + koreksi attendance).
     * 153 = General Manager Human Resources
     *
     * @var list<int>
     */
    public const ADDITIONAL_HR_APPROVER_JABATAN_IDS = [153];

    /**
     * @return list<int>
     */
    public static function hrApproverJabatanIds(): array
    {
        return array_values(array_unique(array_merge(
            [self::HR_APPROVER_JABATAN_ID],
            self::ADDITIONAL_HR_APPROVER_JABATAN_IDS
        )));
    }

    public static function isSuperadmin(?User $user): bool
    {
        return $user !== null && $user->id_role === self::SUPERADMIN_ROLE_ID;
    }

    public static function isHrApprover(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array((int) $user->id_jabatan, self::hrApproverJabatanIds(), true);
    }

    public static function canAccessHrdApprovals(?User $user): bool
    {
        return self::isSuperadmin($user) || self::isHrApprover($user);
    }

    public static function hrdApproverUsersQuery(): Builder
    {
        return DB::table('users')
            ->whereIn('id_jabatan', self::hrApproverJabatanIds())
            ->where('status', 'A');
    }

    public static function hrdApproverUserIds(): array
    {
        return self::hrdApproverUsersQuery()->pluck('id')->all();
    }

    public static function firstHrdApprover()
    {
        // Prefer jabatan HRD utama (309) untuk label/notifikasi "kepada HR"
        $primary = self::hrdApproverUsersQuery()
            ->where('id_jabatan', self::HR_APPROVER_JABATAN_ID)
            ->select('id', 'nama_lengkap', 'email')
            ->orderBy('id')
            ->first();

        if ($primary) {
            return $primary;
        }

        return self::hrdApproverUsersQuery()
            ->select('id', 'nama_lengkap', 'email')
            ->orderBy('id')
            ->first();
    }

    public static function hrdApproverDisplayName(): string
    {
        $approver = self::firstHrdApprover();

        return $approver?->nama_lengkap ?? 'HR';
    }

    public static function pendingLeaveHrdApprovalsCount(): int
    {
        return (int) DB::table('approval_requests')
            ->where('status', 'approved')
            ->where('hrd_status', 'pending')
            ->count();
    }

    /**
     * Ringkasan antrian izin/cuti untuk dashboard HRD (company-wide).
     *
     * @return array{
     *     pending_supervisor_count: int,
     *     pending_supervisor_oldest_at: ?string,
     *     pending_hrd_count: int,
     *     pending_hrd_oldest_at: ?string,
     *     last_hrd_approved_at: ?string
     * }
     */
    public static function leaveApprovalQueueSummary(): array
    {
        $supervisor = DB::table('approval_requests')
            ->where('status', 'pending')
            ->selectRaw('COUNT(*) as cnt, MIN(created_at) as oldest_at')
            ->first();

        $hrd = DB::table('approval_requests')
            ->where('status', 'approved')
            ->where('hrd_status', 'pending')
            ->selectRaw('COUNT(*) as cnt, MIN(COALESCE(approved_at, created_at)) as oldest_at')
            ->first();

        $lastHrdApprovedAt = DB::table('approval_requests')
            ->where('hrd_status', 'approved')
            ->whereNotNull('hrd_approved_at')
            ->max('hrd_approved_at');

        return [
            'pending_supervisor_count' => (int) ($supervisor->cnt ?? 0),
            'pending_supervisor_oldest_at' => $supervisor->oldest_at ?? null,
            'pending_hrd_count' => (int) ($hrd->cnt ?? 0),
            'pending_hrd_oldest_at' => $hrd->oldest_at ?? null,
            'last_hrd_approved_at' => $lastHrdApprovedAt,
        ];
    }

    public static function notifyHrdApprovers(array $notification): void
    {
        foreach (self::hrdApproverUserIds() as $userId) {
            \App\Services\NotificationService::insert(array_merge($notification, [
                'user_id' => $userId,
            ]));
        }
    }
}
