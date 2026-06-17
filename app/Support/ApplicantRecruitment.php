<?php

namespace App\Support;

use App\Models\JobVacancyApplication;
use Illuminate\Database\Eloquent\Builder;

class ApplicantRecruitment
{
    public const PENDING = 'pending';

    public const OK = 'ok';

    public const NOK = 'nok';

    public const STEP_OPTIONS = [
        self::PENDING,
        self::OK,
        self::NOK,
    ];

    public const STEP_LABELS = [
        self::PENDING => 'Pending',
        self::OK => 'Lolos',
        self::NOK => 'Tidak Lolos',
    ];

    public static function emptyCounts(): array
    {
        return [
            'sourcing' => 0,
            'screening_cv_ok' => 0,
            'screening_cv_nok' => 0,
            'hr_interview_ok' => 0,
            'hr_interview_nok' => 0,
            'user_interview_ok' => 0,
            'user_interview_nok' => 0,
            'loi' => 0,
            'joined' => 0,
        ];
    }

    public static function aggregateCounts(int $vacancyId): array
    {
        $base = JobVacancyApplication::query()->where('job_vacancy_id', $vacancyId);
        $counts = self::emptyCounts();
        $counts['sourcing'] = (clone $base)->count();
        $counts['screening_cv_ok'] = self::countStep($base, 'screening_status', self::OK);
        $counts['screening_cv_nok'] = self::countStep($base, 'screening_status', self::NOK);
        $counts['hr_interview_ok'] = self::countStep($base, 'hr_interview_status', self::OK);
        $counts['hr_interview_nok'] = self::countStep($base, 'hr_interview_status', self::NOK);
        $counts['user_interview_ok'] = self::countStep($base, 'user_interview_status', self::OK);
        $counts['user_interview_nok'] = self::countStep($base, 'user_interview_status', self::NOK);
        $counts['loi'] = self::countStep($base, 'loi_status', self::OK);
        $counts['joined'] = (clone $base)->whereNotNull('joined_at')->count();

        return $counts;
    }

    private static function countStep(Builder $base, string $column, string $value): int
    {
        return (clone $base)->where($column, $value)->count();
    }

    public static function syncLegacyStatus(JobVacancyApplication $application): void
    {
        if ($application->joined_at) {
            $application->status = 'hired';

            return;
        }

        if (in_array($application->loi_status, [self::OK, self::NOK], true)
            || in_array($application->user_interview_status, [self::OK, self::NOK], true)
            || in_array($application->hr_interview_status, [self::OK, self::NOK], true)) {
            $application->status = 'interview';

            return;
        }

        if (in_array($application->screening_status, [self::OK, self::NOK], true)) {
            $application->status = $application->screening_status === self::NOK ? 'rejected' : 'reviewed';

            return;
        }

        $application->status = 'submitted';
    }
}
