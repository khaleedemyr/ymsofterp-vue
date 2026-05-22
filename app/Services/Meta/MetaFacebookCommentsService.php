<?php

namespace App\Services\Meta;

use App\Support\MetaPageAccountRegistry;
use App\Support\MetaPageTokens;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Post & komentar Facebook Page (Graph API + Page token).
 *
 * Permission: pages_read_engagement, pages_manage_engagement
 */
class MetaFacebookCommentsService
{
    /**
     * @return list<array{page_id: string, label: string}>
     */
    public function listPages(): array
    {
        $pages = [];
        foreach ($this->resolvePageTokenMap() as $pageId => $token) {
            $pages[] = [
                'page_id' => $pageId,
                'label' => MetaPageAccountRegistry::displayLabel($pageId),
            ];
        }

        if ($pages === []) {
            $token = (string) config('services.meta.page_access_token', '');
            $pageId = (string) config('services.meta.page_id', '');
            if ($token !== '' && $pageId !== '' && ! $this->isLikelyNonPageKey($pageId)) {
                $pages[] = [
                    'page_id' => $pageId,
                    'label' => MetaPageAccountRegistry::displayLabel($pageId),
                ];
            }
        }

        return $pages;
    }

    /**
     * @return array{page_id: string, name: ?string}
     */
    public function resolvePage(string $configuredPageId): array
    {
        [$token, $pageId] = $this->resolveCredentials($configuredPageId);
        $version = config('services.meta.graph_api_version', 'v25.0');

        $me = Http::withToken($token)
            ->acceptJson()
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

            return ['page_id' => $pageId, 'name' => $name !== '' ? $name : null];
        }

        return ['page_id' => $pageId, 'name' => null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPosts(string $configuredPageId, int $limit = 25): array
    {
        [$token, $pageId] = $this->resolveCredentials($configuredPageId);
        $pageId = $this->resolvePage($configuredPageId)['page_id'];
        $version = config('services.meta.graph_api_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$pageId}/published_posts", [
                'fields' => 'id,message,created_time,permalink_url,full_picture,status_type,comments.limit(0).summary(true)',
                'limit' => min(50, max(1, $limit)),
            ]);

        if (! $response->successful()) {
            $feed = Http::withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/{$pageId}/feed", [
                    'fields' => 'id,message,created_time,permalink_url,full_picture,status_type,comments.limit(0).summary(true)',
                    'limit' => min(50, max(1, $limit)),
                ]);
            if (! $feed->successful()) {
                throw new RuntimeException('Gagal memuat post Facebook: '.$response->body(), $response->status());
            }
            $response = $feed;
        }

        $rows = $response->json('data') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $posts = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $posts[] = $this->formatPostRow($row);
            }
        }

        return $posts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listComments(string $configuredPageId, string $postId, int $limit = 50): array
    {
        [$token] = $this->resolveCredentials($configuredPageId);
        $version = config('services.meta.graph_api_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$postId}/comments", [
                'fields' => 'id,message,created_time,from{id,name,picture},comments{id,message,created_time,from{id,name,picture}}',
                'limit' => min(100, max(1, $limit)),
                'filter' => 'stream',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal memuat komentar Facebook: '.$response->body(), $response->status());
        }

        $rows = $response->json('data') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $comments = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $comments[] = $this->formatCommentRow($row);
            }
        }

        return $comments;
    }

    /**
     * @return array<string, mixed>
     */
    public function replyToComment(string $configuredPageId, string $commentId, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Balasan tidak boleh kosong.');
        }

        [$token] = $this->resolveCredentials($configuredPageId);
        $version = config('services.meta.graph_api_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->asForm()
            ->post("https://graph.facebook.com/{$version}/{$commentId}/comments", [
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal membalas komentar Facebook: '.$response->body(), $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatPostRow(array $row): array
    {
        $summary = $row['comments']['summary'] ?? [];
        $commentCount = is_array($summary) ? (int) ($summary['total_count'] ?? 0) : 0;

        return [
            'id' => (string) ($row['id'] ?? ''),
            'caption' => (string) ($row['message'] ?? ''),
            'media_type' => (string) ($row['status_type'] ?? 'POST'),
            'thumbnail_url' => ! empty($row['full_picture']) ? (string) $row['full_picture'] : null,
            'permalink' => (string) ($row['permalink_url'] ?? ''),
            'timestamp' => (string) ($row['created_time'] ?? ''),
            'comments_count' => $commentCount,
            'like_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatCommentRow(array $row): array
    {
        $from = is_array($row['from'] ?? null) ? $row['from'] : [];
        $username = (string) ($from['name'] ?? '');
        $avatar = is_array($from['picture'] ?? null)
            ? (string) ($from['picture']['data']['url'] ?? $from['picture']['url'] ?? '')
            : (string) ($from['picture'] ?? '');

        $replies = [];
        $replyRows = $row['comments']['data'] ?? [];
        if (is_array($replyRows)) {
            foreach ($replyRows as $reply) {
                if (is_array($reply)) {
                    $replies[] = $this->formatCommentRow($reply);
                }
            }
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'text' => (string) ($row['message'] ?? ''),
            'timestamp' => (string) ($row['created_time'] ?? ''),
            'username' => $username,
            'from_id' => (string) ($from['id'] ?? ''),
            'avatar_url' => $avatar !== '' ? $avatar : null,
            'replies' => $replies,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolvePageTokenMap(): array
    {
        $raw = MetaPageTokens::resolved();
        $tokens = [];
        foreach ($raw as $pageId => $token) {
            if ($this->isLikelyNonPageKey((string) $pageId)) {
                continue;
            }
            $tokens[(string) $pageId] = $token;
        }

        return $tokens;
    }

    private function isLikelyNonPageKey(string $key): bool
    {
        return $key === '' || preg_match('/^178414\d+$/', $key) === 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCredentials(string $configuredPageId): array
    {
        $tokens = $this->resolvePageTokenMap();
        $pageId = $configuredPageId !== '' ? $configuredPageId : (string) config('services.meta.page_id', '');

        $token = ($pageId !== '' && isset($tokens[$pageId])) ? $tokens[$pageId] : null;
        $token = $token ?: config('services.meta.page_access_token');

        if (! $token || $pageId === '') {
            if ($tokens !== []) {
                $pageId = (string) array_key_first($tokens);
                $token = $tokens[$pageId];
            }
        }

        if (! $token || $pageId === '') {
            throw new RuntimeException(
                'Token Facebook Page tidak dikonfigurasi. Isi META_PAGE_TOKENS atau META_PAGE_ACCESS_TOKEN + META_PAGE_ID.'
            );
        }

        return [$token, $pageId];
    }
}
