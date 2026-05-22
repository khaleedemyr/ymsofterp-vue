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
    /**
     * @return array{imported: int, accounts: list<array<string, mixed>>}
     */
    public function syncAll(bool $verbose = false): array
    {
        $imported = 0;
        $accounts = [];

        foreach (MetaInstagramTokens::resolved() as $igId => $token) {
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
        $apiErrors = 0;

        $me = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/me", [
                'fields' => 'user_id,username',
            ]);

        if ($me->successful()) {
            $resolvedId = (string) ($me->json('user_id') ?? '');
            if ($resolvedId !== '') {
                $igProfessionalId = $resolvedId;
            }
        }

        $conversations = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.instagram.com/{$version}/me/conversations", [
                'platform' => 'instagram',
            ]);

        if (! $conversations->successful()) {
            $conversations = Http::withToken($accessToken)
                ->acceptJson()
                ->get("https://graph.instagram.com/{$version}/{$igProfessionalId}/conversations", [
                    'platform' => 'instagram',
                ]);
        }

        if (! $conversations->successful()) {
            throw new \RuntimeException('List conversations failed: '.$conversations->body());
        }

        $conversationRows = $conversations->json('data') ?? [];

        foreach ($conversationRows as $row) {
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
                $apiErrors++;

                continue;
            }

            $messageRows = $detail->json('messages.data') ?? [];

            foreach ($messageRows as $msgRow) {
                $messageId = (string) ($msgRow['id'] ?? '');
                if ($messageId === '') {
                    continue;
                }

                $messagesChecked++;

                if (OmniMessage::query()->where('meta_message_id', $messageId)->exists()) {
                    $skippedExisting++;

                    continue;
                }

                $full = Http::withToken($accessToken)
                    ->acceptJson()
                    ->get("https://graph.instagram.com/{$version}/{$messageId}", [
                        'fields' => 'id,created_time,from,to,message',
                    ]);

                if (! $full->successful()) {
                    $apiErrors++;

                    continue;
                }

                $payload = $full->json();
                $fromId = (string) ($payload['from']['id'] ?? '');

                if ($fromId === '' || $this->isOutboundFromBusiness($payload, $igProfessionalId)) {
                    $skippedOutbound++;

                    continue;
                }

                if ($this->storeInboundFromApi($igProfessionalId, $fromId, $messageId, $payload)) {
                    $imported++;
                }
            }
        }

        $summary = [
            'ig_id' => $igProfessionalId,
            'username' => $me->successful() ? (string) ($me->json('username') ?? '') : '',
            'imported' => $imported,
            'conversations' => count($conversationRows),
            'messages_checked' => $messagesChecked,
            'skipped_existing' => $skippedExisting,
            'skipped_outbound' => $skippedOutbound,
            'api_errors' => $apiErrors,
        ];

        Log::info('Meta Instagram inbox sync finished', $summary);

        return $summary;
    }

    private function isOutboundFromBusiness(array $payload, string $igProfessionalId): bool
    {
        $fromId = (string) ($payload['from']['id'] ?? '');

        return $fromId === '' || $fromId === $igProfessionalId;
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
