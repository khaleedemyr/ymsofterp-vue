<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class HrdApprovalAccess
{
    public const SUPERADMIN_ROLE_ID = '5af56935b011a';

    public const HR_APPROVER_JABATAN_ID = 309;

    public static function isSuperadmin(?User $user): bool
    {
        return $user !== null && $user->id_role === self::SUPERADMIN_ROLE_ID;
    }

    public static function isHrApprover(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return (int) $user->id_jabatan === self::HR_APPROVER_JABATAN_ID;
    }

    public static function canAccessHrdApprovals(?User $user): bool
    {
        return self::isSuperadmin($user) || self::isHrApprover($user);
    }

    public static function hrdApproverUsersQuery(): Builder
    {
        return DB::table('users')
            ->where('id_jabatan', self::HR_APPROVER_JABATAN_ID)
            ->where('status', 'A');
    }

    public static function hrdApproverUserIds(): array
    {
        return self::hrdApproverUsersQuery()->pluck('id')->all();
    }

    public static function firstHrdApprover()
    {
        return self::hrdApproverUsersQuery()
            ->select('id', 'nama_lengkap', 'email')
            ->orderBy('id')
            ->first();
    }

    public static function hrdApproverDisplayName(): string
    {
        $approver = self::hrdApproverUsersQuery()
            ->select('nama_lengkap')
            ->orderBy('id')
            ->first();

        return $approver?->nama_lengkap ?? 'HR';
    }

    public static function pendingLeaveHrdApprovalsCount(): int
    {
        return (int) DB::table('approval_requests')
            ->where('status', 'approved')
            ->where('hrd_status', 'pending')
            ->count();
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
