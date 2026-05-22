<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Services\Omni\OmnichannelInboxMediaService;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\OmniMetaMessageId;
use App\Support\OmniMetaMessagePayload;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook pesan masuk Facebook Page (Messenger) & Instagram DM.
 */
class MetaMessengerInboundService
{
    /** @var array<string, true> */
    private array $processedMetaIdsThisRequest = [];

    public function processPayload(array $payload): void
    {
        $object = (string) ($payload['object'] ?? '');
        $entryCount = count($payload['entry'] ?? []);

        Log::info('Meta Messenger/Instagram webhook payload', [
            'object' => $object !== '' ? $object : '(empty)',
            'entry_count' => $entryCount,
            'has_sample' => isset($payload['sample']),
        ]);

        // Meta Dashboard → Webhooks → "Send to My Server" (bukan DM live)
        if (isset($payload['sample']) && is_array($payload['sample'])) {
            $this->processMessagesChangeValue($payload['sample']['value'] ?? [], 'instagram', 'test');
            Log::info('Meta webhook processed dashboard test sample');

            return;
        }

        if ($object !== 'page' && $object !== 'instagram') {
            Log::warning('Meta webhook ignored: unsupported object', [
                'object' => $object,
                'keys' => array_keys($payload),
            ]);

            return;
        }

        if ($object === 'page') {
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processPageEntry($entry, 'messenger');
            }

            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            $this->processPageEntry($entry, 'instagram');
        }
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     */
    private function processPageEntry(array $entry, string $channel): void
    {
        $pageOrIgId = (string) ($entry['id'] ?? '');

        $events = $entry['messaging'] ?? [];
        foreach ($events as $event) {
            $this->processMessagingEvent($channel, $pageOrIgId, $event);
        }

        // Instagram Platform / beberapa konfigurasi webhook memakai entry.changes[]
        foreach ($entry['changes'] ?? [] as $change) {
            if (! is_array($change) || ($change['field'] ?? '') !== 'messages') {
                continue;
            }
            $value = $change['value'] ?? [];
            if (is_array($value)) {
                $this->processMessagesChangeValue($value, $channel, $pageOrIgId);
            }
        }

        if ($events === [] && ($entry['changes'] ?? []) === []) {
            Log::warning('Meta webhook entry without messaging/changes (no DM stored)', [
                'channel' => $channel,
                'entry_id' => $pageOrIgId,
                'entry_keys' => array_keys($entry),
            ]);
        }
    }

