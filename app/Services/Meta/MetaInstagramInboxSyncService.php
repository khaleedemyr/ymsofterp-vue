<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\MetaInstagramAccountRegistry;
use App\Support\MetaInstagramTokens;
use App\Support\OmniMetaMessagePayload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function syncAll(bool $verbose = false): array
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
                $result = $this->syncAccount((string) $igId, $token, $verbose);
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

        return ['imported' => $imported, 'accounts' => $accounts];
    }

    /**
     * @return array{ig_id: string, imported: int, conversations: int, messages_checked: int, skipped_existing: int, skipped_outbound: int, api_errors: int, username?: string}
     */
    public function syncAccount(string $igProfessionalId, string $accessToken, bool $verbose = false): array
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
                $apiErrors
            );
        }

        $summary = [
            'ig_id' => $igProfessionalId,
            'username' => $igUsername !== '' ? $igUsername : ($me->successful() ? (string) ($me->json('username') ?? '') : ''),
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
        int &$apiErrors
    ): void {
        $url = "https://graph.instagram.com/{$version}/{$metaConversationId}/messages";
        $query = [
            'fields' => 'id,created_time,from,to,message,attachments{type,mime_type,image_data,file_url,video_data,name}',
            'limit' => self::MESSAGE_PAGE_LIMIT,
        ];
        $pages = 0;
        $consecutiveExisting = 0;

        while ($pages < self::MAX_MESSAGE_PAGES_PER_CONVERSATION) {
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
                    $apiErrors
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
                    $apiErrors
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

                $existing = OmniMessage::query()->where('meta_message_id', $messageId)->first();
                if ($existing) {
                    $fullPayload = $this->resolveMessagePayload($accessToken, $version, $payload) ?? $payload;
                    if ($this->maybeEnrichExistingMessageMedia($existing, $fullPayload)) {
                        $imported++;
                    }
                    $skippedExisting++;
                    $consecutiveExisting++;
                    if ($consecutiveExisting >= self::STOP_AFTER_CONSECUTIVE_EXISTING) {
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

                if ($this->storeInboundFromApi($igProfessionalId, $senderId, $messageId, $payload)) {
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
        int &$apiErrors
    ): void {
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

            $existing = OmniMessage::query()->where('meta_message_id', $messageId)->first();
            if ($existing) {
                $payload = $this->resolveMessagePayload($accessToken, $version, $msgRow);
                if ($payload && $this->maybeEnrichExistingMessageMedia($existing, $payload)) {
                    $imported++;
                }
                $skippedExisting++;
                $consecutiveExisting++;
                if ($consecutiveExisting >= self::STOP_AFTER_CONSECUTIVE_EXISTING) {
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

            if ($this->storeInboundFromApi($igProfessionalId, $senderId, $messageId, $payload)) {
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
        $fromId = (string) ($partial['from']['id'] ?? '');
        $hasBody = array_key_exists('message', $partial);

        if ($fromId !== '' && $hasBody) {
            return $partial;
        }

        $messageId = (string) ($partial['id'] ?? '');
        if ($messageId === '') {
            return null;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$messageId}", [
                'fields' => 'id,created_time,from,to,message,attachments{type,mime_type,image_data,file_url,video_data,name}',
            ]);

        return $response->successful() ? $response->json() : null;
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

    private function maybeEnrichExistingMessageMedia(OmniMessage $message, array $payload): bool
    {
        $currentPayload = is_array($message->payload) ? $message->payload : [];
        if (OmniMetaMessagePayload::extractAttachmentUrl($currentPayload) !== null) {
            return false;
        }

        $normalized = OmniMetaMessagePayload::normalize($payload);
        if ($normalized['attachment_url'] === null) {
            return false;
        }

        $merged = array_merge($currentPayload, $payload, [
            'attachment_url' => $normalized['attachment_url'],
        ]);
        if ($normalized['media_mime'] !== null) {
            $merged['media_mime'] = $normalized['media_mime'];
        }
        if ($normalized['media_filename'] !== null) {
            $merged['media_filename'] = $normalized['media_filename'];
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

    private function storeInboundFromApi(
        string $igProfessionalId,
        string $senderIgsid,
        string $metaMessageId,
        array $payload
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

        $contactKey = "instagram_{$senderIgsid}";
        $conversationId = null;
        $inboundMessageId = null;

        DB::transaction(function () use (
            $igProfessionalId,
            $senderIgsid,
            $contactKey,
            $metaMessageId,
            $body,
            $payload,
            $sentAt,
            &$conversationId,
            &$inboundMessageId
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

            $inbound = OmniMessage::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'meta_message_id' => $metaMessageId,
                'message_type' => $messageType,
                'body' => $body,
                'payload' => $payload,
                'status' => 'received',
                'sent_at' => $sentAt,
            ]);

            $conversationId = (int) $conversation->id;
            $inboundMessageId = (int) $inbound->id;

            $conversation->last_message_at = $sentAt;
            $conversation->last_customer_message_at = $sentAt;
            $conversation->last_message_preview = mb_substr($body ?: '[pesan]', 0, 500);
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();
        });

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
