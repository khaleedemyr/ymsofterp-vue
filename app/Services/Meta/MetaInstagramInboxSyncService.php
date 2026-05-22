<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaInstagramTokens;
use App\Support\OmniMetaMessageId;
use App\Support\OmniMetaMessagePayload;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sinkron DM Instagram (Instagram Login API) via polling.
 */
class MetaInstagramInboxSyncService
{
    private const MESSAGE_PAGE_LIMIT = 25;

    private const MAX_MESSAGE_PAGES_PER_CONVERSATION = 4;

    /** Berhenti scan thread jika N pesan teratas sudah ada di DB. */
    private const STOP_AFTER_CONSECUTIVE_EXISTING = 8;

    /**
     * @return array{imported: int, accounts: list<array<string, mixed>>}
     */
    public function syncAll(bool $verbose = false, ?int $recentMinutes = null): array
    {
        $imported = 0;
        $accounts = [];
        $tokenMap = MetaInstagramTokens::resolved();

        if ($tokenMap === []) {
            Log::warning('Meta Instagram inbox sync skipped: no tokens (META_INSTAGRAM_LOGIN_TOKENS kosong?)');

            return [
                'imported' => 0,
                'accounts' => [[
                    'error' => 'Tidak ada token Instagram. Isi META_INSTAGRAM_LOGIN_TOKENS atau META_INSTAGRAM_LOGIN_ACCESS_TOKEN + META_INSTAGRAM_LOGIN_DEFAULT_ID lalu php artisan config:clear',
                    'imported' => 0,
                ]],
            ];
        }

        foreach ($tokenMap as $igId => $token) {
            try {
                $result = $this->syncAccount((string) $igId, $token, $verbose, $recentMinutes);
                $imported += $result['imported'];
                $accounts[] = $result;
            } catch (\Throwable $e) {
                Log::error('Meta Instagram inbox sync failed for account', [
                    'ig_id' => $igId,
                    'error' => $e->getMessage(),
                ]);
                $accounts[] = [
                    'ig_id' => $igId,
                    'error' => $e->getMessage(),
                    'imported' => 0,
                ];
            }
        }

        if ($imported > 0 || $recentMinutes !== null) {
            Cache::put('meta_instagram_last_sync_at', now()->toIso8601String(), now()->addDay());
        }

        return ['imported' => $imported, 'accounts' => $accounts];
    }

    public function repairMessageMedia(OmniMessage $message, string $accessToken, string $igProfessionalId): bool
    {
        $metaId = (string) ($message->meta_message_id ?? '');
        if ($metaId === '') {
            return false;
        }

        $version = config('services.meta.instagram_graph_version', 'v25.0');
        $payload = $this->fetchMessagePayloadFromApi($accessToken, $version, $metaId);

        if ($payload === null) {
            return false;
        }

        return $this->maybeEnrichExistingMessageMedia($message->fresh() ?? $message, $payload, $accessToken);
    }

    /**
     * @return array{ig_id: string, imported: int, conversations: int, messages_checked: int, skipped_existing: int, skipped_outbound: int, api_errors: int, username?: string, recent_minutes?: int|null}
     */
    public function syncAccount(string $igProfessionalId, string $accessToken, bool $verbose = false, ?int $recentMinutes = null): array
    {
        return $this->runSyncAccount($igProfessionalId, $accessToken, $verbose, $recentMinutes);
    }

