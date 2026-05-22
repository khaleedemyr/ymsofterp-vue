<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\MetaPageAccountRegistry;
use App\Support\MetaPageTokens;
use App\Support\OmniMetaMessagePayload;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sinkron DM Facebook Messenger via Page Conversations API (polling, cadangan webhook).
 */
class MetaMessengerInboxSyncService
{
    private const MESSAGE_PAGE_LIMIT = 25;

    private const MAX_MESSAGE_PAGES_PER_CONVERSATION = 4;

    private const STOP_AFTER_CONSECUTIVE_EXISTING = 8;

    /**
     * @return array{imported: int, accounts: list<array<string, mixed>>}
     */
    public function syncAll(bool $verbose = false, ?int $recentMinutes = null): array
    {
        $imported = 0;
        $accounts = [];
        $tokenMap = $this->resolvePageTokenMap();

        if ($tokenMap === []) {
            Log::warning('Meta Messenger inbox sync skipped: no page tokens');

            return [
                'imported' => 0,
                'accounts' => [[
                    'error' => 'Tidak ada Page token. Isi META_PAGE_TOKENS atau META_PAGE_ACCESS_TOKEN + META_PAGE_ID lalu php artisan config:clear',
                    'imported' => 0,
                ]],
            ];
        }

        foreach ($tokenMap as $pageId => $token) {
            try {
                $result = $this->syncPage((string) $pageId, $token, $verbose, $recentMinutes);
                $imported += $result['imported'];
                $accounts[] = $result;
            } catch (\Throwable $e) {
                Log::error('Meta Messenger inbox sync failed for page', [
                    'page_id' => $pageId,
                    'error' => $e->getMessage(),
                ]);
                $accounts[] = [
                    'page_id' => $pageId,
                    'error' => $e->getMessage(),
                    'imported' => 0,
                ];
            }
        }

        if ($imported > 0 || $recentMinutes !== null) {
            Cache::put('meta_messenger_last_sync_at', now()->toIso8601String(), now()->addDay());
        }

        return ['imported' => $imported, 'accounts' => $accounts];
    }

    /**
     * @return array<string, string> page_id => page_access_token
     */
    private function resolvePageTokenMap(): array
    {
        $raw = MetaPageTokens::resolved();
        $tokens = [];
        foreach ($raw as $pageId => $token) {
            $pageId = (string) $pageId;
            if ($this->isLikelyNonPageKey($pageId)) {
                Log::info('Meta Messenger sync: skip non-Page key in META_PAGE_TOKENS', [
                    'key' => $pageId,
                    'hint' => 'Pindahkan ke META_INSTAGRAM_LOGIN_TOKENS jika ini akun IG',
                ]);

                continue;
            }
            $tokens[$pageId] = $token;
        }

        if ($tokens !== []) {
            return $tokens;
        }

        $token = (string) config('services.meta.page_access_token', '');
        $pageId = (string) config('services.meta.page_id', '');
        if ($token !== '' && $pageId !== '' && ! $this->isLikelyNonPageKey($pageId)) {
            return [$pageId => $token];
        }

        return [];
    }

