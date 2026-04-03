<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApifyGoogleReviewsService
{
    protected string $token;
    protected string $actorId;
    protected string $actorIdForApi;

    public function __construct()
    {
        $this->token = (string) env('APIFY_TOKEN', '');
        $this->actorId = (string) env('APIFY_GOOGLE_REVIEWS_ACTOR', 'compass/google-maps-reviews-scraper');
        // Apify API expects actor ID in form "username~actorName" (not "username/actorName")
        $this->actorIdForApi = str_replace('/', '~', $this->actorId);
    }

    public function fetchReviewsByPlaceId(string $placeId, int $maxReviews = 200, string $sort = 'newest'): array
    {
        if (trim($this->token) === '') {
            throw new \Exception('APIFY_TOKEN belum diset di .env');
        }

        $url = "https://www.google.com/maps/place/?q=place_id:{$placeId}";
        $items = $this->runActorAndGetDatasetItems([
            'startUrls' => [
                ['url' => $url],
            ],
            'maxReviews' => $maxReviews,
            'reviewsSort' => $sort, // newest | mostRelevant
        ]);

        $reviews = collect($items)->map(function ($item) {
            $time = 0;
            if (!empty($item['publishedAtDate'])) {
                $time = strtotime($item['publishedAtDate']) ?: 0;
            }

            return [
                'author' => (string)($item['name'] ?? ''),
                'rating' => (string)($item['stars'] ?? ''),
                'date' => (string)($item['publishAt'] ?? ($item['publishedAtDate'] ?? '')),
                'text' => (string)($item['text'] ?? ''),
                'profile_photo' => (string)($item['reviewerPhotoUrl'] ?? ''),
                'time' => (int)$time,
            ];
        })->values()->all();

        $placeName = (string)($items[0]['title'] ?? 'Apify Google Reviews');
        $address = (string)($items[0]['address'] ?? '');
        $rating = $items[0]['totalScore'] ?? '';
        $location = $items[0]['location'] ?? null;

        return [
            'place' => [
                'name' => $placeName,
                'address' => $address,
                'rating' => $rating,
                'location' => $location,
            ],
            'reviews' => $reviews,
        ];
    }

    public function startScrapeByPlaceId(string $placeId, int $maxReviews = 200, string $sort = 'newest'): array
    {
        if (trim($this->token) === '') {
            throw new \Exception('APIFY_TOKEN belum diset di .env');
        }

        $url = "https://www.google.com/maps/place/?q=place_id:{$placeId}";
        $run = $this->runActor([
            'startUrls' => [
                ['url' => $url],
            ],
            'maxReviews' => $maxReviews,
            'reviewsSort' => $sort,
        ]);

        $datasetId = $run['defaultDatasetId'] ?? null;
        if (!$datasetId) {
            throw new \Exception('Apify run tidak mengembalikan defaultDatasetId');
        }

        $datasetInfo = $this->getDatasetInfo($datasetId);
        $place = $this->getPlaceFromDataset($datasetId);

        return [
            'datasetId' => $datasetId,
            'itemCount' => (int)($datasetInfo['itemCount'] ?? 0),
            'place' => $place,
        ];
    }

    /**
     * Ambil semua item review dari dataset Apify (loop offset, maks 200 per request).
     *
     * @return array<int, array{author: string, rating: string, date: string, text: string, profile_photo: string, time: int}>
     */
    public function getAllReviewsFromDataset(string $datasetId): array
    {
        $datasetInfo = $this->getDatasetInfo($datasetId);
        $total = (int) ($datasetInfo['itemCount'] ?? 0);
        $all = [];
        $limit = 200;
        for ($offset = 0; $offset < $total; $offset += $limit) {
            $items = $this->getDatasetItems($datasetId, $offset, $limit);
            foreach ($items as $item) {
                $time = 0;
                if (! empty($item['publishedAtDate'])) {
                    $time = strtotime($item['publishedAtDate']) ?: 0;
                }
                $all[] = [
                    'author' => (string) ($item['name'] ?? ''),
                    'rating' => (string) ($item['stars'] ?? ''),
                    'date' => (string) ($item['publishAt'] ?? ($item['publishedAtDate'] ?? '')),
                    'text' => (string) ($item['text'] ?? ''),
                    'profile_photo' => (string) ($item['reviewerPhotoUrl'] ?? ''),
                    'time' => (int) $time,
                ];
            }
        }

        return $all;
    }

    public function getReviewsPageFromDataset(string $datasetId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $datasetInfo = $this->getDatasetInfo($datasetId);
        $items = $this->getDatasetItems($datasetId, $offset, $perPage);

        $reviews = collect($items)->map(function ($item) {
            $time = 0;
            if (!empty($item['publishedAtDate'])) {
                $time = strtotime($item['publishedAtDate']) ?: 0;
            }

            return [
                'author' => (string)($item['name'] ?? ''),
                'rating' => (string)($item['stars'] ?? ''),
                'date' => (string)($item['publishAt'] ?? ($item['publishedAtDate'] ?? '')),
                'text' => (string)($item['text'] ?? ''),
                'profile_photo' => (string)($item['reviewerPhotoUrl'] ?? ''),
                'time' => (int)$time,
            ];
        })->values()->all();

        $total = (int)($datasetInfo['itemCount'] ?? 0);
        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        return [
            'reviews' => $reviews,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ];
    }

    public function exportDatasetReviewsToCsv(string $datasetId, callable $writer): void
    {
        $datasetInfo = $this->getDatasetInfo($datasetId);
        $total = (int)($datasetInfo['itemCount'] ?? 0);

        $writer(['Author', 'Rating', 'Date', 'Text', 'Profile Photo']);

        $limit = 200;
        for ($offset = 0; $offset < $total; $offset += $limit) {
            $items = $this->getDatasetItems($datasetId, $offset, $limit);
            foreach ($items as $item) {
                $writer([
                    (string)($item['name'] ?? ''),
                    (string)($item['stars'] ?? ''),
                    (string)($item['publishAt'] ?? ($item['publishedAtDate'] ?? '')),
                    (string)($item['text'] ?? ''),
                    (string)($item['reviewerPhotoUrl'] ?? ''),
                ]);
            }
        }
    }

    protected function runActorAndGetDatasetItems(array $input): array
    {
        $run = $this->runActor($input);
        $datasetId = $run['defaultDatasetId'] ?? null;
        if (!$datasetId) {
            throw new \Exception('Apify run tidak mengembalikan defaultDatasetId');
        }
        return $this->getDatasetItems($datasetId);
    }

    protected function runActor(array $input): array
    {
        // Use token in query and wait for completion (synchronous).
        $response = $this->httpClient()
            ->timeout(180)
            ->post("https://api.apify.com/v2/acts/{$this->actorIdForApi}/runs?token={$this->token}&waitForFinish=180", $input);

        $data = $response->json();
        if (!$response->successful()) {
            $message = $data['error']['message'] ?? ($data['error'] ?? 'Unknown Apify error');
            throw new \Exception("Apify run gagal ({$response->status()}): {$message}");
        }

        return $data['data'] ?? [];
    }

    protected function getDatasetItems(string $datasetId, int $offset = 0, int $limit = 0): array
    {
        $response = $this->httpClient()
            ->timeout(180)
            ->get("https://api.apify.com/v2/datasets/{$datasetId}/items", [
            'token' => $this->token,
            'clean' => 1,
            'format' => 'json',
            'offset' => max(0, $offset),
            'limit' => $limit > 0 ? min(200, $limit) : null,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Gagal ambil dataset Apify ({$response->status()})");
        }

        $items = $response->json();
        return is_array($items) ? $items : [];
    }

    protected function getDatasetInfo(string $datasetId): array
    {
        $response = $this->httpClient()
            ->timeout(60)
            ->get("https://api.apify.com/v2/datasets/{$datasetId}", [
                'token' => $this->token,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Gagal ambil info dataset Apify ({$response->status()})");
        }

        $data = $response->json();
        return $data['data'] ?? [];
    }

    protected function getPlaceFromDataset(string $datasetId): array
    {
        $items = $this->getDatasetItems($datasetId, 0, 1);

        $placeName = (string)($items[0]['title'] ?? 'Apify Google Reviews');
        $address = (string)($items[0]['address'] ?? '');
        $rating = $items[0]['totalScore'] ?? '';
        $location = $items[0]['location'] ?? null;

        return [
            'name' => $placeName,
            'address' => $address,
            'rating' => $rating,
            'location' => $location,
        ];
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

        \Log::warning('ApifyGoogleReviewsService: CA file not found, disabling SSL verify for this request.', [
            'curl.cainfo' => $curlCaInfo,
            'openssl.cafile' => $opensslCaFile,
        ]);

        return false;
    }

    protected function isReadableFile($path)
    {
        return is_string($path) && $path !== '' && file_exists($path) && is_readable($path);
    }
}

