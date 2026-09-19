<?php

namespace App\Services\Meta;

use App\Models\SocialContentMetric;
use App\Models\SocialContentPost;
use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaInstagramTokens;
use App\Support\MetaPageAccountRegistry;
use App\Support\MetaPageTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync konten IG/FB + metrik performa ke social_content_* (dashboard).
 *
 * Reuse token omnichannel. Insights yang gagal (permission) disimpan null;
 * likes/comments tetap diisi dari field media/post.
 */
class MetaSocialContentSyncService
{
    /**
     * @return array{synced: int, errors: int, accounts: int, error_details: list<string>}
     */
    public function syncAll(?int $limitPerAccount = null): array
    {
        $limit = $limitPerAccount ?? (int) config('omnichannel.social_content_posts_per_account', 50);
        $limit = min(100, max(5, $limit));

        $synced = 0;
        $errors = 0;
        $accounts = 0;
        $errorDetails = [];

        foreach (MetaInstagramTokens::resolved() as $igId => $token) {
            if ($token === '') {
                continue;
            }
            $accounts++;
            try {
                $synced += $this->syncInstagramAccount((string) $igId, $token, $limit);
            } catch (Throwable $e) {
                $errors++;
                $msg = "IG {$igId}: ".$e->getMessage();
                $errorDetails[] = $msg;
                Log::warning('[social-content-sync] '.$msg);
            }
        }

        foreach ($this->resolvePageTokenMap() as $pageId => $token) {
            $accounts++;
            try {
                $synced += $this->syncFacebookPage((string) $pageId, $token, $limit);
            } catch (Throwable $e) {
                $errors++;
                $msg = "FB {$pageId}: ".$e->getMessage();
                $errorDetails[] = $msg;
                Log::warning('[social-content-sync] '.$msg);
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'accounts' => $accounts,
            'error_details' => $errorDetails,
        ];
    }

    private function syncInstagramAccount(string $configuredIgId, string $token, int $limit): int
    {
        $version = config('services.meta.instagram_graph_version', 'v25.0');
        $igId = $this->resolveInstagramUserId($configuredIgId, $token, $version);
        $label = MetaInstagramAccountRegistry::displayLabel($igId);

        $media = $this->paginateGraph(
            "https://graph.instagram.com/{$version}/{$igId}/media",
            $token,
            [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,timestamp,permalink,comments_count,like_count',
            ],
            $limit
        );

        $count = 0;
        foreach ($media as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $insights = $this->fetchInstagramInsights($token, $version, (string) $row['id'], (string) ($row['media_type'] ?? ''));
            $this->upsertPostAndMetrics(
                platform: 'instagram',
                accountId: $igId,
                accountLabel: $label,
                externalId: (string) $row['id'],
                caption: (string) ($row['caption'] ?? ''),
                permalink: (string) ($row['permalink'] ?? ''),
                thumbnailUrl: (string) ($row['thumbnail_url'] ?? $row['media_url'] ?? ''),
                mediaType: (string) ($row['media_type'] ?? 'UNKNOWN'),
                postedAt: $this->parseTimestamp((string) ($row['timestamp'] ?? '')),
                metrics: [
                    'likes' => isset($row['like_count']) ? (int) $row['like_count'] : null,
                    'comments' => isset($row['comments_count']) ? (int) $row['comments_count'] : null,
                    'shares' => $insights['shares'] ?? null,
                    'saved' => $insights['saved'] ?? null,
                    'impressions' => $insights['impressions'] ?? $insights['views'] ?? null,
                    'reach' => $insights['reach'] ?? null,
                    'clicks' => $insights['clicks'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function syncFacebookPage(string $configuredPageId, string $token, int $limit): int
    {
        $version = config('services.meta.graph_api_version', 'v25.0');
        $pageId = $this->resolveFacebookPageId($configuredPageId, $token, $version);
        $label = MetaPageAccountRegistry::displayLabel($pageId);

        $fields = 'id,message,created_time,permalink_url,full_picture,status_type,'
            .'comments.limit(0).summary(true),'
            .'reactions.summary(true),'
            .'shares';

        $posts = $this->paginateGraph(
            "https://graph.facebook.com/{$version}/{$pageId}/published_posts",
            $token,
            ['fields' => $fields],
            $limit
        );

        if ($posts === []) {
            $posts = $this->paginateGraph(
                "https://graph.facebook.com/{$version}/{$pageId}/feed",
                $token,
                ['fields' => $fields],
                $limit
            );
        }

        $count = 0;
        foreach ($posts as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }

            $commentSummary = is_array($row['comments']['summary'] ?? null) ? $row['comments']['summary'] : [];
            $reactionSummary = is_array($row['reactions']['summary'] ?? null) ? $row['reactions']['summary'] : [];
            $sharesCount = is_array($row['shares'] ?? null) ? (int) ($row['shares']['count'] ?? 0) : null;

            $insights = $this->fetchFacebookPostInsights($token, $version, (string) $row['id']);

            $this->upsertPostAndMetrics(
                platform: 'facebook',
                accountId: $pageId,
                accountLabel: $label,
                externalId: (string) $row['id'],
                caption: (string) ($row['message'] ?? ''),
                permalink: (string) ($row['permalink_url'] ?? ''),
                thumbnailUrl: (string) ($row['full_picture'] ?? ''),
                mediaType: (string) ($row['status_type'] ?? 'POST'),
                postedAt: $this->parseTimestamp((string) ($row['created_time'] ?? '')),
                metrics: [
                    'likes' => isset($reactionSummary['total_count']) ? (int) $reactionSummary['total_count'] : null,
                    'comments' => isset($commentSummary['total_count']) ? (int) $commentSummary['total_count'] : null,
                    'shares' => $sharesCount,
                    'saved' => null,
                    'impressions' => $insights['impressions'] ?? null,
                    'reach' => $insights['reach'] ?? null,
                    'clicks' => $insights['clicks'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, int|null>  $metrics
     */
    private function upsertPostAndMetrics(
        string $platform,
        string $accountId,
        string $accountLabel,
        string $externalId,
        string $caption,
        string $permalink,
        string $thumbnailUrl,
        string $mediaType,
        ?Carbon $postedAt,
        array $metrics,
    ): void {
        $now = now();

        $post = SocialContentPost::query()->updateOrCreate(
            [
                'platform' => $platform,
                'external_id' => $externalId,
            ],
            [
                'account_id' => $accountId,
                'account_label' => $accountLabel !== '' ? $accountLabel : null,
                'caption' => $caption !== '' ? mb_substr($caption, 0, 65000) : null,
                'permalink' => $permalink !== '' ? $permalink : null,
                'thumbnail_url' => $thumbnailUrl !== '' ? $thumbnailUrl : null,
                'media_type' => $mediaType !== '' ? $mediaType : null,
                'posted_at' => $postedAt,
                'last_synced_at' => $now,
            ]
        );

        SocialContentMetric::query()->updateOrCreate(
            ['social_content_post_id' => $post->id],
            [
                'likes' => $metrics['likes'],
                'comments' => $metrics['comments'],
                'shares' => $metrics['shares'],
                'saved' => $metrics['saved'],
                'impressions' => $metrics['impressions'],
                'reach' => $metrics['reach'],
                'clicks' => $metrics['clicks'],
                'synced_at' => $now,
            ]
        );
    }

    /**
     * @return array<string, int|null>
     */
    private function fetchInstagramInsights(string $token, string $version, string $mediaId, string $mediaType): array
    {
        $metricSets = [
            ['impressions', 'reach', 'saved', 'shares'],
            ['views', 'reach', 'saved', 'shares'],
            ['impressions', 'reach', 'saved'],
            ['views', 'reach', 'saved'],
            ['reach', 'saved'],
        ];

        if (strtoupper($mediaType) === 'VIDEO' || strtoupper($mediaType) === 'REELS') {
            array_unshift($metricSets, ['views', 'reach', 'saved', 'shares']);
        }

        foreach ($metricSets as $metrics) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get("https://graph.instagram.com/{$version}/{$mediaId}/insights", [
                    'metric' => implode(',', $metrics),
                ]);

            if (! $response->successful()) {
                continue;
            }

            return $this->parseInsightRows($response->json('data') ?? []);
        }

        return [];
    }

    /**
     * @return array<string, int|null>
     */
    private function fetchFacebookPostInsights(string $token, string $version, string $postId): array
    {
        $metricSets = [
            ['post_impressions', 'post_impressions_unique', 'post_clicks'],
            ['post_impressions', 'post_impressions_unique'],
            ['post_media_view'],
        ];

        foreach ($metricSets as $metrics) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get("https://graph.facebook.com/{$version}/{$postId}/insights", [
                    'metric' => implode(',', $metrics),
                ]);

            if (! $response->successful()) {
                continue;
            }

            $parsed = $this->parseInsightRows($response->json('data') ?? []);

            return [
                'impressions' => $parsed['post_impressions'] ?? $parsed['post_media_view'] ?? null,
                'reach' => $parsed['post_impressions_unique'] ?? null,
                'clicks' => $parsed['post_clicks'] ?? null,
            ];
        }

        return [];
    }

    /**
     * @param  mixed  $rows
     * @return array<string, int|null>
     */
    private function parseInsightRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $values = $row['values'] ?? [];
            $value = null;
            if (is_array($values) && isset($values[0]['value'])) {
                $value = $values[0]['value'];
            } elseif (isset($row['value'])) {
                $value = $row['value'];
            }
            if (is_numeric($value)) {
                $out[$name] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function paginateGraph(string $url, string $token, array $query, int $limit): array
    {
        $collected = [];
        $nextUrl = $url;
        $params = array_merge($query, ['limit' => min(50, $limit)]);

        while ($nextUrl !== null && count($collected) < $limit) {
            $response = $nextUrl === $url
                ? Http::withToken($token)->acceptJson()->timeout(45)->get($nextUrl, $params)
                : Http::withToken($token)->acceptJson()->timeout(45)->get($nextUrl);

            if (! $response->successful()) {
                if ($collected === []) {
                    throw new \RuntimeException('Graph list gagal: '.$response->body());
                }
                break;
            }

            $rows = $response->json('data') ?? [];
            if (! is_array($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $collected[] = $row;
                    if (count($collected) >= $limit) {
                        break 2;
                    }
                }
            }

            $pagingNext = $response->json('paging.next');
            $nextUrl = is_string($pagingNext) && $pagingNext !== '' ? $pagingNext : null;
        }

        return $collected;
    }

    private function resolveInstagramUserId(string $configuredIgId, string $token, string $version): string
    {
        $igId = $configuredIgId;
        $me = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.instagram.com/{$version}/me", [
                'fields' => 'user_id,username',
            ]);

        if ($me->successful()) {
            $resolved = (string) ($me->json('user_id') ?? '');
            if ($resolved !== '') {
                $igId = $resolved;
            }
            $username = (string) ($me->json('username') ?? '');
            if ($username !== '') {
                MetaInstagramAccountRegistry::remember($igId, $username);
            }
        }

        return $igId;
    }

    private function resolveFacebookPageId(string $configuredPageId, string $token, string $version): string
    {
        $pageId = $configuredPageId;
        $me = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/me", ['fields' => 'id,name']);

        if ($me->successful()) {
            $resolved = (string) ($me->json('id') ?? '');
            if ($resolved !== '') {
                $pageId = $resolved;
            }
            $name = (string) ($me->json('name') ?? '');
            if ($name !== '') {
                MetaPageAccountRegistry::remember($pageId, $name);
            }
        }

        return $pageId;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePageTokenMap(): array
    {
        $raw = MetaPageTokens::resolved();
        $tokens = [];
        foreach ($raw as $pageId => $token) {
            $key = (string) $pageId;
            if ($key === '' || preg_match('/^178414\d+$/', $key) === 1) {
                continue;
            }
            if ($token === '') {
                continue;
            }
            $tokens[$key] = $token;
        }

        if ($tokens === []) {
            $token = (string) config('services.meta.page_access_token', '');
            $pageId = (string) config('services.meta.page_id', '');
            if ($token !== '' && $pageId !== '' && preg_match('/^178414\d+$/', $pageId) !== 1) {
                $tokens[$pageId] = $token;
            }
        }

        return $tokens;
    }

    private function parseTimestamp(string $raw): ?Carbon
    {
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