    /** IG professional id & key non-Page yang sering tercampur di META_PAGE_TOKENS. */
    private function isLikelyNonPageKey(string $key): bool
    {
        if ($key === '') {
            return true;
        }

        if (preg_match('/^178414\d+$/', $key)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{page_id: string, imported: int, conversations: int, messages_checked: int, skipped_existing: int, skipped_outbound: int, api_errors: int, page_name?: string, recent_minutes?: int|null, error?: string}
     */
    public function syncPage(string $pageId, string $accessToken, bool $verbose = false, ?int $recentMinutes = null): array
    {
        $version = config('services.meta.graph_api_version', 'v25.0');
        $imported = 0;
        $messagesChecked = 0;
        $skippedExisting = 0;
        $skippedOutbound = 0;
        $skippedNoSender = 0;
        $apiErrors = 0;

        $me = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/me", [
                'fields' => 'id,name',
            ]);

        if (! $me->successful()) {
            Log::warning('Meta Messenger sync skipped: bukan Page token atau permission kurang', [
                'configured_key' => $pageId,
                'status' => $me->status(),
                'body' => mb_substr($me->body(), 0, 400),
            ]);

            return [
                'page_id' => $pageId,
                'imported' => 0,
                'conversations' => 0,
                'messages_checked' => 0,
                'skipped_existing' => 0,
                'skipped_outbound' => 0,
                'skipped_no_sender' => 0,
                'api_errors' => 0,
                'skipped_invalid_token' => true,
                'error' => 'Bukan Page token — pindahkan ke META_INSTAGRAM_LOGIN_TOKENS atau hapus dari META_PAGE_TOKENS',
            ];
        }

        $pageName = '';
        $resolvedId = (string) ($me->json('id') ?? '');
        if ($resolvedId !== '') {
            $pageId = $resolvedId;
        }
        $pageName = (string) ($me->json('name') ?? '');
        MetaPageAccountRegistry::remember($pageId, $pageName !== '' ? $pageName : null);

        $conversationRows = $this->fetchAllConversations($accessToken, $version, $pageId, $apiErrors);
        $conversationCount = count($conversationRows);

        foreach ($conversationRows as $row) {
            $conversationId = (string) ($row['id'] ?? '');
            if ($conversationId === '') {
                continue;
            }

            $this->syncConversationMessages(
                $accessToken,
                $version,
                $pageId,
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
            'page_id' => $pageId,
            'page_name' => $pageName,
            'recent_minutes' => $recentMinutes,
            'imported' => $imported,
            'conversations' => $conversationCount,
            'messages_checked' => $messagesChecked,
            'skipped_existing' => $skippedExisting,
            'skipped_outbound' => $skippedOutbound,
            'skipped_no_sender' => $skippedNoSender,
            'api_errors' => $apiErrors,
        ];

        Log::info('Meta Messenger inbox sync finished', $summary);

        return $summary;
    }

    private function syncConversationMessages(
        string $accessToken,
        string $version,
        string $pageId,
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

        $url = "https://graph.facebook.com/{$version}/{$metaConversationId}/messages";
        $query = [
            'fields' => 'id,created_time,from,to,message,attachments{type,mime_type,image_data,file_url,video_data,name,payload}',
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
                Log::warning('Messenger messages edge failed, trying legacy', [
                    'conversation' => $metaConversationId,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 400),
                ]);
                $this->syncConversationMessagesLegacy(
                    $accessToken,
                    $version,
                    $pageId,
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
                    $pageId,
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
                    if ($this->maybeEnrichExistingMessageMedia($existing, $fullPayload)) {
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

                if ($this->isOutboundFromPage($payload, $pageId)) {
                    $skippedOutbound++;

                    continue;
                }

                $senderId = (string) ($payload['from']['id'] ?? '');
                if ($senderId === '') {
                    $skippedNoSender++;

                    continue;
                }

                if ($this->storeInboundFromApi($pageId, $senderId, $messageId, $payload)) {
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

    private function syncConversationMessagesLegacy(
        string $accessToken,
        string $version,
        string $pageId,
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
            ->get("https://graph.facebook.com/{$version}/{$metaConversationId}", [
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
                if ($payload && $this->maybeEnrichExistingMessageMedia($existing, $payload)) {
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

            if ($this->isOutboundFromPage($payload, $pageId)) {
                $skippedOutbound++;

                continue;
            }

            $senderId = (string) ($payload['from']['id'] ?? '');
            if ($senderId === '') {
                $skippedNoSender++;

                continue;
            }

            if ($this->storeInboundFromApi($pageId, $senderId, $messageId, $payload)) {
                $imported++;
            }
        }
    }

    /**
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
            ->get("https://graph.facebook.com/{$version}/{$messageId}", [
                'fields' => 'id,created_time,from,to,message,attachments{type,mime_type,image_data,file_url,video_data,name,payload}',
            ]);

        $data = $response->successful() ? $response->json() : [];
        if (! is_array($data) || $data === []) {
            return null;
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllConversations(
        string $accessToken,
        string $version,
        string $pageId,
        int &$apiErrors
    ): array {
        $all = [];
        $url = "https://graph.facebook.com/{$version}/{$pageId}/conversations";
        $query = ['platform' => 'messenger', 'limit' => 50];
        $pages = 0;

        while ($pages < 3) {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                $apiErrors++;
                throw new \RuntimeException('List Messenger conversations failed: '.$response->body());
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

    private function isOutboundFromPage(array $payload, string $pageId): bool
    {
        $fromId = (string) ($payload['from']['id'] ?? '');
        if ($fromId === '') {
            return false;
        }

        return $fromId === $pageId;
    }

    private function maybeEnrichExistingMessageMedia(OmniMessage $message, array $payload): bool
    {
        $currentPayload = is_array($message->payload) ? $message->payload : [];
        if (! empty($currentPayload['local_media_path'])) {
            return false;
        }

        $normalized = OmniMetaMessagePayload::normalize($payload);
        if ($normalized['attachment_url'] === null) {
            return false;
        }

        $attachmentUrl = $normalized['attachment_url'];
        $merged = array_merge($currentPayload, $payload, [
            'attachment_url' => $attachmentUrl,
        ]);

        $localPath = $this->cacheInboundMediaLocally(
            $attachmentUrl,
            (int) $message->conversation_id,
            (string) ($message->meta_message_id ?? $message->id),
            $normalized['message_type'] !== 'text' ? $normalized['message_type'] : 'image'
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
        }

        $message->update($updates);

        return true;
    }

    private function cacheInboundMediaLocally(
        ?string $remoteUrl,
        int $conversationId,
        string $metaMessageId,
        string $messageType
    ): ?string {
        if ($remoteUrl === null || $remoteUrl === '' || $conversationId <= 0) {
            return null;
        }

        try {
            $response = Http::timeout(45)->get($remoteUrl);
            if (! $response->successful()) {
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
            Log::warning('Messenger inbound media cache failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function storeInboundFromApi(
        string $pageId,
        string $senderPsid,
        string $metaMessageId,
        array $payload
    ): bool {
        $normalized = OmniMetaMessagePayload::normalize($payload);
        $body = $normalized['body'];
        $messageType = $normalized['message_type'];

        if ($normalized['attachment_url'] !== null) {
            $payload['attachment_url'] = $normalized['attachment_url'];
        }

        $created = (string) ($payload['created_time'] ?? '');
        $sentAt = $created !== ''
            ? Carbon::parse($created)->timezone(config('app.timezone'))
            : now();

        if (OmniMessage::query()->where('meta_message_id', $metaMessageId)->exists()) {
            return false;
        }

        $fromName = (string) ($payload['from']['name'] ?? '');
        $contactKey = "messenger_{$senderPsid}";
        $conversationId = null;
        $inboundMessageId = null;
        $wasNew = false;

        try {
            DB::transaction(function () use (
                $pageId,
                $senderPsid,
                $fromName,
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
                    ['display_name' => $fromName !== '' ? $fromName : null]
                );

                if ($fromName !== '' && ! $contact->display_name) {
                    $contact->update(['display_name' => $fromName]);
                }

                $conversation = OmniConversation::query()->firstOrCreate(
                    [
                        'channel' => 'messenger',
                        'external_contact_id' => $senderPsid,
                        'phone_number_id' => $pageId,
                    ],
                    [
                        'omni_contact_id' => $contact->id,
                        'status' => 'open',
                    ]
                );

                $conversation->omni_contact_id = $contact->id;
                if ($fromName !== '' && ! $conversation->contact_name) {
                    $conversation->contact_name = $fromName;
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
            Log::debug('Messenger sync duplicate meta_message_id skipped', [
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
                $messageType
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
            $within = (int) config('omnichannel.messenger_sync_notify_within_minutes', 30);
            if ($sentAt->gte(now()->subMinutes($within))) {
                NotifyOmniInboundMessageJob::dispatch($conversationId, $inboundMessageId);
                ProcessOmniFlowJob::dispatch($conversationId, $inboundMessageId);
            }
        }

        $conversation = OmniConversation::query()->with('omniContact')->find($conversationId);
        $contact = $conversation?->omniContact;
        if ($conversation && $contact && (! $conversation->contact_name || ! $contact->avatar_url)) {
            app(MetaMessengerProfileService::class)->enrichContactAndConversation(
                $contact,
                $conversation,
                $senderPsid,
                $pageId,
                $fromName !== '' ? $fromName : (string) ($payload['from']['name'] ?? null)
            );
        }

        return true;
    }
}
