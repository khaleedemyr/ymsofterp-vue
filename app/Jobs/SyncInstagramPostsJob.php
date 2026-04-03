<?php

namespace App\Jobs;

use App\Services\ApifyInstagramScraperService;
use App\Services\InstagramPostImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncInstagramPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $profileKeys
     */
    public function __construct(public array $profileKeys = [], public ?string $operationId = null)
    {
        $this->onQueue((string) config('instagram.process_queue', 'instagram-scraper'));
    }

    public function handle(ApifyInstagramScraperService $apify, InstagramPostImporter $importer): void
    {
        $this->setProgress('processing', 'Menyiapkan URL profil...', 2, 0);

        $profiles = config('instagram.profiles', []);
        $keys = $this->profileKeys;
        if ($keys === []) {
            $keys = array_keys($profiles);
        }

        $urls = [];
        foreach ($keys as $k) {
            if (! isset($profiles[$k]['url'])) {
                continue;
            }
            $u = trim((string) $profiles[$k]['url']);
            if ($u !== '' && $u !== 'https://www.instagram.com/' && $u !== 'https://www.instagram.com') {
                $urls[] = $u;
            }
        }

        if ($urls === []) {
            throw new \RuntimeException(
                'Tidak ada URL profil Instagram yang valid untuk key terpilih. Isi URL di config/instagram.php (bukan https://www.instagram.com/ saja).'
            );
        }

        $this->setProgress('processing', 'Menjalankan Apify untuk posting...', 2, 1);
        Log::info('SyncInstagramPostsJob: mulai Apify posts', ['urls' => $urls]);
        $input = $apify->buildPostsInput($urls);
        $datasetId = $apify->runAndWaitForDataset($input);
        $items = $apify->getAllDatasetItems($datasetId);
        $saved = $importer->upsertFromApifyPostItems($items);

        $this->setProgress('completed', "Selesai: {$saved} posting tersimpan.", 2, 2, [
            'dataset_id' => $datasetId,
            'rows' => count($items),
            'saved' => $saved,
        ]);
        Log::info('SyncInstagramPostsJob: selesai', ['dataset' => $datasetId, 'rows' => count($items), 'saved' => $saved]);
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
}
