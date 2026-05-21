<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook pesan masuk Facebook Page (Messenger) & Instagram DM.
 */
class MetaMessengerInboundService
{
    public function processPayload(array $payload): void
    {
        $object = (string) ($payload['object'] ?? '');

        if ($object === 'page') {
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processPageEntry($entry, 'messenger');
            }

            return;
        }

        if ($object === 'instagram') {
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processPageEntry($entry, 'instagram');
            }

            return;
        }
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     */
    private function processPageEntry(array $entry, string $channel): void
    {
        $pageOrIgId = (string) ($entry['id'] ?? '');

        foreach ($entry['messaging'] ?? [] as $event) {
            if (! isset($event['message']) || ! is_array($event['message'])) {
                continue;
            }

            if (! empty($event['message']['is_echo'])) {
                continue;
            }

            $this->storeInboundMessage($channel, $pageOrIgId, $event);
        }
    }

    /**
     * @param  'messenger'|'instagram'  $channel
     */
    private function storeInboundMessage(string $channel, string $pageOrIgId, array $event): void
    {
        $message = $event['message'];
        $metaMessageId = (string) ($message['mid'] ?? '');
        if ($metaMessageId === '') {
            return;
        }

        if (OmniMessage::query()->where('meta_message_id', $metaMessageId)->exists()) {
            return;
        }

        $senderId = (string) ($event['sender']['id'] ?? '');
        if ($senderId === '') {
            return;
        }

        $body = $this->extractMessageBody($message);
        $messageType = $this->detectMessageType($message);
        $tsMs = (int) ($event['timestamp'] ?? 0);
        $sentAt = $tsMs > 0
            ? Carbon::createFromTimestamp((int) floor($tsMs / 1000), 'UTC')->timezone(config('app.timezone'))
            : now();

        $contactKey = "{$channel}_{$senderId}";

        $conversationId = null;
        $inboundMessageId = null;

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

        if ($conversationId && $inboundMessageId) {
            NotifyOmniInboundMessageJob::dispatch($conversationId, $inboundMessageId);
            ProcessOmniFlowJob::dispatch($conversationId, $inboundMessageId);
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
