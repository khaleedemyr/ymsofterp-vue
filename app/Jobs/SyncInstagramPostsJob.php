<?php

namespace App\Jobs;

use App\Services\ApifyInstagramScraperService;
use App\Services\InstagramPostImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInstagramPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $profileKeys  kosong = semua dari config
     */
    public function __construct(public array $profileKeys = [])
    {
        $this->onQueue((string) config('instagram.process_queue', 'instagram-scraper'));
    }

    public function handle(ApifyInstagramScraperService $apify, InstagramPostImporter $importer): void
    {
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

        Log::info('SyncInstagramPostsJob: mulai Apify posts', ['urls' => $urls]);
        $input = $apify->buildPostsInput($urls);
        $datasetId = $apify->runAndWaitForDataset($input);
        $items = $apify->getAllDatasetItems($datasetId);
        $n = $importer->upsertFromApifyPostItems($items);
        Log::info('SyncInstagramPostsJob: selesai', ['dataset' => $datasetId, 'rows' => count($items), 'saved' => $n]);
    }
}
