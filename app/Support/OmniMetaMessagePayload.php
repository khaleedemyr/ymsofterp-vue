<?php

namespace App\Support;

/**
 * Normalisasi payload pesan Meta (Instagram / Messenger) untuk simpan & tampil di inbox.
 */
final class OmniMetaMessagePayload
{
    /**
     * @return array{body: ?string, message_type: string, attachment_url: ?string, media_mime: ?string, media_filename: ?string}
     */
    public static function normalize(array $payload): array
    {
        $body = isset($payload['message']) && is_string($payload['message']) && trim($payload['message']) !== ''
            ? trim($payload['message'])
            : null;

        $messageType = 'text';
        $attachmentUrl = self::extractAttachmentUrl($payload);
        $mediaMime = null;
        $mediaFilename = null;

        $attachments = $payload['attachments']['data'] ?? $payload['attachments'] ?? [];
        if (is_array($attachments) && $attachments !== []) {
            $first = is_array($attachments[0] ?? null) ? $attachments[0] : null;
            if ($first !== null) {
                $rawType = (string) ($first['type'] ?? 'attachment');
                $messageType = match ($rawType) {
                    'image' => 'image',
                    'video' => 'video',
                    'audio' => 'audio',
                    'file' => 'document',
                    default => $rawType !== '' ? $rawType : 'attachment',
                };
                $mediaMime = isset($first['mime_type']) ? (string) $first['mime_type'] : null;
                $mediaFilename = isset($first['name']) ? (string) $first['name'] : null;
            }
        }

        if ($attachmentUrl !== null && $messageType === 'text') {
            $messageType = 'image';
        }

        if ($body === null && $attachmentUrl !== null) {
            $body = match ($messageType) {
                'image' => '[Gambar]',
                'video' => '[Video]',
                'audio' => '[Audio]',
                'document' => '[Berkas]',
                default => '[Lampiran]',
            };
        }

        return [
            'body' => $body,
            'message_type' => $messageType,
            'attachment_url' => $attachmentUrl,
            'media_mime' => $mediaMime,
            'media_filename' => $mediaFilename,
        ];
    }

    public static function extractAttachmentUrl(array $payload): ?string
    {
        if (isset($payload['local_media_url']) && is_string($payload['local_media_url']) && $payload['local_media_url'] !== '') {
            return $payload['local_media_url'];
        }

        if (isset($payload['attachment_url']) && is_string($payload['attachment_url']) && $payload['attachment_url'] !== '') {
            return $payload['attachment_url'];
        }

        $attachments = $payload['attachments']['data'] ?? $payload['attachments'] ?? [];
        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $item) {
            if (! is_array($item)) {
                continue;
            }

            $candidates = [
                $item['image_data']['url'] ?? null,
                $item['image_data']['preview_url'] ?? null,
                $item['video_data']['url'] ?? null,
                $item['file_url'] ?? null,
                $item['payload']['url'] ?? null,
            ];

            foreach ($candidates as $url) {
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    public static function mediaUrlForInbox(array $payload, string $messageType = 'text'): ?string
    {
        $url = self::extractAttachmentUrl($payload);

        return $url !== null ? $url : null;
    }
}
