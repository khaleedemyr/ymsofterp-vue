<?php

namespace App\Services;

use App\Models\OvertimeSubmissionItem;

class OvertimeSubmissionFilterService
{
    /**
     * Sementara: jam OT di report/payroll memakai absensi, tidak di-cap pengajuan lembur.
     * Set true jika OT Submission dipakai lagi sebagai batas jam OT.
     */
    public const CAP_BY_SUBMISSION = false;

    public function mapKey(int $userId, string $date): string
    {
        return $userId.'_'.$date;
    }

    /**
     * Pengajuan lembur APPROVED per user per tanggal.
     *
     * @param  list<int>  $userIds
     * @return array<string, array{hours: float, reason: ?string}>
     */
    public function batchApprovedByUserDate(array $userIds, string $startDate, string $endDate): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $items = OvertimeSubmissionItem::query()
            ->join('overtime_submissions as os', 'overtime_submission_items.submission_id', '=', 'os.id')
            ->whereNull('os.deleted_at')
            ->where('os.status', 'APPROVED')
            ->whereIn('overtime_submission_items.user_id', $userIds)
            ->whereBetween('overtime_submission_items.overtime_date', [$startDate, $endDate])
            ->get([
                'overtime_submission_items.user_id',
                'overtime_submission_items.overtime_date',
                'overtime_submission_items.requested_hours',
                'overtime_submission_items.notes as item_notes',
                'os.notes as submission_notes',
            ]);

        $result = [];
        foreach ($items as $item) {
            $date = $item->overtime_date;
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d');
            }

            $key = $this->mapKey((int) $item->user_id, (string) $date);
            $reason = trim((string) ($item->item_notes ?: $item->submission_notes ?: ''));

            if (! isset($result[$key])) {
                $result[$key] = [
                    'hours' => 0.0,
                    'reason' => $reason !== '' ? $reason : null,
                ];
            }

            $result[$key]['hours'] += (float) $item->requested_hours;
            if (! $result[$key]['reason'] && $reason !== '') {
                $result[$key]['reason'] = $reason;
            }
        }

        return $result;
    }

    public function capHours(float $actualOvertimeHours, ?float $requestedOvertimeHours): float
    {
        if (! self::CAP_BY_SUBMISSION) {
            return $actualOvertimeHours;
        }

        if ($requestedOvertimeHours === null || $requestedOvertimeHours <= 0) {
            return $actualOvertimeHours;
        }

        return min($actualOvertimeHours, $requestedOvertimeHours);
    }

    /**
     * @param  array{hours: float, reason: ?string}|null  $submission
     * @return array{
     *     lembur: float,
     *     total_lembur: float,
     *     overtime_submission_hours: ?float,
     *     overtime_submission_reason: ?string
     * }
     */
    public function applyToDay(
        float $lemburActual,
        float $extraOffHours = 0,
        ?array $submission = null,
        ?float $onePlusOneHours = null
    ): array {
        $lemburActual = max(0, floor($lemburActual));
        $extraOffHours = max(0, $extraOffHours);
        $actualTotal = floor($lemburActual + $extraOffHours);
        $requested = isset($submission['hours']) ? (float) $submission['hours'] : null;
        $total = $this->capHours($actualTotal, $requested);
        $total = max(0, $total - max(0, $onePlusOneHours ?? 0));

        return [
            'lembur' => min($lemburActual, $total),
            'total_lembur' => $total,
            'overtime_submission_hours' => $requested !== null && $requested > 0 ? $requested : null,
            'overtime_submission_reason' => $submission['reason'] ?? null,
        ];
    }
}
