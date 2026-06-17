<?php

namespace App\Support;

class RecruitmentStage
{
    public const STAGES = [
        'sourcing',
        'screening_cv_ok',
        'screening_cv_nok',
        'hr_interview_ok',
        'hr_interview_nok',
        'user_interview_ok',
        'user_interview_nok',
        'loi',
        'joined',
    ];

    public const LABELS = [
        'sourcing' => 'Sourcing',
        'screening_cv_ok' => 'Screening CV - Lolos',
        'screening_cv_nok' => 'Screening CV - Tidak Lolos',
        'hr_interview_ok' => 'HR Interview - Lolos',
        'hr_interview_nok' => 'HR Interview - Tidak Lolos',
        'user_interview_ok' => 'User Interview - Lolos',
        'user_interview_nok' => 'User Interview - Tidak Lolos',
        'loi' => 'LOI',
        'joined' => 'Join',
    ];

    public const DASHBOARD_BUCKETS = [
        'sourcing',
        'screening_cv_ok',
        'screening_cv_nok',
        'hr_interview_ok',
        'hr_interview_nok',
        'user_interview_ok',
        'user_interview_nok',
        'loi',
        'joined',
    ];

    public static function label(string $stage): string
    {
        return self::LABELS[$stage] ?? $stage;
    }

    public static function emptyCounts(): array
    {
        return array_fill_keys(self::DASHBOARD_BUCKETS, 0);
    }

    public static function legacyStatusForStage(string $stage): string
    {
        return match ($stage) {
            'sourcing' => 'submitted',
            'screening_cv_ok', 'screening_cv_nok' => 'reviewed',
            'hr_interview_ok', 'hr_interview_nok', 'user_interview_ok', 'user_interview_nok', 'loi' => 'interview',
            'joined' => 'hired',
            default => 'rejected',
        };
    }
}
