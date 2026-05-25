<?php

namespace App\Support;

/**
 * Balasan / mention story Instagram (webhook reply_to.story atau field Graph API story).
 */
final class OmniInstagramStoryReply
{
    /**
     * @param  array<string, mixed>  $message  Payload pesan (webhook message atau Graph API row)
     * @return array{kind: string, story_id: ?string, story_url: ?string, label: string}|null
     */
    public static function extract(array $message): ?array
    {
        $replyTo = $message['reply_to'] ?? null;
        if (is_array($replyTo) && isset($replyTo['story']) && is_array($replyTo['story'])) {
            return self::normalizeStoryBlock($replyTo['story'], 'replied_to_story');
        }

        if (isset($message['story']) && is_array($message['story'])) {
            return self::normalizeStoryBlock($message['story'], 'replied_to_story');
        }

        foreach (OmniMetaMessagePayload::attachmentsList($message) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if ($type === 'story_mention') {
                $url = (string) ($item['payload']['url'] ?? $item['url'] ?? '');

                return self::normalizeStoryBlock([
                    'url' => $url,
                    'id' => (string) ($item['payload']['story_id'] ?? ''),
                ], 'story_mention');
            }
        }

        $stored = $message['story_reply'] ?? null;
        if (is_array($stored) && ! empty($stored['story_url'])) {
            return $stored;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $story
     * @return array{kind: string, story_id: ?string, story_url: ?string, label: string}
     */
    private static function normalizeStoryBlock(array $story, string $kind): ?array
    {
        $url = trim((string) ($story['url'] ?? $story['link'] ?? ''));
        $id = trim((string) ($story['id'] ?? ''));

        if ($url === '' && $id === '') {
            return null;
        }

        return [
            'kind' => $kind,
            'story_id' => $id !== '' ? $id : null,
            'story_url' => $url !== '' ? $url : null,
            'label' => $kind === 'story_mention'
                ? 'Menyebut Anda di story'
                : 'Membalas story Anda',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{kind: string, story_id: ?string, story_url: ?string, label: string}|null
     */
    public static function fromPayload(array $payload): ?array
    {
        $stored = $payload['story_reply'] ?? null;
        if (is_array($stored) && (! empty($stored['story_url']) || ! empty($stored['story_id']))) {
            return [
                'kind' => (string) ($stored['kind'] ?? 'replied_to_story'),
                'story_id' => isset($stored['story_id']) ? (string) $stored['story_id'] : null,
                'story_url' => isset($stored['story_url']) ? (string) $stored['story_url'] : null,
                'label' => (string) ($stored['label'] ?? 'Membalas story Anda'),
            ];
        }

        return self::extract($payload);
    }

    public static function isVideoUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return preg_match('/\.(mp4|webm|mov|m4v)(\?|$)/i', $url) === 1;
    }
}
