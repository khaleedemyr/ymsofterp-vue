<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Services\Meta\MetaInstagramInboxSyncService;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\MetaInstagramTokens;
use App\Support\MetaPageTokens;
use App\Support\OmniMetaMessagePayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Unduh & cache lampiran inbox ke storage publik ERP (URL Meta CDN cepat kedaluwarsa).
 */
class OmnichannelInboxMediaService
{
    public function needsResolve(OmniMessage $message): bool
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        if (OmniMetaMessagePayload::isEphemeralOnly($payload) || (string) $message->message_type === 'ephemeral') {
            return false;
        }
        if (! empty($payload['local_media_path'])) {
            return false;
        }

        $existing = OmniMetaMessagePayload::extractAttachmentUrl($payload);
        if ($existing !== null && $this->isOurStorageUrl($existing)) {
            return false;
        }

        $type = (string) $message->message_type;
        $body = trim((string) $message->body);

        if (in_array($type, ['image', 'video', 'audio', 'document', 'attachment', 'sticker'], true)) {
            return true;
        }

        return in_array($body, ['[Gambar]', '[Video]', '[Audio]', '[Berkas]', '[Lampiran]', '[Dokumen]'], true)
            || str_starts_with($body, '[PDF:');
    }

    /**
     * @param  bool  $tryFetch  false = hanya baca payload yang sudah ada (cepat)
     */
    public function resolvePublicUrl(OmniMessage $message, ?OmniConversation $conversation = null, bool $tryFetch = true): ?string
    {
        $payload = is_array($message->payload) ? $message->payload : [];

        if (! empty($payload['local_media_path']) && is_string($payload['local_media_path'])) {
            return $this->publicStorageUrl($payload['local_media_path']);
        }

        $existing = OmniMetaMessagePayload::extractAttachmentUrl($payload);
        if ($existing !== null) {
            if ($this->isOurStorageUrl($existing)) {
                return $this->absoluteUrl($existing);
            }
            if (! $tryFetch) {
                return $this->isMetaCdnUrl($existing) ? null : $this->absoluteUrl($existing);
            }
        }

        if ($tryFetch && $conversation !== null && $this->needsResolve($message)) {
            $this->ensureCached($message, $conversation);
            $message->refresh();
            $payload = is_array($message->payload) ? $message->payload : [];
            if (! empty($payload['local_media_path'])) {
                return $this->publicStorageUrl($payload['local_media_path']);
            }
            $localUrl = $payload['local_media_url'] ?? null;
            if (is_string($localUrl) && $localUrl !== '') {
                return $this->absoluteUrl($localUrl);
            }
        }

        $after = OmniMetaMessagePayload::extractAttachmentUrl($payload);
        if ($after !== null && $this->isOurStorageUrl($after)) {
            return $this->absoluteUrl($after);
        }

        return null;
    }

    public function ensureCached(OmniMessage $message, OmniConversation $conversation): void
    {
        if (! $this->needsResolve($message)) {
            return;
        }

        $payload = is_array($message->payload) ? $message->payload : [];
        $channel = (string) $conversation->channel;
        $accessToken = $this->resolveChannelAccessToken($conversation);

        $remoteUrl = OmniMetaMessagePayload::extractAttachmentUrl($payload);
        if ($remoteUrl !== null && str_starts_with($remoteUrl, 'http')) {
            if ($this->cacheRemoteUrl($message, $remoteUrl, null, $accessToken)) {
                return;
            }
        }

        if ($accessToken !== null && $accessToken !== '') {
            foreach (OmniMetaMessagePayload::extractAttachmentIds($payload) as $attachmentId) {
                $fetched = $this->fetchAttachmentUrlById($attachmentId, $accessToken, $channel);
                if ($fetched !== null && $this->cacheRemoteUrl($message, $fetched, null, $accessToken)) {
                    return;
                }
            }
        }

        match ($channel) {
            'whatsapp' => $this->ensureWhatsApp($message, $payload),
            'instagram' => $this->ensureInstagram($message, $conversation, $payload, $accessToken),
            'messenger', 'facebook' => $this->ensureMessenger($message, $conversation, $payload, $accessToken),
            default => null,
        };
    }

    /**
     * Ambil ulang attachment dari Meta (IG) bila cache lokal belum ada.
     */
    public function attemptChannelRepair(OmniMessage $message, OmniConversation $conversation): void
    {
        $channel = (string) $conversation->channel;

        if ($channel === 'instagram') {
            $igId = (string) ($conversation->phone_number_id ?? '');
            $token = $this->resolveChannelAccessToken($conversation);
            if ($igId !== '' && $token !== '') {
                app(MetaInstagramInboxSyncService::class)->repairMessageMedia($message, $token, $igId);
            }

            return;
        }

        if (in_array($channel, ['messenger', 'facebook'], true)) {
            $this->ensureMessenger($message, $conversation, is_array($message->payload) ? $message->payload : [], $this->resolveChannelAccessToken($conversation));
        }
    }

    private function resolveChannelAccessToken(OmniConversation $conversation): ?string
    {
        $accountId = (string) ($conversation->phone_number_id ?? '');

        return match ((string) $conversation->channel) {
            'instagram' => $this->pickToken(MetaInstagramTokens::resolved(), $accountId)
                ?: (string) config('services.meta.instagram_login_access_token'),
            'messenger', 'facebook' => $this->pickToken(MetaPageTokens::resolved(), $accountId)
                ?: (string) config('services.meta.page_access_token'),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function pickToken(array $tokens, string $accountId): ?string
    {
        if ($accountId !== '' && isset($tokens[$accountId]) && $tokens[$accountId] !== '') {
            return $tokens[$accountId];
        }

        $first = reset($tokens);

        return is_string($first) && $first !== '' ? $first : null;
    }

    private function ensureWhatsApp(OmniMessage $message, array $payload): void
    {
        $type = (string) ($payload['type'] ?? $message->message_type);
        $mediaId = match ($type) {
            'image' => (string) ($payload['image']['id'] ?? ''),
            'document' => (string) ($payload['document']['id'] ?? ''),
            'audio' => (string) ($payload['audio']['id'] ?? ''),
            'video' => (string) ($payload['video']['id'] ?? ''),
            'sticker' => (string) ($payload['sticker']['id'] ?? ''),
            default => '',
        };

        if ($mediaId === '') {
            return;
        }

        try {
            $downloaded = app(MetaWhatsAppClient::class)->downloadMedia(
                $mediaId,
                (string) ($payload['document']['filename'] ?? '')
            );
            if ($downloaded !== null) {
                $this->mergePayload($message, $downloaded);
            }
        } catch (\Throwable $e) {
            Log::warning('Omni WA media cache failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
        }
    }

    private function ensureInstagram(OmniMessage $message, OmniConversation $conversation, array $payload, ?string $accessToken = null): void
    {
        $token = $accessToken ?? $this->resolveChannelAccessToken($conversation);
        if ($token === null || $token === '') {
            return;
        }

        $this->fetchMetaMessageAttachments($message, $payload, $token, 'instagram');

        $message->refresh();
        if ($this->needsResolve($message)) {
            $this->attemptChannelRepair($message, $conversation);
        }
    }

    private function ensureMessenger(OmniMessage $message, OmniConversation $conversation, array $payload, ?string $accessToken = null): void
    {
        $token = $accessToken ?? $this->resolveChannelAccessToken($conversation);
        if ($token === null || $token === '') {
            return;
        }

        $this->fetchMetaMessageAttachments($message, $payload, $token, 'facebook');
    }

    /**
     * @param  'instagram'|'facebook'  $platform
     */
    private function fetchMetaMessageAttachments(OmniMessage $message, array $payload, string $token, string $platform): void
    {
        $messageIds = OmniMetaMessagePayload::messageIdCandidates($message);
        if ($messageIds === []) {
            return;
        }

        $version = $platform === 'instagram'
            ? config('services.meta.instagram_graph_version', 'v25.0')
            : config('services.meta.graph_api_version', 'v25.0');

        $hosts = $platform === 'instagram'
            ? ['graph.instagram.com', 'graph.facebook.com']
            : ['graph.facebook.com'];

        $merged = $payload;
        $fields = 'type,mime_type,image_data,file_url,video_data,name,payload,url,image_url';

        foreach ($messageIds as $messageId) {
            foreach ($hosts as $host) {
                $attResponse = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get("https://{$host}/{$version}/{$messageId}/attachments", [
                        'fields' => $fields,
                    ]);

                if ($attResponse->successful()) {
                    $rows = $attResponse->json('data') ?? [];
                    if (is_array($rows) && $rows !== []) {
                        $merged['attachments'] = ['data' => $rows];
                        break 2;
                    }
                }

                $nested = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get("https://{$host}/{$version}/{$messageId}", [
                        'fields' => 'id,attachments{type,mime_type,image_data,file_url,video_data,name,payload,url,image_url}',
                    ]);
                if ($nested->successful()) {
                    $extra = $nested->json('attachments');
                    if (is_array($extra) && ! empty($extra['data'] ?? $extra)) {
                        $merged['attachments'] = $extra;
                        break 2;
                    }
                }
            }
        }

        $normalized = OmniMetaMessagePayload::normalize($merged);
        if ($normalized['attachment_url'] !== null) {
            $this->cacheRemoteUrl($message, $normalized['attachment_url'], $normalized, $token);
        }

        $updates = ['payload' => array_merge($merged, [
            'attachment_url' => $normalized['attachment_url'],
            'media_mime' => $normalized['media_mime'],
            'media_filename' => $normalized['media_filename'],
        ])];
        if ($normalized['body'] && ! $message->body) {
            $updates['body'] = $normalized['body'];
        }
        if ($normalized['message_type'] !== 'text' && ($message->message_type === 'text' || ! $message->message_type)) {
            $updates['message_type'] = $normalized['message_type'];
        }

        $message->update($updates);
    }

    private function cacheRemoteUrl(OmniMessage $message, string $remoteUrl, ?array $normalized = null, ?string $accessToken = null): bool
    {
        try {
            $response = $this->downloadRemoteContent($remoteUrl, $accessToken);
            if ($response === null) {
                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'gif') => 'gif',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'pdf') => 'pdf',
                str_contains($contentType, 'mp4') => 'mp4',
                str_contains($contentType, 'mpeg') => 'mp3',
                default => 'jpg',
            };

            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($message->meta_message_id ?: $message->id)) ?: 'msg';
            $path = "omni-inbound/{$message->conversation_id}/{$safeId}.{$ext}";
            Storage::disk('public')->put($path, $response->body());

            $publicUrl = $this->publicStorageUrl($path);
            $merged = array_merge(is_array($message->payload) ? $message->payload : [], [
                'local_media_path' => $path,
                'local_media_url' => $publicUrl,
                'attachment_url' => $remoteUrl,
            ]);
            if ($normalized !== null) {
                if ($normalized['media_mime']) {
                    $merged['media_mime'] = $normalized['media_mime'];
                }
                if ($normalized['media_filename']) {
                    $merged['media_filename'] = $normalized['media_filename'];
                }
            }

            $message->update(['payload' => $merged]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Omni media cache remote failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Ambil URL file dari attachment_id (webhook kamera IG kadang tanpa payload.url).
     */
    private function fetchAttachmentUrlById(string $attachmentId, string $token, string $channel): ?string
    {
        $version = $channel === 'instagram'
            ? config('services.meta.instagram_graph_version', 'v25.0')
            : config('services.meta.graph_api_version', 'v25.0');

        $hosts = $channel === 'instagram'
            ? ['graph.instagram.com', 'graph.facebook.com']
            : ['graph.facebook.com'];

        foreach ($hosts as $host) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get("https://{$host}/{$version}/{$attachmentId}", [
                    'fields' => 'url,image_url,file_url,mime_type',
                ]);

            if (! $response->successful()) {
                continue;
            }

            foreach (['url', 'image_url', 'file_url'] as $key) {
                $url = $response->json($key);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * Unduh URL CDN Meta (sering butuh access_token di query atau header).
     */
    private function downloadRemoteContent(string $remoteUrl, ?string $accessToken = null): ?\Illuminate\Http\Client\Response
    {
        $url = $remoteUrl;
        $needsAuth = $this->isMetaCdnUrl($remoteUrl) || str_contains(strtolower($remoteUrl), 'fbsbx.com');

        if ($accessToken !== null && $accessToken !== '' && $needsAuth && ! preg_match('/[?&]access_token=/i', $url)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'access_token='.urlencode($accessToken);
        }

        $response = Http::timeout(45)->get($url);
        if ($response->successful()) {
            return $response;
        }

        if ($accessToken !== null && $accessToken !== '' && $needsAuth) {
            $authResponse = Http::withToken($accessToken)->timeout(45)->get($remoteUrl);
            if ($authResponse->successful()) {
                return $authResponse;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $downloaded
     */
    private function mergePayload(OmniMessage $message, array $downloaded): void
    {
        $path = null;
        $localUrl = (string) ($downloaded['local_media_url'] ?? '');
        if ($localUrl !== '' && preg_match('#/storage/(.+)$#', $localUrl, $m)) {
            $path = $m[1];
        }

        $merged = array_merge(is_array($message->payload) ? $message->payload : [], $downloaded);
        if ($path !== null) {
            $merged['local_media_path'] = $path;
            $merged['local_media_url'] = $this->publicStorageUrl($path);
        }

        $message->update(['payload' => $merged]);
    }

    public function isMetaCdnUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'fbcdn.net')
            || str_contains($host, 'facebook.com')
            || str_contains($host, 'instagram.com')
            || str_contains($host, 'cdninstagram.com');
    }

    private function isOurStorageUrl(string $url): bool
    {
        if (str_starts_with($url, '/storage/')) {
            return true;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $appHost !== '' && $urlHost === $appHost && str_contains($url, '/storage/');
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    public function publicStorageUrl(string $storedPath): string
    {
        $relative = Storage::disk('public')->url($storedPath);

        return $this->absoluteUrl($relative);
    }

    /**
     * URL aman untuk UI (web/app): jangan kirim CDN Meta yang kedaluwarsa — paksa muat ulang via /media.
     */
    public function clientSafeMediaUrl(OmniMessage $message, ?OmniConversation $conversation = null): ?string
    {
        $url = $this->resolvePublicUrl($message, $conversation, false);
        if ($url !== null) {
            return $url;
        }

        $payload = is_array($message->payload) ? $message->payload : [];
        if (! empty($payload['local_media_path']) && is_string($payload['local_media_path'])) {
            return $this->publicStorageUrl($payload['local_media_path']);
        }

        $candidate = OmniMetaMessagePayload::mediaUrlForInbox($payload, (string) $message->message_type);
        if ($candidate !== null && ! $this->isMetaCdnUrl($candidate)) {
            return $this->absoluteUrl($candidate);
        }

        return null;
    }
}
