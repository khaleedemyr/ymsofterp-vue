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

        $reviews = collect($items)->map(fn ($item) => $this->mapApifyItemToReview($item))->values()->all();

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

    /**
     * Untuk progress UI: jumlah baris di dataset (tanpa mengunduh semua).
     */
    public function getDatasetItemCount(string $datasetId): int
    {
        $info = $this->getDatasetInfo($datasetId);

        return max(0, (int) ($info['itemCount'] ?? 0));
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
     * @param  callable(int $loaded, int $total): void|null  $onProgress
     * @return array<int, array<string, mixed>>
     */
    public function getAllReviewsFromDataset(
        string $datasetId,
        ?callable $onProgress = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $datasetInfo = $this->getDatasetInfo($datasetId);
        $total = (int) ($datasetInfo['itemCount'] ?? 0);
        $all = [];
        $limit = 200;
        [$fromTs, $toTs] = $this->buildDateRangeBoundaries($dateFrom, $dateTo);
        for ($offset = 0; $offset < $total; $offset += $limit) {
            $items = $this->getDatasetItems($datasetId, $offset, $limit);
            foreach ($items as $item) {
                $mapped = $this->mapApifyItemToReview($item);
                if (! $this->reviewInDateRange($mapped, $fromTs, $toTs)) {
                    continue;
                }
                $all[] = $mapped;
            }
            if ($onProgress !== null) {
                $onProgress(count($all), max(1, $total));
            }
        }

        return $all;
    }

    public function getReviewsPageFromDataset(
        string $datasetId,
        int $page = 1,
        int $perPage = 20,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        if ($dateFrom !== null || $dateTo !== null) {
            $allFiltered = $this->getAllReviewsFromDataset($datasetId, null, $dateFrom, $dateTo);
            $total = count($allFiltered);
            $lastPage = (int) max(1, (int) ceil(max(1, $total) / $perPage));
            $offset = ($page - 1) * $perPage;
            $reviews = array_slice($allFiltered, $offset, $perPage);

            return [
                'reviews' => array_values($reviews),
                'meta' => [
                    'page' => min($page, $lastPage),
                    'perPage' => $perPage,
                    'total' => $total,
                    'lastPage' => $lastPage,
                ],
            ];
        }

        $offset = ($page - 1) * $perPage;
        $datasetInfo = $this->getDatasetInfo($datasetId);
        $items = $this->getDatasetItems($datasetId, $offset, $perPage);

        $reviews = collect($items)->map(fn ($item) => $this->mapApifyItemToReview($item))->values()->all();

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

    public function exportDatasetReviewsToCsv(
        string $datasetId,
        callable $writer,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): void
    {
        $datasetInfo = $this->getDatasetInfo($datasetId);
        $total = (int)($datasetInfo['itemCount'] ?? 0);
        [$fromTs, $toTs] = $this->buildDateRangeBoundaries($dateFrom, $dateTo);

        $writer(['Author', 'Rating', 'Date', 'Text', 'Profile Photo']);

        $limit = 200;
        for ($offset = 0; $offset < $total; $offset += $limit) {
            $items = $this->getDatasetItems($datasetId, $offset, $limit);
            foreach ($items as $item) {
                $mapped = $this->mapApifyItemToReview($item);
                if (! $this->reviewInDateRange($mapped, $fromTs, $toTs)) {
                    continue;
                }
                $writer([
                    (string)($mapped['author'] ?? ''),
                    (string)($mapped['rating'] ?? ''),
                    (string)($mapped['date'] ?? ''),
                    (string)($mapped['text'] ?? ''),
                    (string)($mapped['profile_photo'] ?? ''),
                ]);
            }
        }
    }

    protected function buildDateRangeBoundaries(?string $dateFrom, ?string $dateTo): array
    {
        $fromTs = null;
        $toTs = null;
        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $fromTs = strtotime(trim($dateFrom).' 00:00:00 UTC') ?: null;
        }
        if ($dateTo !== null && trim($dateTo) !== '') {
            $toTs = strtotime(trim($dateTo).' 23:59:59 UTC') ?: null;
        }

        return [$fromTs, $toTs];
    }

    protected function reviewInDateRange(array $review, ?int $fromTs, ?int $toTs): bool
    {
        if ($fromTs === null && $toTs === null) {
            return true;
        }
        $time = (int) ($review['time'] ?? 0);
        if ($time <= 0) {
            return false;
        }
        if ($fromTs !== null && $time < $fromTs) {
            return false;
        }
        if ($toTs !== null && $time > $toTs) {
            return false;
        }

        return true;
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

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function mapApifyItemToReview(array $item): array
    {
        $time = 0;
        if (! empty($item['publishedAtDate'])) {
            $time = strtotime($item['publishedAtDate']) ?: 0;
        }
        $rid = $item['reviewId'] ?? $item['review_id'] ?? $item['id'] ?? null;

        return [
            'author' => (string) ($item['name'] ?? ''),
            'rating' => (string) ($item['stars'] ?? ''),
            'date' => (string) ($item['publishAt'] ?? ($item['publishedAtDate'] ?? '')),
            'text' => (string) ($item['text'] ?? ''),
            'profile_photo' => (string) ($item['reviewerPhotoUrl'] ?? ''),
            'time' => (int) $time,
            'review_id' => $rid !== null && $rid !== '' ? (string) $rid : '',
        ];
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

