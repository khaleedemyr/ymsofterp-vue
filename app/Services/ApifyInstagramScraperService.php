<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApifyInstagramScraperService
{
    protected string $token;

    protected string $actorIdForApi;

    public function __construct()
    {
        $this->token = (string) env('APIFY_TOKEN', '');
        $actorId = (string) config('instagram.actor_id', 'apify/instagram-scraper');
        $this->actorIdForApi = str_replace('/', '~', $actorId);
    }

    public function assertToken(): void
    {
        if (trim($this->token) === '') {
            throw new \RuntimeException('APIFY_TOKEN belum diset di .env');
        }
    }

    /**
     * @param  array<int, string>  $profileUrls
     * @return array<string, mixed>
     */
    public function buildPostsInput(array $profileUrls): array
    {
        $profileUrls = array_values(array_filter(array_map('trim', $profileUrls)));

        return [
            'addParentData' => false,
            'directUrls' => $profileUrls,
            'onlyPostsNewerThan' => (string) config('instagram.posts.only_newer_than', '1 year'),
            'resultsLimit' => (int) config('instagram.posts.results_limit', 200),
            'resultsType' => 'posts',
            'searchLimit' => 1,
            'searchType' => 'hashtag',
        ];
    }

    /**
     * @param  array<int, string>  $postUrls  URL post /p/.../
     * @return array<string, mixed>
     */
    public function buildCommentsInput(array $postUrls): array
    {
        $postUrls = array_values(array_filter(array_map('trim', $postUrls)));

        return [
            'addParentData' => false,
            'directUrls' => $postUrls,
            'resultsType' => 'comments',
            'resultsLimit' => (int) config('instagram.comments.results_limit_per_post', 50),
            'searchLimit' => 1,
            'searchType' => 'hashtag',
        ];
    }

    /**
     * Jalankan actor, poll sampai selesai, kembalikan defaultDatasetId.
     *
     * @param  array<string, mixed>  $input
     */
    public function runAndWaitForDataset(array $input): string
    {
        $this->assertToken();
        $run = $this->startRun($input);
        $runId = (string) ($run['id'] ?? '');
        if ($runId === '') {
            throw new \RuntimeException('Apify tidak mengembalikan run id');
        }
        $finished = $this->waitForRunSuccess($runId);
        $datasetId = (string) ($finished['defaultDatasetId'] ?? '');
        if ($datasetId === '') {
            throw new \RuntimeException('Apify run selesai tanpa defaultDatasetId');
        }

        return $datasetId;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function startRun(array $input): array
    {
        $this->assertToken();
        $response = $this->httpClient()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(
                "https://api.apify.com/v2/acts/{$this->actorIdForApi}/runs?token={$this->token}",
                $input
            );

        $data = $response->json();
        if (! $response->successful()) {
            $message = $data['error']['message'] ?? ($data['error'] ?? 'Unknown Apify error');

            throw new \RuntimeException("Apify start run gagal ({$response->status()}): {$message}");
        }

        return $data['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function waitForRunSuccess(string $runId): array
    {
        $this->assertToken();
        $max = max(60, (int) config('instagram.run_max_wait_seconds', 900));
        $deadline = time() + $max;
        $interval = 15;

        while (time() < $deadline) {
            $run = $this->getRun($runId);
            $status = (string) ($run['status'] ?? '');
            if ($status === 'SUCCEEDED') {
                return $run;
            }
            if (in_array($status, ['FAILED', 'ABORTED', 'TIMED-OUT'], true)) {
                $msg = (string) ($run['statusMessage'] ?? $status);

                throw new \RuntimeException("Apify run {$status}: {$msg}");
            }
            sleep($interval);
        }

        throw new \RuntimeException("Apify run timeout setelah {$max}s (run id: {$runId})");
    }

    /**
     * @return array<string, mixed>
     */
    public function getRun(string $runId): array
    {
        $response = $this->httpClient()
            ->get("https://api.apify.com/v2/actor-runs/{$runId}", [
                'token' => $this->token,
            ]);
        $data = $response->json();
        if (! $response->successful()) {
            throw new \RuntimeException("Gagal baca status run Apify ({$response->status()})");
        }

        return $data['data'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllDatasetItems(string $datasetId): array
    {
        $info = $this->getDatasetInfo($datasetId);
        $total = (int) ($info['itemCount'] ?? 0);
        $all = [];
        $limit = 200;
        for ($offset = 0; $offset < $total; $offset += $limit) {
            $chunk = $this->getDatasetItems($datasetId, $offset, $limit);
            foreach ($chunk as $row) {
                $all[] = $row;
            }
        }

        return $all;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDatasetInfo(string $datasetId): array
    {
        $response = $this->httpClient()
            ->get("https://api.apify.com/v2/datasets/{$datasetId}", [
                'token' => $this->token,
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException("Gagal info dataset Apify ({$response->status()})");
        }
        $data = $response->json();

        return $data['data'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDatasetItems(string $datasetId, int $offset = 0, int $limit = 200): array
    {
        $response = $this->httpClient()
            ->timeout(180)
            ->get("https://api.apify.com/v2/datasets/{$datasetId}/items", [
                'token' => $this->token,
                'clean' => 1,
                'format' => 'json',
                'offset' => max(0, $offset),
                'limit' => min(200, max(1, $limit)),
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException("Gagal ambil item dataset ({$response->status()})");
        }
        $items = $response->json();

        return is_array($items) ? $items : [];
    }

    protected function httpClient()
    {
        return Http::withOptions([
            'verify' => $this->resolveVerifyOption(),
        ]);
    }

    protected function resolveVerifyOption()
    {
        $curlCaInfo = ini_get('curl.cainfo');
        $opensslCaFile = ini_get('openssl.cafile');
        if ($this->isReadableFile($curlCaInfo)) {
            return $curlCaInfo;
        }
        if ($this->isReadableFile($opensslCaFile)) {
            return $opensslCaFile;
        }

        \Log::warning('ApifyInstagramScraperService: CA file not found, disabling SSL verify for this request.', [
            'curl.cainfo' => $curlCaInfo,
            'openssl.cafile' => $opensslCaFile,
        ]);

        return false;
    }

    protected function isReadableFile($path): bool
    {
        return is_string($path) && $path !== '' && file_exists($path) && is_readable($path);
    }
}
