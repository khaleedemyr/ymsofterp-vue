<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstagramPostImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function upsertFromApifyPostItems(array $items): int
    {
        $saved = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $shortCode = (string) ($item['shortCode'] ?? '');
            if ($shortCode === '') {
                continue;
            }
            $url = (string) ($item['url'] ?? '');
            if ($url === '' || ! str_contains($url, '/p/')) {
                continue;
            }
            $profileKey = $this->resolveProfileKey($item);
            $caption = isset($item['caption']) ? (string) $item['caption'] : null;
            $commentsCount = (int) ($item['commentsCount'] ?? 0);
            $likesCount = (int) ($item['likesCount'] ?? 0);
            $viewsCount = (int) ($item['videoViewCount'] ?? ($item['videoPlayCount'] ?? ($item['video_view_count'] ?? 0)));
            $mediaUrl = (string) ($item['displayUrl'] ?? '');
            if ($mediaUrl === '' && isset($item['images'][0])) {
                $mediaUrl = (string) $item['images'][0];
            }
            $owner = (string) ($item['ownerUsername'] ?? '');
            $ts = null;
            if (! empty($item['timestamp'])) {
                try {
                    $ts = \Carbon\Carbon::parse((string) $item['timestamp']);
                } catch (\Throwable) {
                    $ts = null;
                }
            }

            try {
                $rawJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $rawJson = null;
            }

            $now = now();
            DB::table('instagram_posts')->upsert(
                [
                    [
                        'profile_key' => $profileKey,
                        'short_code' => $shortCode,
                        'post_url' => $url,
                        'caption' => $caption,
                        'comments_count' => $commentsCount,
                        'likes_count' => $likesCount,
                        'views_count' => $viewsCount,
                        'owner_username' => $owner !== '' ? $owner : null,
                        'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
                        'post_timestamp' => $ts,
                        'raw_json' => $rawJson,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ],
                ['profile_key', 'short_code'],
                ['post_url', 'caption', 'comments_count', 'likes_count', 'views_count', 'owner_username', 'media_url', 'post_timestamp', 'raw_json', 'updated_at']
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveProfileKey(array $item): string
    {
        $inputUrl = (string) ($item['inputUrl'] ?? '');
        $key = $this->profileKeyFromProfileUrl($inputUrl);
        if ($key !== 'unknown') {
            return $key;
        }

        $owner = (string) ($item['ownerUsername'] ?? '');
        if ($owner !== '') {
            foreach (config('instagram.profiles', []) as $k => $meta) {
                $path = (string) (parse_url((string) ($meta['url'] ?? ''), PHP_URL_PATH) ?? '');
                $handle = strtolower(trim($path, '/'));
                if ($handle !== '' && strtolower($owner) === $handle) {
                    return $k;
                }
            }
        }

        return 'unknown';
    }

    protected function profileKeyFromProfileUrl(string $inputUrl): string
    {
        if ($inputUrl === '') {
            return 'unknown';
        }
        $normalized = rtrim(strtolower($inputUrl), '/');
        foreach (config('instagram.profiles', []) as $key => $meta) {
            $u = rtrim(strtolower((string) ($meta['url'] ?? '')), '/');
            if ($u === '') {
                continue;
            }
            if ($normalized === $u || str_starts_with($normalized, $u)) {
                return $key;
            }
        }

        return 'unknown';
    }
}
