<?php

namespace App\Jobs;

use App\Services\AIAnalyticsService;
use App\Services\ApifyGoogleReviewsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessGoogleReviewAiReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $reportId) {}

    public function handle(AIAnalyticsService $ai, ApifyGoogleReviewsService $apify): void
    {
        $report = DB::table('google_review_ai_reports')->where('id', $this->reportId)->first();
        if (! $report) {
            return;
        }

        DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);

        try {
            $reviews = [];
            if ($report->source === 'apify_dataset') {
                if (empty($report->dataset_id)) {
                    throw new \RuntimeException('dataset_id kosong.');
                }
                $reviews = $apify->getAllReviewsFromDataset($report->dataset_id);
            } else {
                $raw = $report->source_payload;
                if (empty($raw)) {
                    throw new \RuntimeException('Payload review kosong.');
                }
                $reviews = json_decode($raw, true);
                if (! is_array($reviews)) {
                    throw new \RuntimeException('Payload review tidak valid.');
                }
            }

            $reviews = array_values($reviews);
            if (count($reviews) === 0) {
                throw new \RuntimeException('Tidak ada review untuk diklasifikasi.');
            }

            $classified = $ai->classifyGoogleReviewsInChunks($reviews, 35);

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
                    'profile_photo' => mb_substr((string) ($row['profile_photo'] ?? ''), 0, 1024),
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

            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'status' => 'completed',
                'review_count' => count($classified),
                'source_payload' => null,
                'error_message' => null,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessGoogleReviewAiReportJob failed', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 10000),
                'updated_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        DB::table('google_review_ai_reports')->where('id', $this->reportId)->where('status', '!=', 'completed')->update([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 10000),
            'updated_at' => now(),
        ]);
    }
}
