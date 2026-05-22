<?php

namespace App\Services\Meta;

use App\Jobs\NotifyOmniInboundMessageJob;
use App\Jobs\ProcessOmniFlowJob;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use App\Support\MetaInstagramTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sinkron DM Instagram (Instagram Login API) via polling — cadangan jika webhook tidak push.
 */
class MetaInstagramInboxSyncService
{
    public function syncAll(): int
    {
        $imported = 0;

        foreach (MetaInstagramTokens::resolved() as $igId => $token) {
            try {
                $imported += $this->syncAccount((string) $igId, $token);
            } catch (\Throwable $e) {
                Log::error('Meta Instagram inbox sync failed for account', [
                    'ig_id' => $igId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    public function syncAccount(string $igProfessionalId, string $accessToken): int
    {
        $version = config('services.meta.instagram_graph_version', 'v25.0');
        $imported = 0;

        $conversations = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/{$igProfessionalId}/conversations", [
                'platform' => 'instagram',
            ]);

        if (! $conversations->successful()) {
            throw new \RuntimeException('List conversations failed: '.$conversations->body());
        }

        foreach ($conversations->json('data') ?? [] as $row) {
            $conversationId = (string) ($row['id'] ?? '');
            if ($conversationId === '') {
                continue;
            }

            $detail = Http::withToken($accessToken)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/{$conversationId}", [
                    'fields' => 'messages',
                ]);

            if (! $detail->successful()) {
                continue;
            }

            foreach ($detail->json('messages.data') ?? [] as $msgRow) {
                $messageId = (string) ($msgRow['id'] ?? '');
                if ($messageId === '') {
                    continue;
                }

                if (OmniMessage::query()->where('meta_message_id', $messageId)->exists()) {
                    continue;
                }

                $full = Http::withToken($accessToken)
                    ->acceptJson()
                    ->get("https://graph.instagram.com/{$version}/{$messageId}", [
                        'fields' => 'id,created_time,from,to,message',
                    ]);

                if (! $full->successful()) {
                    continue;
                }

                $payload = $full->json();
                $fromId = (string) ($payload['from']['id'] ?? '');

                if ($fromId === '' || $fromId === $igProfessionalId) {
                    continue;
                }

                if ($this->storeInboundFromApi($igProfessionalId, $fromId, $messageId, $payload)) {
                    $imported++;
                }
            }
        }

        if ($imported > 0) {
            Log::info('Meta Instagram inbox sync imported messages', [
                'ig_id' => $igProfessionalId,
                'count' => $imported,
            ]);
        }

        return $imported;
    }

    private function storeInboundFromApi(
        string $igProfessionalId,
        string $senderIgsid,
        string $metaMessageId,
        array $payload
    ): bool {
        $body = isset($payload['message']) && is_string($payload['message'])
            ? $payload['message']
            : null;

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
                'message_type' => 'text',
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
            NotifyOmniInboundMessageJob::dispatch($conversationId, $inboundMessageId);
            ProcessOmniFlowJob::dispatch($conversationId, $inboundMessageId);
        }

        return true;
    }
}
