<?php

namespace App\Jobs;

use App\Services\AIAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessInstagramCommentAiReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public int $reportId)
    {
        $this->onQueue((string) config('google_review.process_queue', 'google-review-ai'));
    }

    public function handle(AIAnalyticsService $ai): void
    {
        $report = DB::table('google_review_ai_reports')->where('id', $this->reportId)->first();
        if (! $report) {
            return;
        }

        DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
            'status' => 'processing',
            'progress_phase' => 'starting',
            'progress_total' => 0,
            'progress_done' => 0,
            'updated_at' => now(),
        ]);
        $this->pushLog('Job dimulai (klasifikasi AI komentar Instagram).');

        try {
            $hasSourceItemId = Schema::hasColumn('google_review_ai_items', 'source_item_id');
            $hasSourceAccount = Schema::hasColumn('google_review_ai_items', 'source_account');
            $hasSourcePostUrl = Schema::hasColumn('google_review_ai_items', 'source_post_url');
            $hasSourcePostShortcode = Schema::hasColumn('google_review_ai_items', 'source_post_shortcode');
            $meta = [];
            if (! empty($report->source_payload)) {
                $decoded = json_decode((string) $report->source_payload, true);
                $meta = is_array($decoded) ? $decoded : [];
            }

            $dateFrom = isset($meta['date_from']) ? (string) $meta['date_from'] : null;
            $dateTo = isset($meta['date_to']) ? (string) $meta['date_to'] : null;
            $profileKeys = array_values(array_filter((array) ($meta['profile_keys'] ?? [])));

            $q = DB::table('instagram_comments')
                ->join('instagram_posts', 'instagram_posts.id', '=', 'instagram_comments.instagram_post_id')
                ->select([
                    'instagram_comments.id as comment_id',
                    'instagram_comments.username',
                    'instagram_comments.text',
                    'instagram_comments.commented_at',
                    'instagram_comments.raw_json',
                    'instagram_posts.profile_key',
                    'instagram_posts.post_url',
                    'instagram_posts.short_code',
                ])
                ->whereRaw("TRIM(COALESCE(instagram_comments.text, '')) <> ''");

            if ($profileKeys !== []) {
                $q->whereIn('instagram_posts.profile_key', $profileKeys);
            }
            if ($dateFrom) {
                $q->whereDate('instagram_comments.commented_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('instagram_comments.commented_at', '<=', $dateTo);
            }
            if ($hasSourceItemId) {
                $alreadyClassifiedIds = DB::table('google_review_ai_items as i')
                    ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
                    ->where('r.source', 'instagram_comments_db')
                    ->where('r.status', 'completed')
                    ->whereNotNull('i.source_item_id')
                    ->pluck('i.source_item_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();
                if ($alreadyClassifiedIds !== []) {
                    $q->whereNotIn('instagram_comments.id', $alreadyClassifiedIds);
                }
            } else {
                $this->pushLog('Kolom source_item_id belum ada; skip "already-classified" tidak aktif.');
            }

            $rows = $q->orderBy('instagram_comments.id')->get();
            $reviews = $rows->map(function ($r) {
                $date = ! empty($r->commented_at) ? (string) $r->commented_at : '';
                if ($date === '' && ! empty($r->raw_json)) {
                    try {
                        $raw = json_decode((string) $r->raw_json, true);
                    } catch (\Throwable) {
                        $raw = null;
                    }
                    if (is_array($raw)) {
                        $candidate = $raw['timestamp'] ?? $raw['createdAt'] ?? $raw['created_at'] ?? null;
                        if ($candidate !== null && $candidate !== '') {
                            if (is_numeric($candidate)) {
                                $ts = (int) $candidate;
                                if ($ts > 9999999999) {
                                    $ts = (int) floor($ts / 1000);
                                }
                                if ($ts > 0) {
                                    $date = date('Y-m-d H:i:s', $ts);
                                }
                            } else {
                                $parsed = strtotime((string) $candidate);
                                if ($parsed !== false && $parsed > 0) {
                                    $date = date('Y-m-d H:i:s', $parsed);
                                }
                            }
                        }
                    }
                }
                return [
                    'author' => (string) ($r->username ?? ''),
                    'rating' => (string) ($r->profile_key ?? ''),
                    'date' => $date,
                    'text' => (string) ($r->text ?? ''),
                    'profile_photo' => '',
                    'review_id' => (string) ($r->comment_id ?? ''),
                    '_source_item_id' => (int) ($r->comment_id ?? 0),
                    '_source_account' => (string) ($r->profile_key ?? ''),
                    '_source_post_url' => (string) ($r->post_url ?? ''),
                    '_source_post_shortcode' => (string) ($r->short_code ?? ''),
                ];
            })->values()->all();

            if (count($reviews) === 0) {
                throw new \RuntimeException('Tidak ada komentar Instagram untuk diklasifikasi.');
            }

            $total = count($reviews);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'raw_review_count' => $total,
                'dedupe_removed_count' => 0,
                'progress_phase' => 'classifying',
                'progress_total' => $total,
                'progress_done' => 0,
                'updated_at' => now(),
            ]);
            $this->pushLog("Klasifikasi AI dimulai ({$total} komentar).");

            $classified = $ai->classifyGoogleReviewsInChunks($reviews, 35, function ($done, $all) {
                DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                    'progress_phase' => 'classifying',
                    'progress_total' => max(1, (int) $all),
                    'progress_done' => (int) $done,
                    'updated_at' => now(),
                ]);
            });

            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'progress_phase' => 'saving',
                'progress_total' => max(1, $total),
                'progress_done' => $total,
                'updated_at' => now(),
            ]);
            $this->pushLog('Menyimpan hasil ke database…');

            DB::table('google_review_ai_items')->where('report_id', $this->reportId)->delete();
            $batch = [];
            $now = now();
            foreach ($classified as $idx => $row) {
                $row = is_array($row) ? $row : (array) $row;
                $ac = $row['ai_classification'] ?? [];
                $batch[] = [
                    'report_id' => $this->reportId,
                    'sort_order' => $idx,
                    'author' => mb_substr((string) ($row['author'] ?? ''), 0, 255),
                    'rating' => mb_substr((string) ($row['rating'] ?? ''), 0, 32),
                    'review_date' => mb_substr((string) ($row['date'] ?? ''), 0, 255),
                    'text' => $row['text'] ?? null,
                    'profile_photo' => '',
                    'severity' => mb_substr((string) ($ac['severity'] ?? ''), 0, 32),
                    'topics' => json_encode($ac['topics'] ?? []),
                    'summary_id' => mb_substr((string) ($ac['summary_id'] ?? ''), 0, 500),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasSourceItemId) {
                    $batch[count($batch) - 1]['source_item_id'] = (int) ($row['_source_item_id'] ?? 0);
                }
                if ($hasSourceAccount) {
                    $batch[count($batch) - 1]['source_account'] = mb_substr((string) ($row['_source_account'] ?? ''), 0, 64);
                }
                if ($hasSourcePostUrl) {
                    $batch[count($batch) - 1]['source_post_url'] = mb_substr((string) ($row['_source_post_url'] ?? ''), 0, 512);
                }
                if ($hasSourcePostShortcode) {
                    $batch[count($batch) - 1]['source_post_shortcode'] = mb_substr((string) ($row['_source_post_shortcode'] ?? ''), 0, 32);
                }
                if (count($batch) >= 150) {
                    DB::table('google_review_ai_items')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                DB::table('google_review_ai_items')->insert($batch);
            }

            $final = count($classified);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'status' => 'completed',
                'review_count' => $final,
                'source_payload' => null,
                'error_message' => null,
                'progress_phase' => 'completed',
                'progress_total' => max(1, $final),
                'progress_done' => $final,
                'updated_at' => now(),
            ]);
            $this->pushLog("Selesai. {$final} komentar tersimpan.");
        } catch (\Throwable $e) {
            Log::error('ProcessInstagramCommentAiReportJob failed', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);
            $this->pushLog('Gagal: '.$e->getMessage());
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 10000),
                'progress_phase' => 'failed',
                'updated_at' => now(),
            ]);
        }
    }

    private function pushLog(string $message): void
    {
        $row = DB::table('google_review_ai_reports')->where('id', $this->reportId)->first();
        $log = [];
        if ($row && ! empty($row->progress_log)) {
            $decoded = json_decode((string) $row->progress_log, true);
            $log = is_array($decoded) ? $decoded : [];
        }
        $log[] = [
            't' => now()->format('Y-m-d H:i:s'),
            'm' => mb_substr($message, 0, 800),
        ];
        $log = array_slice($log, -100);

        DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
            'progress_log' => json_encode($log, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
}

