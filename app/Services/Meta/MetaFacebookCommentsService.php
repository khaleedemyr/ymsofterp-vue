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

        // Selalu pakai Page ID dari config/key META_PAGE_TOKENS.
        // Jangan timpa dengan /me — User token membuat /me = User ID, lalu
        // /{user-id}/published_posts error (#100) nonexisting field.
        $name = null;

        $page = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/{$pageId}", [
                'fields' => 'id,name',
            ]);

        if ($page->successful()) {
            $name = (string) ($page->json('name') ?? '') ?: null;
            if ($name !== null) {
                MetaPageAccountRegistry::remember($pageId, $name);
            }

            return ['page_id' => $pageId, 'name' => $name];
        }

        $me = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/me", ['fields' => 'id,name']);

        if ($me->successful()) {
            $resolved = (string) ($me->json('id') ?? '');
            // Hanya pakai /me id jika sama dengan Page yang diminta (Page token).
            if ($resolved !== '' && $resolved === $pageId) {
                $name = (string) ($me->json('name') ?? '') ?: null;
                if ($name !== null) {
                    MetaPageAccountRegistry::remember($pageId, $name);
                }
            }
        }

        return ['page_id' => $pageId, 'name' => $name];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPosts(string $configuredPageId, int $limit = 25): array
    {
        [$token, $pageId] = $this->resolveCredentials($configuredPageId);
        // Jangan resolvePage()[/me] — pakai Page ID yang dipilih user.
        $pageId = $configuredPageId !== '' ? $configuredPageId : $pageId;
        $token = $this->resolveTokenForPage($token, $pageId);
        $version = config('services.meta.graph_api_version', 'v25.0');
        $limit = min(50, max(1, $limit));
        $fields = 'id,message,created_time,permalink_url,full_picture,status_type,comments.limit(0).summary(true)';

        $endpoints = ['published_posts', 'posts', 'feed'];
        $lastError = null;

        foreach ($endpoints as $edge) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(45)
                ->get("https://graph.facebook.com/{$version}/{$pageId}/{$edge}", [
                    'fields' => $fields,
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                $lastError = $response->body();
                continue;
            }

            $rows = $response->json('data') ?? [];
            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $posts = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $posts[] = $this->formatPostRow($row);
                }
            }

            if ($posts !== []) {
                return $posts;
            }
        }

        if ($lastError !== null) {
            throw new RuntimeException('Gagal memuat post Facebook: '.$lastError);
        }

        return [];
    }

    /**
     * Jika token adalah User token, ambil Page access token dari /me/accounts.
     */
    private function resolveTokenForPage(string $token, string $pageId): string
    {
        $version = config('services.meta.graph_api_version', 'v25.0');

        $me = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/me", ['fields' => 'id']);

        if (! $me->successful()) {
            return $token;
        }

        $meId = (string) ($me->json('id') ?? '');
        // Sudah Page token untuk page ini.
        if ($meId !== '' && $meId === $pageId) {
            return $token;
        }

        $accounts = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get("https://graph.facebook.com/{$version}/me/accounts", [
                'fields' => 'id,name,access_token',
                'limit' => 100,
            ]);

        if (! $accounts->successful()) {
            return $token;
        }

        $rows = $accounts->json('data') ?? [];
        if (! is_array($rows)) {
            return $token;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((string) ($row['id'] ?? '') === $pageId) {
                $pageToken = (string) ($row['access_token'] ?? '');
                if ($pageToken !== '') {
                    $name = (string) ($row['name'] ?? '');
                    if ($name !== '') {
                        MetaPageAccountRegistry::remember($pageId, $name);
                    }

                    return $pageToken;
                }
            }
        }

        return $token;
    }

    /**
     * Diagnosa cepat token + edge posts (untuk sync dashboard).
     *
     * @return array{
     *   configured_page_id: string,
     *   resolved_page_id: string,
     *   page_name: ?string,
     *   token_ok: bool,
     *   edges: array<string, array{ok: bool, status: int, count: int, error: ?string}>
     * }
     */
    public function diagnosePostsAccess(string $configuredPageId): array
    {
        [$token, $configured] = $this->resolveCredentials($configuredPageId);
        $pageId = $configuredPageId !== '' ? $configuredPageId : $configured;
        $token = $this->resolveTokenForPage($token, $pageId);
        $version = config('services.meta.graph_api_version', 'v25.0');

        $page = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get("https://graph.facebook.com/{$version}/{$pageId}", ['fields' => 'id,name']);

        $pageName = $page->successful() ? ((string) ($page->json('name') ?? '') ?: null) : null;

        $edges = [];
        foreach (['published_posts', 'posts', 'feed'] as $edge) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get("https://graph.facebook.com/{$version}/{$pageId}/{$edge}", [
                    'fields' => 'id,created_time',
                    'limit' => 5,
                ]);

            $edges[$edge] = [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'count' => is_array($response->json('data')) ? count($response->json('data')) : 0,
                'error' => $response->successful()
                    ? null
                    : (string) ($response->json('error.message') ?? mb_substr($response->body(), 0, 240)),
            ];
        }

        return [
            'configured_page_id' => $configured,
            'resolved_page_id' => $pageId,
            'page_name' => $pageName,
            'token_ok' => $page->successful(),
            'me_error' => $page->successful()
                ? null
                : (string) ($page->json('error.message') ?? mb_substr($page->body(), 0, 240)),
            'edges' => $edges,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listComments(string $configuredPageId, string $postId, int $limit = 50): array
    {
        [$token, $pageId] = $this->resolveCredentials($configuredPageId);
        $pageId = $configuredPageId !== '' ? $configuredPageId : $pageId;
        $token = $this->resolveTokenForPage($token, $pageId);
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

        [$token, $pageId] = $this->resolveCredentials($configuredPageId);
        $pageId = $configuredPageId !== '' ? $configuredPageId : $pageId;
        $token = $this->resolveTokenForPage($token, $pageId);
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