    /**
     * @return array{ig_id: string, imported: int, conversations: int, messages_checked: int, skipped_existing: int, skipped_outbound: int, api_errors: int, username?: string, recent_minutes?: int|null}
     */
    private function runSyncAccount(string $igProfessionalId, string $accessToken, bool $verbose = false, ?int $recentMinutes = null): array
    {
        $version = config('services.meta.instagram_graph_version', 'v25.0');
        $imported = 0;
        $messagesChecked = 0;
        $skippedExisting = 0;
        $skippedOutbound = 0;
        $skippedNoSender = 0;
        $apiErrors = 0;
        $conversationCount = 0;

        $me = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/me", [
                'fields' => 'user_id,username',
            ]);

        $igUsername = '';
        if ($me->successful()) {
            $resolvedId = (string) ($me->json('user_id') ?? '');
            if ($resolvedId !== '') {
                $igProfessionalId = $resolvedId;
            }
            $igUsername = (string) ($me->json('username') ?? '');
            MetaInstagramAccountRegistry::remember($igProfessionalId, $igUsername !== '' ? $igUsername : null);
        }

        $conversationRows = $this->fetchAllConversations($accessToken, $version, $igProfessionalId, $apiErrors);
        $conversationCount = count($conversationRows);

        foreach ($conversationRows as $row) {
            $conversationId = (string) ($row['id'] ?? '');
            if ($conversationId === '') {
                continue;
            }

            $this->syncConversationMessages(
                $accessToken,
                $version,
                $igProfessionalId,
                $conversationId,
                $imported,
                $messagesChecked,
                $skippedExisting,
                $skippedOutbound,
                $skippedNoSender,
                $apiErrors,
                $recentMinutes
            );
        }

        $summary = [
            'ig_id' => $igProfessionalId,
            'username' => $igUsername !== '' ? $igUsername : ($me->successful() ? (string) ($me->json('username') ?? '') : ''),
            'recent_minutes' => $recentMinutes,
            'imported' => $imported,
            'conversations' => $conversationCount,
            'messages_checked' => $messagesChecked,
            'skipped_existing' => $skippedExisting,
            'skipped_outbound' => $skippedOutbound,
            'skipped_no_sender' => $skippedNoSender,
            'api_errors' => $apiErrors,
        ];

        Log::info('Meta Instagram inbox sync finished', $summary);

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $conversationRows
     */
    private function syncConversationMessages(
        string $accessToken,
        string $version,
        string $igProfessionalId,
        string $metaConversationId,
        int &$imported,
        int &$messagesChecked,
        int &$skippedExisting,
        int &$skippedOutbound,
        int &$skippedNoSender,
        int &$apiErrors,
        ?int $recentMinutes = null
    ): void {
        $cutoff = $recentMinutes !== null && $recentMinutes > 0
            ? now()->subMinutes($recentMinutes)
            : null;
        $maxPages = $cutoff !== null ? 1 : self::MAX_MESSAGE_PAGES_PER_CONVERSATION;
        $stopAfterExisting = $cutoff !== null ? 3 : self::STOP_AFTER_CONSECUTIVE_EXISTING;

        $url = "https://graph.instagram.com/{$version}/{$metaConversationId}/messages";
        $query = [
            'fields' => 'id,created_time,from,to,message,attachments{type,mime_type,image_data,file_url,video_data,name}',
            'limit' => self::MESSAGE_PAGE_LIMIT,
        ];
        $pages = 0;
        $consecutiveExisting = 0;

        while ($pages < $maxPages) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                $apiErrors++;
                Log::warning('Instagram messages edge failed, trying legacy fetch', [
                    'conversation' => $metaConversationId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 400),
                ]);
                $this->syncConversationMessagesLegacy(
                    $accessToken,
                    $version,
                    $igProfessionalId,
                    $metaConversationId,
                    $imported,
                    $messagesChecked,
                    $skippedExisting,
                    $skippedOutbound,
                    $skippedNoSender,
                    $apiErrors,
                    $recentMinutes
                );

                return;
            }

            $rows = $this->orderMessagesNewestFirst($response->json('data') ?? []);

            if ($rows === [] && $pages === 0) {
                $this->syncConversationMessagesLegacy(
                    $accessToken,
                    $version,
                    $igProfessionalId,
                    $metaConversationId,
                    $imported,
                    $messagesChecked,
                    $skippedExisting,
                    $skippedOutbound,
                    $skippedNoSender,
                    $apiErrors,
                    $recentMinutes
                );

                return;
            }

            foreach ($rows as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                $messageId = (string) ($payload['id'] ?? '');
                if ($messageId === '') {
                    continue;
                }

                $messagesChecked++;

                if ($this->isMessageOlderThanCutoff($payload, $cutoff)) {
                    $skippedExisting++;
                    $consecutiveExisting++;
                    if ($consecutiveExisting >= $stopAfterExisting) {
                        return;
                    }

                    continue;
                }

                $existing = OmniMessage::query()->where('meta_message_id', $messageId)->first();
                if ($existing) {
                    $fullPayload = $this->resolveMessagePayload($accessToken, $version, $payload) ?? $payload;
                    if ($this->maybeEnrichExistingMessageMedia($existing, $fullPayload, $accessToken)) {
                        $imported++;
                    }
                    $skippedExisting++;
                    $consecutiveExisting++;
                    if ($consecutiveExisting >= $stopAfterExisting) {
                        return;
                    }

                    continue;
                }

                $consecutiveExisting = 0;

                $payload = $this->resolveMessagePayload($accessToken, $version, $payload) ?? $payload;

                if ($this->isOutboundFromBusiness($payload, $igProfessionalId)) {
                    $skippedOutbound++;

                    continue;
                }

                $senderId = (string) ($payload['from']['id'] ?? '');
                if ($senderId === '') {
                    $skippedNoSender++;

                    continue;
                }

                if ($this->storeInboundFromApi($igProfessionalId, $senderId, $messageId, $payload, $accessToken)) {
                    $imported++;
                }
            }

            $nextUrl = $response->json('paging.next');
            if (! is_string($nextUrl) || $nextUrl === '') {
                break;
            }

            $url = $nextUrl;
            $query = [];
            $pages++;
        }
    }

    /**
     * Cadangan: GET conversation?fields=messages lalu fetch tiap message id (lebih lambat, lebih kompatibel).
     */
    private function syncConversationMessagesLegacy(
        string $accessToken,
        string $version,
        string $igProfessionalId,
        string $metaConversationId,
        int &$imported,
        int &$messagesChecked,
        int &$skippedExisting,
        int &$skippedOutbound,
        int &$skippedNoSender,
        int &$apiErrors,
        ?int $recentMinutes = null
    ): void {
        $cutoff = $recentMinutes !== null && $recentMinutes > 0
            ? now()->subMinutes($recentMinutes)
            : null;
        $stopAfterExisting = $cutoff !== null ? 3 : self::STOP_AFTER_CONSECUTIVE_EXISTING;

        $detail = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$metaConversationId}", [
                'fields' => 'messages',
            ]);

        if (! $detail->successful()) {
            $apiErrors++;

            return;
        }

        $messageRows = $this->orderMessagesNewestFirst($detail->json('messages.data') ?? []);
        $consecutiveExisting = 0;

        foreach ($messageRows as $msgRow) {
            if (! is_array($msgRow)) {
                continue;
            }

            $messageId = (string) ($msgRow['id'] ?? '');
            if ($messageId === '') {
                continue;
            }

            $messagesChecked++;

            if ($this->isMessageOlderThanCutoff($msgRow, $cutoff)) {
                $skippedExisting++;
                $consecutiveExisting++;
                if ($consecutiveExisting >= $stopAfterExisting) {
                    return;
                }

                continue;
            }

            $existing = OmniMessage::query()->where('meta_message_id', $messageId)->first();
            if ($existing) {
                $payload = $this->resolveMessagePayload($accessToken, $version, $msgRow);
                if ($payload && $this->maybeEnrichExistingMessageMedia($existing, $payload, $accessToken)) {
                    $imported++;
                }
                $skippedExisting++;
                $consecutiveExisting++;
                if ($consecutiveExisting >= $stopAfterExisting) {
                    return;
                }

                continue;
            }

            $consecutiveExisting = 0;

            $payload = $this->resolveMessagePayload($accessToken, $version, $msgRow);
            if ($payload === null) {
                $apiErrors++;

                continue;
            }

            if ($this->isOutboundFromBusiness($payload, $igProfessionalId)) {
                $skippedOutbound++;

                continue;
            }

            $senderId = (string) ($payload['from']['id'] ?? '');
            if ($senderId === '') {
                $skippedNoSender++;

                continue;
            }

            if ($this->storeInboundFromApi($igProfessionalId, $senderId, $messageId, $payload, $accessToken)) {
                $imported++;
            }
        }
    }

    /**
     * Selalu ambil detail + edge attachments (pesan gambar IG punya message kosong).
     *
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>|null
     */
    private function resolveMessagePayload(string $accessToken, string $version, array $partial): ?array
    {
        $messageId = (string) ($partial['id'] ?? '');
        if ($messageId === '') {
            return null;
        }

        return $this->fetchMessagePayloadFromApi($accessToken, $version, $messageId) ?? $partial;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchMessagePayloadFromApi(string $accessToken, string $version, string $messageId): ?array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$messageId}", [
                'fields' => 'id,created_time,from,to,message',
            ]);

        $data = $response->successful() ? $response->json() : [];
        if (! is_array($data)) {
            $data = [];
        }

        $attResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$messageId}/attachments", [
                'fields' => 'type,mime_type,image_data,file_url,video_data,name,payload',
            ]);

        if ($attResponse->successful()) {
            $rows = $attResponse->json('data') ?? [];
            if (is_array($rows) && $rows !== []) {
                $data['attachments'] = ['data' => $rows];
            }
        }

        if (! isset($data['attachments']) || empty($data['attachments']['data'])) {
            $nested = Http::withToken($accessToken)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/{$messageId}", [
                    'fields' => 'attachments{type,mime_type,image_data,file_url,video_data,name,payload}',
                ]);
            if ($nested->successful()) {
                $extra = $nested->json('attachments') ?? null;
                if (is_array($extra)) {
                    $data['attachments'] = $extra;
                }
            }
        }

        return $data !== [] ? $data : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllConversations(
        string $accessToken,
        string $version,
        string $igProfessionalId,
        int &$apiErrors
    ): array {
        $all = [];
        $url = "https://graph.instagram.com/{$version}/me/conversations";
        $query = ['platform' => 'instagram', 'limit' => 50];
        $pages = 0;

        while ($pages < 3) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->get("https://graph.instagram.com/{$version}/{$igProfessionalId}/conversations", $query);
            }

            if (! $response->successful()) {
                $apiErrors++;
                throw new \RuntimeException('List conversations failed: '.$response->body());
            }

            foreach ($response->json('data') ?? [] as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $nextUrl = $response->json('paging.next');
            if (! is_string($nextUrl) || $nextUrl === '') {
                break;
            }

            $url = $nextUrl;
            $query = [];
            $pages++;
        }

        return $all;
    }

    /**
     * API sering mengembalikan pesan lama dulu — urutkan terbaru di atas.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function orderMessagesNewestFirst(array $rows): array
    {
        if (count($rows) < 2) {
            return $rows;
        }

        $first = $this->messageTimestamp($rows[0]);
        $last = $this->messageTimestamp($rows[count($rows) - 1]);

        if ($first !== null && $last !== null && $first < $last) {
            return array_reverse($rows);
        }

        return $rows;
    }

    private function isMessageOlderThanCutoff(array $payload, ?Carbon $cutoff): bool
    {
        if ($cutoff === null) {
            return false;
        }

        $createdRaw = (string) ($payload['created_time'] ?? '');
        if ($createdRaw === '') {
            return false;
        }

        try {
            return Carbon::parse($createdRaw)->timezone(config('app.timezone'))->lt($cutoff);
        } catch (\Throwable) {
            return false;
        }
    }

    private function messageTimestamp(array $row): ?int
    {
        $created = (string) ($row['created_time'] ?? '');
        if ($created === '') {
            return null;
        }

        try {
            return Carbon::parse($created)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isOutboundFromBusiness(array $payload, string $igProfessionalId): bool
    {
        $fromId = (string) ($payload['from']['id'] ?? '');
        if ($fromId === '') {
            return false;
        }

        if ($fromId === $igProfessionalId) {
            return true;
        }

        $toData = $payload['to']['data'] ?? null;
        if (is_array($toData)) {
            foreach ($toData as $recipient) {
                if (is_array($recipient) && (string) ($recipient['id'] ?? '') === $igProfessionalId) {
                    return false;
                }
            }
        }

        if ((string) ($payload['to']['id'] ?? '') === $igProfessionalId) {
            return false;
        }

        return false;
    }

    private function maybeEnrichExistingMessageMedia(OmniMessage $message, array $payload, ?string $accessToken = null): bool
    {
        $currentPayload = is_array($message->payload) ? $message->payload : [];
        if (! empty($currentPayload['local_media_path'])) {
            return false;
        }

        $normalized = OmniMetaMessagePayload::normalize($payload);
        $hadMetaUrl = OmniMetaMessagePayload::extractAttachmentUrl($currentPayload) !== null;
        if ($normalized['attachment_url'] === null && ! $hadMetaUrl) {
            // Pesan gambar IG: message kosong, tandai supaya UI tidak bubble kosong
            if (array_key_exists('message', $payload) && ($payload['message'] === '' || $payload['message'] === null)) {
                $message->update([
                    'message_type' => 'image',
                    'body' => '[Gambar]',
                ]);

                return true;
            }

            return false;
        }

        $attachmentUrl = $normalized['attachment_url'] ?? OmniMetaMessagePayload::extractAttachmentUrl($currentPayload);

        $merged = array_merge($currentPayload, $payload, [
            'attachment_url' => $attachmentUrl,
        ]);
        if ($normalized['media_mime'] !== null) {
            $merged['media_mime'] = $normalized['media_mime'];
        }
        if ($normalized['media_filename'] !== null) {
            $merged['media_filename'] = $normalized['media_filename'];
        }

        $localPath = $this->cacheInboundMediaLocally(
            $attachmentUrl,
            (int) $message->conversation_id,
            (string) ($message->meta_message_id ?? $message->id),
            $normalized['message_type'] !== 'text' ? $normalized['message_type'] : 'image',
            $accessToken
        );
        if ($localPath !== null) {
            $merged['local_media_path'] = $localPath;
        }

        $updates = ['payload' => $merged];
        if (($message->message_type === 'text' || $message->message_type === null || $message->message_type === '')
            && $normalized['message_type'] !== 'text') {
            $updates['message_type'] = $normalized['message_type'];
        }
        if (! $message->body && $normalized['body']) {
            $updates['body'] = $normalized['body'];
        } elseif (! $message->body && $localPath !== null) {
            $updates['body'] = '[Gambar]';
        }

        $message->update($updates);

        return true;
    }

    private function cacheInboundMediaLocally(
        ?string $remoteUrl,
        int $conversationId,
        string $metaMessageId,
        string $messageType,
        ?string $accessToken = null
    ): ?string {
        if ($remoteUrl === null || $remoteUrl === '' || $conversationId <= 0) {
            return null;
        }

        try {
            $response = $this->downloadInstagramMedia($remoteUrl, $accessToken);
            if ($response === null) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'pdf') => 'pdf',
                str_contains($contentType, 'webp') => 'webp',
                default => 'jpg',
            };

            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $metaMessageId) ?: 'msg';
            $path = "omni-inbound/{$conversationId}/{$safeId}.{$ext}";
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Instagram inbound media cache failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function downloadInstagramMedia(string $remoteUrl, ?string $accessToken): ?\Illuminate\Http\Client\Response
    {
        $url = $remoteUrl;
        $host = strtolower((string) parse_url($remoteUrl, PHP_URL_HOST));
        $needsAuth = str_contains($host, 'fbcdn.net')
            || str_contains($host, 'facebook.com')
            || str_contains($host, 'instagram.com')
            || str_contains($host, 'fbsbx.com');

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

    private function storeInboundFromApi(
        string $igProfessionalId,
        string $senderIgsid,
        string $metaMessageId,
        array $payload,
        ?string $accessToken = null
    ): bool {
        if ($senderIgsid === '') {
            return false;
        }

        $normalized = OmniMetaMessagePayload::normalize($payload);
        $body = $normalized['body'];
        $messageType = $normalized['message_type'];

        if ($normalized['attachment_url'] !== null) {
            $payload['attachment_url'] = $normalized['attachment_url'];
        }
        if ($normalized['media_mime'] !== null) {
            $payload['media_mime'] = $normalized['media_mime'];
        }
        if ($normalized['media_filename'] !== null) {
            $payload['media_filename'] = $normalized['media_filename'];
        }

        $created = (string) ($payload['created_time'] ?? '');
        $sentAt = $created !== ''
            ? Carbon::parse($created)->timezone(config('app.timezone'))
            : now();

        $existingGlobal = OmniMessage::query()->where('meta_message_id', $metaMessageId)->first();
        if ($existingGlobal) {
            $this->maybeEnrichExistingMessageMedia($existingGlobal, $payload);

            return false;
        }

        $contactKey = "instagram_{$senderIgsid}";
        $conversationId = null;
        $inboundMessageId = null;
        $wasNew = false;

        try {
            DB::transaction(function () use (
                $igProfessionalId,
                $senderIgsid,
                $contactKey,
                $metaMessageId,
                $body,
                $messageType,
                $payload,
                $sentAt,
                &$conversationId,
                &$inboundMessageId,
                &$wasNew
            ) {
                $contact = OmniContact::query()->firstOrCreate(
                    ['phone_normalized' => $contactKey],
                    ['display_name' => (string) ($payload['from']['username'] ?? null)]
                );

                $conversation = OmniConversation::query()->firstOrCreate(
                    [
                        'channel' => 'instagram',
                        'external_contact_id' => $senderIgsid,
                        'phone_number_id' => $igProfessionalId,
                    ],
                    [
                        'omni_contact_id' => $contact->id,
                        'status' => 'open',
                    ]
                );

                $conversation->omni_contact_id = $contact->id;

                $nearDup = OmniMetaMessageId::findNearDuplicateInbound(
                    (int) $conversation->id,
                    $body,
                    $sentAt
                );
                if ($nearDup !== null) {
                    return;
                }

                $inbound = OmniMessage::query()->firstOrCreate(
                    ['meta_message_id' => $metaMessageId],
                    [
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'message_type' => $messageType,
                        'body' => $body,
                        'payload' => $payload,
                        'status' => 'received',
                        'sent_at' => $sentAt,
                    ]
                );

                $wasNew = $inbound->wasRecentlyCreated;
                if (! $wasNew) {
                    return;
                }

                $conversationId = (int) $conversation->id;
                $inboundMessageId = (int) $inbound->id;

                $conversation->last_message_at = $sentAt;
                $conversation->last_customer_message_at = $sentAt;
                $conversation->last_message_preview = mb_substr($body ?: '[pesan]', 0, 500);
                $conversation->unread_count = (int) $conversation->unread_count + 1;
                $conversation->save();
            });
        } catch (UniqueConstraintViolationException $e) {
            $dup = OmniMessage::query()->where('meta_message_id', $metaMessageId)->first();
            if ($dup) {
                $this->maybeEnrichExistingMessageMedia($dup, $payload);
            }

            Log::debug('Instagram sync duplicate meta_message_id skipped', [
                'meta_message_id' => mb_substr($metaMessageId, 0, 80),
            ]);

            return false;
        }

        if (! $wasNew) {
            return false;
        }

        if ($conversationId && $inboundMessageId && $normalized['attachment_url'] !== null) {
            $localPath = $this->cacheInboundMediaLocally(
                $normalized['attachment_url'],
                $conversationId,
                $metaMessageId,
                $messageType,
                $accessToken
            );
            if ($localPath !== null) {
                $msg = OmniMessage::query()->find($inboundMessageId);
                if ($msg) {
                    $mergedPayload = is_array($msg->payload) ? $msg->payload : [];
                    $mergedPayload['local_media_path'] = $localPath;
                    $msg->update(['payload' => $mergedPayload]);
                }
            }
        }

        if ($conversationId && $inboundMessageId) {
            $within = (int) config('omnichannel.instagram_sync_notify_within_minutes', 30);
            if ($sentAt->gte(now()->subMinutes($within))) {
                NotifyOmniInboundMessageJob::dispatch($conversationId, $inboundMessageId);
                ProcessOmniFlowJob::dispatch($conversationId, $inboundMessageId);
            }
        }

        $conversation = OmniConversation::query()->find($conversationId);
        $contact = $conversation?->omniContact;
        if ($conversation && $contact && (! $conversation->contact_name || ! $contact->avatar_url)) {
            app(MetaInstagramProfileService::class)->enrichContactAndConversation(
                $contact,
                $conversation,
                $senderIgsid,
                $igProfessionalId,
                isset($payload['from']['username']) ? (string) $payload['from']['username'] : null
            );
        }

        return true;
    }
}
