<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstagramCommentImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, int>  $postUrlToId  normalized post URL => instagram_posts.id
     */
    public function upsertFromApifyCommentItems(array $items, array $postUrlToId): int
    {
        $saved = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $postId = $this->resolvePostId($item, $postUrlToId);
            if ($postId === null) {
                continue;
            }
            $externalId = $this->resolveExternalId($item);
            $username = $this->resolveUsername($item);
            $text = $this->resolveText($item);
            $commentedAt = $this->resolveCommentedAt($item);

            try {
                $rawJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $rawJson = null;
            }

            $now = now();
            DB::table('instagram_comments')->upsert(
                [
                    [
                        'instagram_post_id' => $postId,
                        'external_id' => $externalId,
                        'username' => $username,
                        'text' => $text,
                        'commented_at' => $commentedAt,
                        'raw_json' => $rawJson,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ],
                ['instagram_post_id', 'external_id'],
                ['username', 'text', 'commented_at', 'raw_json', 'updated_at']
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * @param  array<string, int>  $postUrlToId
     */
    protected function resolvePostId(array $item, array $postUrlToId): ?int
    {
        $candidates = [];
        foreach (['inputUrl', 'url', 'postUrl', 'parentUrl'] as $k) {
            if (! empty($item[$k])) {
                $candidates[] = (string) $item[$k];
            }
        }
        foreach ($candidates as $c) {
            $norm = $this->normalizePostUrl($c);
            if ($norm !== '' && isset($postUrlToId[$norm])) {
                return $postUrlToId[$norm];
            }
        }
        if (! empty($item['shortCode'])) {
            $short = strtolower((string) $item['shortCode']);
            foreach ($postUrlToId as $key => $id) {
                if ($key === 'p/'.$short || str_contains($key, 'p/'.$short)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /** Kunci kanonik untuk mencocokkan URL post ke baris DB. */
    public function postUrlKey(string $url): string
    {
        return $this->normalizePostUrl($url);
    }

    protected function normalizePostUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('~instagram\.com/p/([^/?#]+)~i', $url, $m)) {
            return 'p/'.strtolower($m[1]);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveExternalId(array $item): string
    {
        $id = $item['id'] ?? $item['pk'] ?? $item['commentId'] ?? null;
        if ($id !== null && $id !== '') {
            return (string) $id;
        }
        $base = ($item['text'] ?? '').'|'.($item['ownerUsername'] ?? ($item['owner']['username'] ?? '')).'|'.($item['timestamp'] ?? $item['createdAt'] ?? '');

        return 'h:'.substr(sha1($base), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveUsername(array $item): ?string
    {
        $u = $item['ownerUsername'] ?? $item['owner']['username'] ?? $item['username'] ?? null;

        return $u !== null && $u !== '' ? (string) $u : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveText(array $item): ?string
    {
        $t = $item['text'] ?? $item['comment'] ?? null;

        return $t !== null ? (string) $t : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveCommentedAt(array $item): ?\Illuminate\Support\Carbon
    {
        $raw = $item['timestamp']
            ?? $item['createdAt']
            ?? $item['created_at']
            ?? $item['created_time']
            ?? $item['time']
            ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            if (is_int($raw) || is_float($raw) || (is_string($raw) && preg_match('/^\d+$/', trim($raw)))) {
                $n = (int) $raw;
                // Handle Unix milliseconds from some payload variants.
                if ($n > 9999999999) {
                    $n = (int) floor($n / 1000);
                }
                if ($n > 0) {
                    return \Carbon\Carbon::createFromTimestamp($n);
                }
            }

            return \Carbon\Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
