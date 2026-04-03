<?php

namespace App\Jobs;

use App\Services\ApifyInstagramScraperService;
use App\Services\InstagramCommentImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncInstagramCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $profileKeys
     */
    public function __construct(
        public array $profileKeys = [],
        public ?string $operationId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null
    )
    {
        $this->onQueue((string) config('instagram.process_queue', 'instagram-scraper'));
    }

    public function handle(ApifyInstagramScraperService $apify, InstagramCommentImporter $commentImporter): void
    {
        $q = DB::table('instagram_posts')
            ->select(['id', 'post_url', 'short_code'])
            ->where('post_url', 'like', '%/p/%');

        if ($this->profileKeys !== []) {
            $q->whereIn('profile_key', $this->profileKeys);
        }
        if ((bool) config('instagram.comments.only_if_comments_count_positive', true)) {
            $q->where('comments_count', '>', 0);
        }

        $posts = $q->orderBy('id')->get();
        if ($posts->isEmpty()) {
            $this->setProgress('completed', 'Tidak ada post yang memenuhi syarat komentar.', 1, 1);
            Log::info('SyncInstagramCommentsJob: tidak ada post untuk di-sync komentar.');
            return;
        }

        $batchSize = max(1, (int) config('instagram.comments.batch_size', 25));
        $chunks = $posts->chunk($batchSize);
        $totalBatches = max(1, $chunks->count());
        $processedBatches = 0;
        $totalSaved = 0;

        $this->setProgress('processing', 'Memulai sinkron komentar...', $totalBatches, 0);

        $failedBatches = 0;
        foreach ($chunks as $chunk) {
            $urls = [];
            $urlToId = [];
            foreach ($chunk as $row) {
                $url = (string) $row->post_url;
                $urls[] = $url;
                $key = $commentImporter->postUrlKey($url);
                if ($key !== '') {
                    $urlToId[$key] = (int) $row->id;
                }
            }
            if ($urls === []) {
                $processedBatches++;
                $this->setProgress('processing', 'Melewati batch kosong...', $totalBatches, $processedBatches);
                continue;
            }

            try {
                Log::info('SyncInstagramCommentsJob: batch Apify', ['count' => count($urls)]);
                $input = $apify->buildCommentsInput($urls);
                $datasetId = $apify->runAndWaitForDataset($input);
                $items = $apify->getAllDatasetItems($datasetId);
                $items = $this->filterItemsByDateRange($items);
                $totalSaved += $commentImporter->upsertFromApifyCommentItems($items, $urlToId);
            } catch (\Throwable $e) {
                $failedBatches++;
                Log::warning('SyncInstagramCommentsJob: batch gagal, lanjut batch berikutnya', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($urls),
                ]);
                $processedBatches++;
                $this->setProgress(
                    'processing',
                    "Batch {$processedBatches}/{$totalBatches} gagal ({$failedBatches} total gagal). Melanjutkan batch berikutnya...",
                    $totalBatches,
                    $processedBatches
                );
                continue;
            }

            $processedBatches++;
            $this->setProgress(
                'processing',
                "Batch {$processedBatches}/{$totalBatches} selesai. Total komentar tersimpan: {$totalSaved}",
                $totalBatches,
                $processedBatches,
                ['last_dataset_id' => $datasetId]
            );
        }

        $finalStatus = $failedBatches > 0 ? 'completed_with_errors' : 'completed';
        $finalMessage = $failedBatches > 0
            ? "Selesai dengan kendala: {$totalSaved} komentar tersimpan, {$failedBatches} batch gagal."
            : "Selesai: {$totalSaved} komentar tersimpan.";
        $this->setProgress($finalStatus, $finalMessage, $totalBatches, $totalBatches, [
            'posts' => $posts->count(),
            'comments_upserted' => $totalSaved,
            'failed_batches' => $failedBatches,
        ]);
        Log::info('SyncInstagramCommentsJob: selesai', [
            'posts' => $posts->count(),
            'comments_upserted' => $totalSaved,
            'failed_batches' => $failedBatches,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->setProgress('failed', $e->getMessage(), 1, 1);
    }

    protected function setProgress(string $status, string $message, int $total, int $done, array $extra = []): void
    {
        if (! $this->operationId) {
            return;
        }
        Cache::put('instagram:sync:'.$this->operationId, array_merge([
            'status' => $status,
            'message' => $message,
            'progress_total' => max(1, $total),
            'progress_done' => max(0, min($done, max(1, $total))),
            'updated_at' => now()->toDateTimeString(),
        ], $extra), now()->addHours(6));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    protected function filterItemsByDateRange(array $items): array
    {
        [$fromTs, $toTs] = $this->buildDateRangeBoundaries();
        if ($fromTs === null && $toTs === null) {
            return $items;
        }

        $filtered = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $time = $this->extractCommentTimestamp($item);
            if ($time === null) {
                continue;
            }
            if ($fromTs !== null && $time < $fromTs) {
                continue;
            }
            if ($toTs !== null && $time > $toTs) {
                continue;
            }
            $filtered[] = $item;
        }

        return $filtered;
    }

    protected function extractCommentTimestamp(array $item): ?int
    {
        $candidates = [
            $item['timestamp'] ?? null,
            $item['createdAt'] ?? null,
        ];
        if (isset($item['ownerComment']) && is_array($item['ownerComment'])) {
            $candidates[] = $item['ownerComment']['timestamp'] ?? null;
            $candidates[] = $item['ownerComment']['createdAt'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $ts = (int) $candidate;
                if ($ts > 0) {
                    return $ts;
                }
            }
            if (is_string($candidate) && trim($candidate) !== '') {
                $parsed = strtotime($candidate);
                if ($parsed !== false && $parsed > 0) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    protected function buildDateRangeBoundaries(): array
    {
        $fromTs = null;
        $toTs = null;
        if ($this->dateFrom && trim($this->dateFrom) !== '') {
            $fromTs = strtotime(trim($this->dateFrom).' 00:00:00 UTC') ?: null;
        }
        if ($this->dateTo && trim($this->dateTo) !== '') {
            $toTs = strtotime(trim($this->dateTo).' 23:59:59 UTC') ?: null;
        }

        return [$fromTs, $toTs];
    }
}
