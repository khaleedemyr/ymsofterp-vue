<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $period = AttendancePayrollPeriod::forHrdApprovalQueue();

        return (int) DB::table('approval_requests')
            ->where('status', 'approved')
            ->where('hrd_status', 'pending')
            ->where(function ($q) use ($period) {
                self::applyLeaveDateOverlapsPeriod($q, $period['start'], $period['end']);
            })
            ->count();
    }

    /**
     * Ringkasan antrian izin/cuti + koreksi absensi untuk dashboard HRD
     * (hanya periode absen berjalan: 26 bln lalu – 25 bln ini).
     *
     * @return array{
     *     period: array{bulan: int, tahun: int, start: string, end: string, label: string},
     *     pending_supervisor_count: int,
     *     pending_supervisor_oldest_at: ?string,
     *     pending_hrd_count: int,
     *     pending_hrd_oldest_at: ?string,
     *     pending_leave_count: int,
     *     pending_leave_oldest_at: ?string,
     *     pending_correction_count: int,
     *     pending_correction_oldest_at: ?string,
     *     pending_total_count: int,
     *     last_hrd_approved_at: ?string
     * }
     */
    public static function leaveApprovalQueueSummary(): array
    {
        $period = AttendancePayrollPeriod::forHrdApprovalQueue();
        $start = $period['start'];
        $end = $period['end'];

        $supervisor = DB::table('approval_requests')
            ->where('status', 'pending')
            ->where(function ($q) use ($start, $end) {
                self::applyLeaveDateOverlapsPeriod($q, $start, $end);
            })
            ->selectRaw('COUNT(*) as cnt, MIN(created_at) as oldest_at')
            ->first();

        $hrd = DB::table('approval_requests')
            ->where('status', 'approved')
            ->where('hrd_status', 'pending')
            ->where(function ($q) use ($start, $end) {
                self::applyLeaveDateOverlapsPeriod($q, $start, $end);
            })
            ->selectRaw('COUNT(*) as cnt, MIN(COALESCE(approved_at, created_at)) as oldest_at')
            ->first();

        $correctionQuery = DB::table('schedule_attendance_correction_approvals')
            ->whereBetween('tanggal', [$start, $end]);

        if (Schema::hasTable('schedule_attendance_correction_approval_flows')) {
            $flowIds = DB::table('schedule_attendance_correction_approval_flows')
                ->distinct()
                ->pluck('approval_id');
            $correctionQuery->where(function ($q) use ($flowIds) {
                $q->where('status', 'supervisor_approved');
                if ($flowIds->isEmpty()) {
                    $q->orWhere('status', 'pending');
                } else {
                    $q->orWhere(function ($qq) use ($flowIds) {
                        $qq->where('status', 'pending')
                            ->whereNotIn('id', $flowIds);
                    });
                }
            });
        } else {
            $correctionQuery->where('status', 'pending');
        }

        $correction = $correctionQuery
            ->selectRaw('COUNT(*) as cnt, MIN(created_at) as oldest_at')
            ->first();

        $lastHrdApprovedAt = DB::table('approval_requests')
            ->where('hrd_status', 'approved')
            ->whereNotNull('hrd_approved_at')
            ->max('hrd_approved_at');

        $pendingSupervisor = (int) ($supervisor->cnt ?? 0);
        $pendingHrd = (int) ($hrd->cnt ?? 0);
        $pendingCorrection = (int) ($correction->cnt ?? 0);
        $pendingLeave = $pendingSupervisor + $pendingHrd;

        $leaveOldestCandidates = array_values(array_filter([
            $supervisor->oldest_at ?? null,
            $hrd->oldest_at ?? null,
        ]));
        $pendingLeaveOldestAt = $leaveOldestCandidates === []
            ? null
            : min($leaveOldestCandidates);

        return [
            'period' => $period,
            'pending_supervisor_count' => $pendingSupervisor,
            'pending_supervisor_oldest_at' => $supervisor->oldest_at ?? null,
            'pending_hrd_count' => $pendingHrd,
            'pending_hrd_oldest_at' => $hrd->oldest_at ?? null,
            'pending_leave_count' => $pendingLeave,
            'pending_leave_oldest_at' => $pendingLeaveOldestAt,
            'pending_correction_count' => $pendingCorrection,
            'pending_correction_oldest_at' => $correction->oldest_at ?? null,
            'pending_total_count' => $pendingLeave + $pendingCorrection,
            'last_hrd_approved_at' => $lastHrdApprovedAt,
        ];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyLeaveDateOverlapsPeriod($query, string $start, string $end, string $table = 'approval_requests'): void
    {
        $dateFrom = $table !== '' ? "{$table}.date_from" : 'date_from';
        $dateTo = $table !== '' ? "{$table}.date_to" : 'date_to';

        $query->where(function ($q) use ($start, $end, $dateFrom, $dateTo) {
            $q->whereBetween($dateFrom, [$start, $end])
                ->orWhereBetween($dateTo, [$start, $end])
                ->orWhere(function ($qq) use ($start, $end, $dateFrom, $dateTo) {
                    $qq->where($dateFrom, '<=', $start)
                        ->where($dateTo, '>=', $end);
                });
        });
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
