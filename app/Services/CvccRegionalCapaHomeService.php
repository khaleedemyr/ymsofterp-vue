<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kasus CVCC yang di-tag ke user regional dan masih menunggu CAPA selesai (isi + approved).
 */
class CvccRegionalCapaHomeService
{
    private const DIVISIONS = ['service', 'kitchen', 'bar'];

    private const MAX_CANDIDATES = 80;

    private const MAX_ITEMS = 20;

    public function __construct(
        private FeedbackCapaService $capaService,
        private FeedbackCapaApprovalService $capaApprovalService,
    ) {}

    /**
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    public function pendingForUser(int $userId): array
    {
        if ($userId <= 0 || ! Schema::hasTable('feedback_cases')) {
            return ['count' => 0, 'items' => []];
        }

        $rows = $this->fetchTaggedCases($userId);
        if ($rows === []) {
            return ['count' => 0, 'items' => []];
        }

        $caseIds = array_map(static fn ($row) => (int) $row->id, $rows);
        $approvalsByCase = $this->capaApprovalService->summariesForCases($caseIds);

        $items = [];
        foreach ($rows as $row) {
            $caseId = (int) $row->id;
            $meta = $this->decodeMeta($row->meta ?? null);
            if (! $this->userIsTaggedRegional($meta, $userId)) {
                continue;
            }

            if ($this->isCapaFilledAndApproved($caseId, $meta, $approvalsByCase[$caseId] ?? null)) {
                continue;
            }

            $statusKey = $this->resolvePendingStatus($caseId, $meta, $approvalsByCase[$caseId] ?? null);
            $items[] = [
                'id' => $caseId,
                'nama_outlet' => (string) ($row->nama_outlet ?? ''),
                'summary_id' => (string) ($row->summary_id ?? ''),
                'severity' => (string) ($row->severity ?? ''),
                'status' => (string) ($row->status ?? ''),
                'event_at' => $row->event_at !== null ? (string) $row->event_at : null,
                'regional_assigned_at' => isset($meta['regional_assigned_at'])
                    ? (string) $meta['regional_assigned_at']
                    : null,
                'capa_status' => $statusKey,
                'capa_status_label' => $statusKey === 'awaiting_approval'
                    ? 'CAPA terisi — menunggu approval'
                    : 'Belum diisi CAPA',
            ];

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return list<object>
     */
    private function fetchTaggedCases(int $userId): array
    {
        $query = DB::table('feedback_cases as c')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'c.id_outlet')
            ->whereNotNull('c.meta')
            ->where(function ($q) use ($userId) {
                // ID di JSON bisa number atau string tergantung writer lama.
                $q->whereRaw(
                    'JSON_CONTAINS(COALESCE(c.meta, JSON_OBJECT()), ?, ?)',
                    [json_encode($userId), '$.regional_user_ids']
                )->orWhereRaw(
                    'JSON_CONTAINS(COALESCE(c.meta, JSON_OBJECT()), ?, ?)',
                    [json_encode((string) $userId), '$.regional_user_ids']
                );
            })
            ->orderByDesc('c.event_at')
            ->limit(self::MAX_CANDIDATES)
            ->select([
                'c.id',
                'c.meta',
                'c.summary_id',
                'c.severity',
                'c.status',
                'c.event_at',
                'o.nama_outlet',
            ]);

        return $query->get()->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function userIsTaggedRegional(array $meta, int $userId): bool
    {
        $ids = $meta['regional_user_ids'] ?? null;
        if (! is_array($ids)) {
            return false;
        }

        foreach ($ids as $id) {
            if ((int) $id === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Selesai untuk widget Home: ada CAPA terisi di minimal satu divisi dan approval divisi itu approved.
     *
     * @param  array<string, mixed>  $meta
     * @param  array{service?: array<string,mixed>, kitchen?: array<string,mixed>, bar?: array<string,mixed>}|null  $approvals
     */
    private function isCapaFilledAndApproved(int $caseId, array $meta, ?array $approvals): bool
    {
        $divisions = $this->normalizeCapaDivisionsFromMeta($meta);

        foreach (self::DIVISIONS as $division) {
            $stored = $divisions[$division] ?? null;
            if (! $this->capaService->storedCapaHasUserInput($stored)) {
                continue;
            }

            $summary = is_array($approvals[$division] ?? null)
                ? $approvals[$division]
                : $this->capaApprovalService->divisionSummary($caseId, $division);

            if (($summary['state'] ?? '') === 'approved') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{service?: array<string,mixed>, kitchen?: array<string,mixed>, bar?: array<string,mixed>}|null  $approvals
     */
    private function resolvePendingStatus(int $caseId, array $meta, ?array $approvals): string
    {
        $divisions = $this->normalizeCapaDivisionsFromMeta($meta);
        $anyFilled = false;

        foreach (self::DIVISIONS as $division) {
            $stored = $divisions[$division] ?? null;
            if (! $this->capaService->storedCapaHasUserInput($stored)) {
                continue;
            }
            $anyFilled = true;

            $summary = is_array($approvals[$division] ?? null)
                ? $approvals[$division]
                : $this->capaApprovalService->divisionSummary($caseId, $division);

            if (in_array(($summary['state'] ?? ''), ['pending', 'rejected'], true)) {
                return 'awaiting_approval';
            }
        }

        return $anyFilled ? 'awaiting_approval' : 'needs_fill';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{service: array<string, mixed>|null, kitchen: array<string, mixed>|null, bar: array<string, mixed>|null}
     */
    private function normalizeCapaDivisionsFromMeta(array $meta): array
    {
        $out = ['service' => null, 'kitchen' => null, 'bar' => null];
        $divs = $meta['capa_divisions'] ?? null;
        if (is_array($divs)) {
            foreach (self::DIVISIONS as $k) {
                if (isset($divs[$k]) && is_array($divs[$k])) {
                    $out[$k] = $divs[$k];
                }
            }
        }

        if ($out['service'] === null && isset($meta['capa']) && is_array($meta['capa'])) {
            $out['service'] = $meta['capa'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }
        if (! is_string($meta) || $meta === '') {
            return [];
        }
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
