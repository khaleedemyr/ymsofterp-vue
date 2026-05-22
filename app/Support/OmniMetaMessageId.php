<?php

namespace App\Support;

use App\Models\OmniMessage;
use Carbon\Carbon;

/**
 * Satu pesan Meta bisa punya beberapa identifier (webhook mid vs Graph API id).
 */
class OmniMetaMessageId
{
    /**
     * @return list<string>
     */
    public static function aliasesFromWebhook(array $message, array $event = []): array
    {
        $candidates = [
            (string) ($message['mid'] ?? ''),
            (string) ($message['id'] ?? ''),
            (string) ($message['message_id'] ?? ''),
            (string) ($event['message_id'] ?? ''),
        ];

        return self::uniqueNonEmpty($candidates);
    }

    /**
     * @param  list<string>  $aliases
     */
    public static function canonical(array $aliases): string
    {
        if ($aliases === []) {
            return '';
        }

        foreach ($aliases as $id) {
            if (str_starts_with($id, 'm_')) {
                return $id;
            }
        }

        foreach ($aliases as $id) {
            if (str_contains($id, 'mid.')) {
                return $id;
            }
        }

        return $aliases[0];
    }

    /**
     * @param  list<string>  $aliases
     */
    public static function existsAny(array $aliases): bool
    {
        $aliases = self::uniqueNonEmpty($aliases);
        if ($aliases === []) {
            return false;
        }

        return OmniMessage::query()->whereIn('meta_message_id', $aliases)->exists();
    }

    /**
     * Webhook mid vs Graph API id sering beda untuk pesan yang sama.
     */
    public static function findNearDuplicateInbound(
        int $conversationId,
        string $body,
        ?Carbon $sentAt
    ): ?OmniMessage {
        $body = trim($body);
        if ($conversationId <= 0 || $body === '' || $sentAt === null) {
            return null;
        }

        return OmniMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', 'inbound')
            ->where('body', $body)
            ->where('sent_at', '>=', $sentAt->copy()->subMinutes(2))
            ->where('sent_at', '<=', $sentAt->copy()->addMinutes(2))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $strings
     * @return list<string>
     */
    private static function uniqueNonEmpty(array $strings): array
    {
        $out = [];
        foreach ($strings as $s) {
            $s = trim($s);
            if ($s !== '' && ! in_array($s, $out, true)) {
                $out[] = $s;
            }
        }

        return $out;
    }
}
