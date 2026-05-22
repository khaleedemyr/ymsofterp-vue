<?php

namespace App\Services\Meta;

use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaInstagramTokens;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Post & komentar Instagram (Instagram API with Instagram Login).
 *
 * Permission Meta: instagram_business_basic, instagram_business_manage_comments
 */
class MetaInstagramCommentsService
{
    /**
     * @return list<array{ig_id: string, label: string}>
     */
    public function listAccounts(): array
    {
        $accounts = [];
        foreach (MetaInstagramTokens::resolved() as $igId => $token) {
            if ($token === '') {
                continue;
            }
            $accounts[] = [
                'ig_id' => (string) $igId,
                'label' => MetaInstagramAccountRegistry::displayLabel((string) $igId),
            ];
        }

        return $accounts;
    }

    /**
     * @return array{ig_id: string, username: ?string}
     */
    public function resolveAccount(string $configuredIgId): array
    {
        [$token, $igId] = $this->resolveCredentials($configuredIgId);
        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $me = Http::withToken($token)
            ->acceptJson()
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

        return [
            'ig_id' => $igId,
            'username' => $me->successful() ? (string) ($me->json('username') ?? '') : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMedia(string $configuredIgId, int $limit = 25): array
    {
        [$token, $igId] = $this->resolveCredentials($configuredIgId);
        $account = $this->resolveAccount($configuredIgId);
        $igId = $account['ig_id'];
        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$igId}/media", [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,timestamp,permalink,comments_count,like_count',
                'limit' => min(50, max(1, $limit)),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal memuat post Instagram: '.$response->body(), $response->status());
        }

        $rows = $response->json('data') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $media = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $media[] = $this->formatMediaRow($row);
        }

        return $media;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listComments(string $configuredIgId, string $mediaId, int $limit = 50): array
    {
        [$token] = $this->resolveCredentials($configuredIgId);
        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$mediaId}/comments", [
                'fields' => 'id,text,timestamp,username,from,replies{id,text,timestamp,username,from}',
                'limit' => min(100, max(1, $limit)),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal memuat komentar: '.$response->body(), $response->status());
        }

        $rows = $response->json('data') ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $comments = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $comments[] = $this->formatCommentRow($row);
        }

        return $comments;
    }

    /**
     * @return array<string, mixed>
     */
    public function replyToComment(string $configuredIgId, string $commentId, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Balasan tidak boleh kosong.');
        }

        [$token] = $this->resolveCredentials($configuredIgId);
        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->asForm()
            ->post("https://graph.instagram.com/{$version}/{$commentId}/replies", [
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal membalas komentar: '.$response->body(), $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatMediaRow(array $row): array
    {
        $type = (string) ($row['media_type'] ?? 'UNKNOWN');
        $thumb = (string) ($row['thumbnail_url'] ?? $row['media_url'] ?? '');

        return [
            'id' => (string) ($row['id'] ?? ''),
            'caption' => (string) ($row['caption'] ?? ''),
            'media_type' => $type,
            'thumbnail_url' => $thumb !== '' ? $thumb : null,
            'permalink' => (string) ($row['permalink'] ?? ''),
            'timestamp' => (string) ($row['timestamp'] ?? ''),
            'comments_count' => (int) ($row['comments_count'] ?? 0),
            'like_count' => (int) ($row['like_count'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function formatCommentRow(array $row): array
    {
        $from = is_array($row['from'] ?? null) ? $row['from'] : [];
        $username = (string) ($row['username'] ?? $from['username'] ?? '');
        $replies = [];
        $replyRows = $row['replies']['data'] ?? $row['replies'] ?? [];
        if (is_array($replyRows)) {
            foreach ($replyRows as $reply) {
                if (is_array($reply)) {
                    $replies[] = $this->formatCommentRow($reply);
                }
            }
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'text' => (string) ($row['text'] ?? ''),
            'timestamp' => (string) ($row['timestamp'] ?? ''),
            'username' => $username,
            'from_id' => (string) ($from['id'] ?? ''),
            'replies' => $replies,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCredentials(string $configuredIgId): array
    {
        $tokens = MetaInstagramTokens::resolved();
        $igId = $configuredIgId !== '' ? $configuredIgId : (string) config('services.meta.instagram_login_default_id');

        $token = ($igId !== '' && isset($tokens[$igId])) ? $tokens[$igId] : null;
        $token = $token ?: config('services.meta.instagram_login_access_token');

        if (! $token || $igId === '') {
            if ($tokens !== []) {
                $igId = (string) array_key_first($tokens);
                $token = $tokens[$igId];
            }
        }

        if (! $token || $igId === '') {
            throw new RuntimeException(
                'Token Instagram Login tidak dikonfigurasi. Isi META_INSTAGRAM_LOGIN_TOKENS di .env'
            );
        }

        return [$token, $igId];
    }
}
