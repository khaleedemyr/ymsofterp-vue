<?php

namespace App\Jobs;

use App\Services\AIAnalyticsService;
use App\Services\ApifyGoogleReviewsService;
use App\Services\GoogleReviewDeduper;
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

    public function __construct(public int $reportId)
    {
        $this->onQueue((string) config('google_review.process_queue', 'google-review-ai'));
    }

    public function handle(AIAnalyticsService $ai, ApifyGoogleReviewsService $apify): void
    {
        Log::info('ProcessGoogleReviewAiReportJob started', [
            'report_id' => $this->reportId,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'attempt' => method_exists($this, 'attempts') ? $this->attempts() : null,
        ]);

        $report = DB::table('google_review_ai_reports')->where('id', $this->reportId)->first();
        if (! $report) {
            Log::warning('ProcessGoogleReviewAiReportJob report not found', [
                'report_id' => $this->reportId,
            ]);
            return;
        }

        DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
            'status' => 'processing',
            'progress_phase' => 'starting',
            'progress_total' => 0,
            'progress_done' => 0,
            'updated_at' => now(),
        ]);
        $this->pushLog('Job dimulai (klasifikasi AI).');

        try {
            $reviews = [];
            if ($report->source === 'apify_dataset') {
                if (empty($report->dataset_id)) {
                    throw new \RuntimeException('dataset_id kosong.');
                }
                $this->pushLog('Mengunduh review dari dataset Apify…');
                $sourceMeta = [];
                if (! empty($report->source_payload)) {
                    $decodedMeta = json_decode((string) $report->source_payload, true);
                    $sourceMeta = is_array($decodedMeta) ? $decodedMeta : [];
                }
                $dateFrom = isset($sourceMeta['date_from']) ? (string) $sourceMeta['date_from'] : null;
                $dateTo = isset($sourceMeta['date_to']) ? (string) $sourceMeta['date_to'] : null;
                if ($dateFrom || $dateTo) {
                    $this->pushLog('Filter tanggal aktif: '.($dateFrom ?: '-').' s/d '.($dateTo ?: '-').'.');
                }
                $expectedTotal = max(1, $apify->getDatasetItemCount($report->dataset_id));
                DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                    'progress_phase' => 'fetching',
                    'progress_total' => $expectedTotal,
                    'progress_done' => 0,
                    'updated_at' => now(),
                ]);

                $lastLogged = 0;
                $reviews = $apify->getAllReviewsFromDataset($report->dataset_id, function ($loaded, $total) use (&$lastLogged) {
                    DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                        'progress_phase' => 'fetching',
                        'progress_total' => max(1, (int) $total),
                        'progress_done' => (int) $loaded,
                        'updated_at' => now(),
                    ]);
                    if ($loaded === $total || $loaded - $lastLogged >= 200) {
                        $this->pushLog("Unduh dataset: {$loaded}/{$total} baris.");
                        $lastLogged = $loaded;
                    }
                }, $dateFrom, $dateTo);
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

            $rawCount = count($reviews);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'raw_review_count' => $rawCount,
                'updated_at' => now(),
            ]);
            $this->pushLog("Review mentah: {$rawCount} baris.");

            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'progress_phase' => 'deduping',
                'updated_at' => now(),
            ]);
            $deduped = GoogleReviewDeduper::dedupe($reviews);
            $reviews = $deduped['reviews'];
            $removed = $deduped['removed'];
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'dedupe_removed_count' => $removed,
                'updated_at' => now(),
            ]);
            if ($removed > 0) {
                $this->pushLog("Deduplikasi: {$removed} duplikat diabaikan ({$rawCount} → ".count($reviews).' unik).');
            } else {
                $this->pushLog('Deduplikasi: tidak ada duplikat.');
            }

            if (count($reviews) === 0) {
                throw new \RuntimeException('Semua review terdeteksi duplikat; tidak ada yang diklasifikasi.');
            }

            $toClassify = count($reviews);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'progress_phase' => 'classifying',
                'progress_total' => $toClassify,
                'progress_done' => 0,
                'updated_at' => now(),
            ]);
            $this->pushLog("Klasifikasi AI dimulai ({$toClassify} review, per batch).");

            $classified = $ai->classifyGoogleReviewsInChunks($reviews, 35, function ($done, $total) {
                DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                    'progress_phase' => 'classifying',
                    'progress_total' => max(1, (int) $total),
                    'progress_done' => (int) $done,
                    'updated_at' => now(),
                ]);
            });

            $this->pushLog('Menyimpan hasil ke database…');
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'progress_phase' => 'saving',
                'progress_done' => $toClassify,
                'progress_total' => max(1, $toClassify),
                'updated_at' => now(),
            ]);

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

            $finalCount = count($classified);
            DB::table('google_review_ai_reports')->where('id', $this->reportId)->update([
                'status' => 'completed',
                'review_count' => $finalCount,
                'source_payload' => null,
                'error_message' => null,
                'progress_phase' => 'completed',
                'progress_total' => max(1, $finalCount),
                'progress_done' => $finalCount,
                'updated_at' => now(),
            ]);
            $this->pushLog("Selesai. {$finalCount} review tersimpan.");
            Log::info('ProcessGoogleReviewAiReportJob completed', [
                'report_id' => $this->reportId,
                'classified_count' => $finalCount,
                'source' => $report->source,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessGoogleReviewAiReportJob failed', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 4000),
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
            $decoded = json_decode($row->progress_log, true);
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

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessGoogleReviewAiReportJob marked failed()', [
            'report_id' => $this->reportId,
            'error' => $e->getMessage(),
            'trace' => mb_substr($e->getTraceAsString(), 0, 4000),
        ]);
        DB::table('google_review_ai_reports')->where('id', $this->reportId)->where('status', '!=', 'completed')->update([
            'status' => 'failed',
            'error_message' => mb_substr($e->getMessage(), 0, 10000),
            'progress_phase' => 'failed',
            'updated_at' => now(),
        ]);
    }
}
