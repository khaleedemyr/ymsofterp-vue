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

            $rows = $q->orderBy('instagram_comments.id')->get();
            $reviews = $rows->map(function ($r) {
                return [
                    'author' => (string) ($r->username ?? ''),
                    'rating' => '',
                    'date' => ! empty($r->commented_at) ? (string) $r->commented_at : '',
                    'text' => (string) ($r->text ?? ''),
                    'profile_photo' => '',
                    'review_id' => (string) ($r->comment_id ?? ''),
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
                    'rating' => '',
                    'review_date' => mb_substr((string) ($row['date'] ?? ''), 0, 255),
                    'text' => $row['text'] ?? null,
                    'profile_photo' => '',
                    'severity' => mb_substr((string) ($ac['severity'] ?? ''), 0, 32),
                    'topics' => json_encode($ac['topics'] ?? []),
                    'summary_id' => mb_substr((string) ($ac['summary_id'] ?? ''), 0, 500),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
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