    /**
     * Normalisasi event messages (Messenger API atau Instagram changes.value).
     *
     * @param  'messenger'|'instagram'  $channel
     */
    private function processMessagesChangeValue(array $value, string $channel, string $pageOrIgId): void
    {
        if (isset($value['messaging']) && is_array($value['messaging'])) {
            foreach ($value['messaging'] as $event) {
                $this->processMessagingEvent($channel, $pageOrIgId, $event);
            }

            return;
        }

        if (isset($value['message']) && is_array($value['message'])) {
            $this->processMessagingEvent($channel, $pageOrIgId, [
                'sender' => $value['sender'] ?? [],
                'recipient' => $value['recipient'] ?? [],
                'timestamp' => $value['timestamp'] ?? null,
                'message' => $value['message'],
            ]);
        }
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     */
    private function processMessagingEvent(string $channel, string $pageOrIgId, array $event): void
    {
        if (! isset($event['message']) || ! is_array($event['message'])) {
            return;
        }

        if (! empty($event['message']['is_echo']) || ! empty($event['is_self'])) {
            return;
        }

        $this->storeInboundMessage($channel, $pageOrIgId, $event);
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     */
    private function storeInboundMessage(string $channel, string $pageOrIgId, array $event): void
    {
        $message = $event['message'];
        $aliases = OmniMetaMessageId::aliasesFromWebhook($message, $event);
        $metaMessageId = OmniMetaMessageId::canonical($aliases);
        if ($metaMessageId === '') {
            Log::warning('Meta inbound skipped: missing message mid', [
                'channel' => $channel,
                'entry_id' => $pageOrIgId,
            ]);

            return;
        }

        if (isset($this->processedMetaIdsThisRequest[$metaMessageId])) {
            return;
        }

        if (OmniMetaMessageId::existsAny($aliases)) {
            $this->processedMetaIdsThisRequest[$metaMessageId] = true;

            return;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        if ($senderId === '') {
            return;
        }

        $body = $this->extractMessageBody($message);
        $messageType = $this->detectMessageType($message);
        $normalized = OmniMetaMessagePayload::normalize($message);
        if ($normalized['body'] !== null) {
            $body = $normalized['body'];
        }
        if ($normalized['message_type'] !== 'text') {
            $messageType = $normalized['message_type'];
        }
        $message = array_merge($message, array_filter([
            'attachment_url' => $normalized['attachment_url'],
            'media_mime' => $normalized['media_mime'],
            'media_filename' => $normalized['media_filename'],
        ], fn ($v) => $v !== null));
        $tsMs = (int) ($event['timestamp'] ?? 0);
        $sentAt = $tsMs > 0
            ? Carbon::createFromTimestamp((int) floor($tsMs / 1000), 'UTC')->timezone(config('app.timezone'))
            : now();

        $contactKey = "{$channel}_{$senderId}";

        $conversationId = null;
        $inboundMessageId = null;

        try {
            DB::transaction(function () use (
                $channel,
                $pageOrIgId,
                $senderId,
                $contactKey,
                $metaMessageId,
                $messageType,
                $body,
                $message,
                $sentAt,
                &$conversationId,
                &$inboundMessageId
            ) {
                $contact = OmniContact::query()->firstOrCreate(
                    ['phone_normalized' => $contactKey],
                    ['display_name' => null]
                );

                $conversation = OmniConversation::query()->firstOrCreate(
                    [
                        'channel' => $channel,
                        'external_contact_id' => $senderId,
                        'phone_number_id' => $pageOrIgId !== '' ? $pageOrIgId : null,
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

                $inbound = OmniMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'direction' => 'inbound',
                    'meta_message_id' => $metaMessageId,
                    'message_type' => $messageType,
                    'body' => $body,
                    'payload' => $message,
                    'status' => 'received',
                    'sent_at' => $sentAt,
                ]);

                $conversationId = (int) $conversation->id;
                $inboundMessageId = (int) $inbound->id;

                $conversation->last_message_at = $sentAt;
                $conversation->last_customer_message_at = $sentAt;
                $conversation->last_message_preview = mb_substr($body ?: '['.$messageType.']', 0, 500);
                $conversation->unread_count = (int) $conversation->unread_count + 1;
                $conversation->save();
            });
        } catch (UniqueConstraintViolationException $e) {
            Log::debug('Meta inbound webhook duplicate meta_message_id skipped', [
                'meta_message_id' => mb_substr($metaMessageId, 0, 80),
                'channel' => $channel,
            ]);
            $this->processedMetaIdsThisRequest[$metaMessageId] = true;

            return;
        }

        $this->processedMetaIdsThisRequest[$metaMessageId] = true;

        if ($conversationId && $inboundMessageId) {
            $conversation = OmniConversation::query()->find($conversationId);
            $inbound = OmniMessage::query()->find($inboundMessageId);
            if ($conversation && $inbound && ! OmniMetaMessagePayload::isEphemeralOnly($message)) {
                app(OmnichannelInboxMediaService::class)->ensureCached($inbound, $conversation);
            }

            NotifyOmniInboundMessageJob::dispatch($conversationId, $inboundMessageId);
            ProcessOmniFlowJob::dispatch($conversationId, $inboundMessageId);
        }

        if ($conversationId) {
            $conversation = OmniConversation::query()->with('omniContact')->find($conversationId);
            $contact = $conversation?->omniContact;
            if ($conversation && $contact && (! $conversation->contact_name || ! $contact->avatar_url)) {
                if ($channel === 'instagram') {
                    app(MetaInstagramProfileService::class)->enrichContactAndConversation(
                        $contact,
                        $conversation,
                        $senderId,
                        $pageOrIgId
                    );
                } elseif ($channel === 'messenger' && $pageOrIgId !== '') {
                    $fallbackName = (string) ($event['sender']['name'] ?? '');
                    app(MetaMessengerProfileService::class)->enrichContactAndConversation(
                        $contact,
                        $conversation,
                        $senderId,
                        $pageOrIgId,
                        $fallbackName !== '' ? $fallbackName : null
                    );
                }
            }
        }

        Log::info('Meta Messenger/Instagram inbound stored', [
            'channel' => $channel,
            'conversation_id' => $conversationId,
            'message_id' => $inboundMessageId,
        ]);
    }

    private function extractMessageBody(array $message): ?string
    {
        if (isset($message['text']) && is_string($message['text'])) {
            return $message['text'];
        }

        $attachments = $message['attachments'] ?? [];
        if (! is_array($attachments) || $attachments === []) {
            return null;
        }

        $first = $attachments[0] ?? [];
        $type = (string) ($first['type'] ?? 'attachment');

        return match ($type) {
            'image' => '[Gambar]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'file' => (string) ($first['payload']['url'] ?? '[Berkas]'),
            'location' => '[Lokasi]',
            'ephemeral' => '[Media sekali lihat — tidak dapat ditampilkan di inbox]',
            'fallback' => (string) ($first['title'] ?? '[Lampiran]'),
            default => '['.$type.']',
        };
    }

    private function detectMessageType(array $message): string
    {
        if (isset($message['text'])) {
            return 'text';
        }

        $type = (string) (($message['attachments'][0]['type'] ?? '') ?: 'attachment');

        return $type !== '' ? $type : 'text';
    }
}
