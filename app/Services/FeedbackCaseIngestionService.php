<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackCaseIngestionService
{
    public function ingestAll(int $limitPerSource = 2000): array
    {
        $google = $this->ingestGoogleAndInstagramAiItems($limitPerSource);
        $guest = $this->ingestGuestCommentForms($limitPerSource);

        return [
            'google_instagram' => $google,
            'guest_comment' => $guest,
            'total_upserted' => $google['upserted'] + $guest['upserted'],
        ];
    }

    public function ingestGoogleAndInstagramAiItems(int $limit = 2000): array
    {
        $selectGi = [
            'i.id',
            'i.report_id',
            'i.author',
            'i.review_date',
            'i.text',
            'i.severity',
            'i.topics',
            'i.summary_id',
            'i.source_account',
            'i.source_post_url',
            'r.source',
            'r.id_outlet',
            'i.created_at',
        ];
        if (Schema::hasColumn('google_review_ai_items', 'follow_up_target')) {
            $selectGi[] = 'i.follow_up_target';
            $selectGi[] = 'i.impact';
        }

        $rows = DB::table('google_review_ai_items as i')
            ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
            ->select($selectGi)
            ->where('r.status', 'completed')
            ->orderByDesc('i.id')
            ->limit(max(1, min(10000, $limit)))
            ->get();

        $payload = [];
        foreach ($rows as $row) {
            $source = (string) ($row->source ?? '');
            $sourceType = $source === 'instagram_comments_db' ? 'instagram_comment' : 'google_review';
            $eventAt = $this->normalizeEventAt($row->review_date ?? null, $row->created_at ?? null);
            $severity = $this->normalizeSeverity($row->severity ?? null);
            $topics = $this->normalizeTopics($row->topics ?? null);
            $risk = $this->riskScoreFromSeverity($severity);
            $sla = $this->slaMinutesFromSeverity($severity);

            $dueAt = null;
            if ($sla !== null) {
                $dueAt = Carbon::parse($eventAt)->addMinutes($sla)->format('Y-m-d H:i:s');
            }

            $meta = [
                'source' => $source,
                'source_post_url' => (string) ($row->source_post_url ?? ''),
                'origin' => 'google_review_ai_items',
            ];
            if (Schema::hasColumn('google_review_ai_items', 'follow_up_target')) {
                $fu = $this->nullableTrim($row->follow_up_target ?? null);
                $impact = $this->normalizeImpactJson($row->impact ?? null);
                if ($fu !== null) {
                    $meta['follow_up_target'] = $fu;
                }
                if ($impact !== []) {
                    $meta['impact'] = $impact;
                }
            }

            $payload[] = [
                'source_type' => $sourceType,
                'source_ref' => 'gri:'.$row->id,
                'source_report_id' => (int) $row->report_id,
                'source_item_id' => (int) $row->id,
                'id_outlet' => $row->id_outlet !== null ? (int) $row->id_outlet : null,
                'channel_account' => $this->nullableTrim($row->source_account ?? null),
                'author_name' => $this->nullableTrim($row->author ?? null),
                'customer_contact' => null,
                'event_at' => $eventAt,
                'severity' => $severity,
                'topics' => json_encode($topics, JSON_UNESCAPED_UNICODE),
                'summary_id' => $this->limitText($row->summary_id ?? null, 500),
                'raw_text' => $row->text !== null ? (string) $row->text : null,
                'risk_score' => $risk,
                'sla_minutes' => $sla,
                'due_at' => $dueAt,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload !== []) {
            DB::table('feedback_cases')->upsert(
                $payload,
                ['source_ref'],
                [
                    'source_type',
                    'source_report_id',
                    'source_item_id',
                    'id_outlet',
                    'channel_account',
                    'author_name',
                    'event_at',
                    'severity',
                    'topics',
                    'summary_id',
                    'raw_text',
                    'risk_score',
                    'sla_minutes',
                    'due_at',
                    'meta',
                    'updated_at',
                ]
            );
        }

        return [
            'selected' => count($rows),
            'upserted' => count($payload),
        ];
    }

    public function ingestGuestCommentForms(int $limit = 2000): array
    {
        $selectGcf = [
            'id',
            'id_outlet',
            'guest_name',
            'guest_phone',
            'comment_text',
            'issue_severity',
            'issue_topics',
            'issue_summary_id',
            'marketing_source',
            'visit_date',
            'verified_at',
            'created_at',
        ];
        if (Schema::hasColumn('guest_comment_forms', 'issue_follow_up_target')) {
            $selectGcf[] = 'issue_follow_up_target';
            $selectGcf[] = 'issue_impact';
        }
        if (Schema::hasColumn('guest_comment_forms', 'guest_email')) {
            $selectGcf[] = 'guest_email';
        }

        $rows = DB::table('guest_comment_forms')
            ->select($selectGcf)
            ->where('status', 'verified')
            ->whereNotNull('comment_text')
            ->where('comment_text', '!=', '')
            ->orderByDesc('id')
            ->limit(max(1, min(10000, $limit)))
            ->get();

        $payload = [];
        foreach ($rows as $row) {
            $eventAt = $this->normalizeEventAt($row->verified_at ?? null, $row->created_at ?? null);
            $severity = $this->normalizeSeverity($row->issue_severity ?? null);
            $topics = $this->normalizeTopics($row->issue_topics ?? null);
            $risk = $this->riskScoreFromSeverity($severity);
            $sla = $this->slaMinutesFromSeverity($severity);

            $dueAt = null;
            if ($sla !== null) {
                $dueAt = Carbon::parse($eventAt)->addMinutes($sla)->format('Y-m-d H:i:s');
            }

            $meta = [
                'origin' => 'guest_comment_forms',
                'marketing_source' => $this->nullableTrim($row->marketing_source ?? null),
                'visit_date' => $this->nullableTrim($row->visit_date ?? null),
            ];
            if (Schema::hasColumn('guest_comment_forms', 'issue_follow_up_target')) {
                $fu = $this->nullableTrim($row->issue_follow_up_target ?? null);
                $impact = $this->normalizeImpactJson($row->issue_impact ?? null);
                if ($fu !== null) {
                    $meta['follow_up_target'] = $fu;
                }
                if ($impact !== []) {
                    $meta['impact'] = $impact;
                }
            }
            if (Schema::hasColumn('guest_comment_forms', 'guest_email')) {
                $em = $this->nullableTrim($row->guest_email ?? null);
                if ($em !== null) {
                    $meta['customer_email'] = $em;
                }
            }

            $payload[] = [
                'source_type' => 'guest_comment',
                'source_ref' => 'gcf:'.$row->id,
                'source_report_id' => null,
                'source_item_id' => (int) $row->id,
                'id_outlet' => $row->id_outlet !== null ? (int) $row->id_outlet : null,
                'channel_account' => null,
                'author_name' => $this->nullableTrim($row->guest_name ?? null),
                'customer_contact' => $this->nullableTrim($row->guest_phone ?? null),
                'event_at' => $eventAt,
                'severity' => $severity,
                'topics' => json_encode($topics, JSON_UNESCAPED_UNICODE),
                'summary_id' => $this->limitText($row->issue_summary_id ?? null, 500),
                'raw_text' => $row->comment_text !== null ? (string) $row->comment_text : null,
                'risk_score' => $risk,
                'sla_minutes' => $sla,
                'due_at' => $dueAt,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload !== []) {
            DB::table('feedback_cases')->upsert(
                $payload,
                ['source_ref'],
                [
                    'source_type',
                    'source_item_id',
                    'id_outlet',
                    'author_name',
                    'customer_contact',
                    'event_at',
                    'severity',
                    'topics',
                    'summary_id',
                    'raw_text',
                    'risk_score',
                    'sla_minutes',
                    'due_at',
                    'meta',
                    'updated_at',
                ]
            );
        }

        return [
            'selected' => count($rows),
            'upserted' => count($payload),
        ];
    }

    private function normalizeEventAt(mixed $primary, mixed $fallback): string
    {
        foreach ([$primary, $fallback] as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            try {
                return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                continue;
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function normalizeSeverity(mixed $severity): string
    {
        $s = strtolower(trim((string) ($severity ?? '')));
        $legacy = [
            'mild_negative' => 'minor',
            'negative' => 'major',
            'severe' => 'critical',
        ];
        if (isset($legacy[$s])) {
            $s = $legacy[$s];
        }

        $allowed = ['positive', 'neutral', 'minor', 'major', 'critical'];

        return in_array($s, $allowed, true) ? $s : 'neutral';
    }

    /**
     * @return array<int, string>
     */
    private function normalizeImpactJson(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return $this->normalizeImpactJson($decoded);
        }
        if (! is_array($raw)) {
            return [];
        }
        $labels = ['reputasi', 'finansial', 'operasional'];
        $out = [];
        foreach ($raw as $v) {
            $x = strtolower(trim((string) $v));
            if (in_array($x, $labels, true)) {
                $out[$x] = $x;
            }
        }

        return array_values($out);
    }

    private function normalizeTopics(mixed $topics): array
    {
        if (is_string($topics) && trim($topics) !== '') {
            $decoded = json_decode($topics, true);
            if (is_array($decoded)) {
                $topics = $decoded;
            }
        }

        if (! is_array($topics) || $topics === []) {
            return ['other'];
        }

        $normalized = array_values(array_filter(array_map(function ($topic) {
            return trim((string) $topic);
        }, $topics)));

        return $normalized !== [] ? $normalized : ['other'];
    }

    private function riskScoreFromSeverity(string $severity): int
    {
        return match ($severity) {
            'critical' => 95,
            'major' => 75,
            'minor' => 50,
            'neutral' => 20,
            default => 10,
        };
    }

    private function slaMinutesFromSeverity(string $severity): ?int
    {
        return match ($severity) {
            'critical' => 30,
            'major' => 120,
            'minor' => 1440,
            default => null,
        };
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function limitText(mixed $value, int $max): ?string
    {
        $s = $this->nullableTrim($value);
        if ($s === null) {
            return null;
        }

        return mb_substr($s, 0, $max);
    }
}
