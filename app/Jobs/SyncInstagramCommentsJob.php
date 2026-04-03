<?php

namespace App\Jobs;

use App\Services\ApifyInstagramScraperService;
use App\Services\InstagramCommentImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncInstagramCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $profileKeys  kosong = semua
     */
    public function __construct(public array $profileKeys = [])
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
            Log::info('SyncInstagramCommentsJob: tidak ada post untuk di-sync komentar.');

            return;
        }

        $batchSize = max(1, (int) config('instagram.comments.batch_size', 25));
        $chunks = $posts->chunk($batchSize);
        $totalSaved = 0;

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
                continue;
            }

            Log::info('SyncInstagramCommentsJob: batch Apify', ['count' => count($urls)]);
            $input = $apify->buildCommentsInput($urls);
            $datasetId = $apify->runAndWaitForDataset($input);
            $items = $apify->getAllDatasetItems($datasetId);
            $totalSaved += $commentImporter->upsertFromApifyCommentItems($items, $urlToId);
        }

        Log::info('SyncInstagramCommentsJob: selesai', ['posts' => $posts->count(), 'comments_upserted' => $totalSaved]);
    }
}
