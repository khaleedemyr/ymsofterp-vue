<?php

namespace App\Services\Meta;

use App\Models\MemberAppsMember;
use App\Models\OmniContact;
use App\Models\OmniConversation;
use App\Models\OmniMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppInboundService
{
    public function processPayload(array $payload): void
    {
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            $wabaId = (string) ($entry['id'] ?? '');

            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                foreach ($value['messages'] ?? [] as $message) {
                    $this->storeInboundMessage($wabaId, $phoneNumberId, $value, $message);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->updateMessageStatus($status);
                }
            }
        }
    }

    private function storeInboundMessage(string $wabaId, string $phoneNumberId, array $value, array $message): void
    {
        $metaMessageId = (string) ($message['id'] ?? '');
        if ($metaMessageId === '') {
            return;
        }

        if (OmniMessage::query()->where('meta_message_id', $metaMessageId)->exists()) {
            return;
        }

        $from = (string) ($message['from'] ?? '');
        $contactName = $this->resolveContactName($value, $from);
        $body = $this->extractMessageBody($message);
        $messageType = (string) ($message['type'] ?? 'text');
        $sentAt = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        $normalizedPhone = $this->normalizePhone($from);
        if ($normalizedPhone === '') {
            $normalizedPhone = preg_replace('/\D/', '', $from) ?? '';
        }
        if ($normalizedPhone === '') {
            Log::warning('Meta WhatsApp inbound: cannot normalize sender phone', ['from' => $from]);

            return;
        }

        DB::transaction(function () use (
            $wabaId,
            $phoneNumberId,
            $from,
            $normalizedPhone,
            $contactName,
            $metaMessageId,
            $messageType,
            $body,
            $message,
            $sentAt
        ) {
            $memberId = $this->findMemberIdByPhone($from);

            $contact = OmniContact::query()->firstOrCreate(
                ['phone_normalized' => $normalizedPhone],
                [
                    'display_name' => $contactName,
                    'member_apps_member_id' => $memberId,
                ]
            );

            if ($contactName) {
                $contact->display_name = $contactName;
            }
            if ($memberId) {
                $contact->member_apps_member_id = $memberId;
            }
            $contact->save();

            $conversation = OmniConversation::query()->firstOrCreate(
                [
                    'channel' => 'whatsapp',
                    'external_contact_id' => $from,
                    'phone_number_id' => $phoneNumberId,
                ],
                [
                    'waba_id' => $wabaId,
                    'contact_name' => $contactName,
                    'member_apps_member_id' => $memberId,
                    'omni_contact_id' => $contact->id,
                    'status' => 'open',
                ]
            );

            $conversation->omni_contact_id = $contact->id;

            if ($contactName && $conversation->contact_name !== $contactName) {
                $conversation->contact_name = $contactName;
            }

            if (! $conversation->member_apps_member_id) {
                if ($memberId) {
                    $conversation->member_apps_member_id = $memberId;
                } elseif ($contact->member_apps_member_id) {
                    $conversation->member_apps_member_id = $contact->member_apps_member_id;
                }
            }

            OmniMessage::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'meta_message_id' => $metaMessageId,
                'message_type' => $messageType,
                'body' => $body,
                'payload' => $message,
                'status' => 'received',
                'sent_at' => $sentAt,
            ]);

            $conversation->last_message_at = $sentAt;
            $conversation->last_customer_message_at = $sentAt;
            $conversation->last_message_preview = mb_substr($body ?: '['.$messageType.']', 0, 500);
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();
        });
    }

    private function updateMessageStatus(array $status): void
    {
        $metaMessageId = (string) ($status['id'] ?? '');
        if ($metaMessageId === '') {
            return;
        }

        $updated = OmniMessage::query()
            ->where('meta_message_id', $metaMessageId)
            ->update([
                'status' => (string) ($status['status'] ?? ''),
            ]);

        if ($updated === 0) {
            Log::debug('Meta WhatsApp status for unknown message', ['meta_message_id' => $metaMessageId]);
        }
    }

    private function resolveContactName(array $value, string $waId): ?string
    {
        foreach ($value['contacts'] ?? [] as $contact) {
            if ((string) ($contact['wa_id'] ?? '') === $waId) {
                return $contact['profile']['name'] ?? null;
            }
        }

        return null;
    }

    private function extractMessageBody(array $message): ?string
    {
        $type = (string) ($message['type'] ?? '');

        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'image' => $message['image']['caption'] ?? '[Gambar]',
            'video' => $message['video']['caption'] ?? '[Video]',
            'audio' => '[Audio]',
            'document' => $message['document']['filename'] ?? '[Dokumen]',
            'sticker' => '[Stiker]',
            'location' => '[Lokasi]',
            'contacts' => '[Kontak]',
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? '[Pesan interaktif]',
            default => '['.$type.']',
        };
    }

    private function findMemberIdByPhone(string $waId): ?int
    {
        $normalized = $this->normalizePhone($waId);
        if ($normalized === '') {
            return null;
        }

        $candidates = array_unique(array_filter([
            $normalized,
            '0'.substr($normalized, 2),
            '+'.$normalized,
        ]));

        $member = MemberAppsMember::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $phone) {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(mobile_phone, '+', ''), ' ', ''), '-', ''), '.', '') = ?",
                        [preg_replace('/\D/', '', $phone)]
                    );
                }
            })
            ->first();

        return $member?->id;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }
}
