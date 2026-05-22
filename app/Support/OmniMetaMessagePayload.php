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
                    'image', 'animated_image_share' => 'image',
                    'video' => 'video',
                    'audio' => 'audio',
                    'file' => 'document',
                    'attachment' => 'attachment',
                    default => $rawType !== '' ? $rawType : 'attachment',
                };
                if ($messageType === 'attachment' || $messageType === 'file') {
                    $mime = (string) ($first['mime_type'] ?? '');
                    if (str_starts_with($mime, 'image/')) {
                        $messageType = 'image';
                    }
                }
                $mediaMime = isset($first['mime_type']) ? (string) $first['mime_type'] : null;
                $mediaFilename = isset($first['name']) ? (string) $first['name'] : null;
            }
        }

        if ($attachmentUrl !== null && $messageType === 'text') {
            $messageType = 'image';
        }

        // Pesan gambar dari IG sering punya message="" tanpa attachments di list API
        if ($body === null && $attachmentUrl === null && array_key_exists('message', $payload)) {
            $rawMsg = $payload['message'];
            if ($rawMsg === null || $rawMsg === '') {
                $messageType = 'image';
            }
        }

        if ($body === null && ($attachmentUrl !== null || $messageType !== 'text')) {
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

            foreach (self::attachmentUrlCandidates($item) as $url) {
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private static function attachmentUrlCandidates(array $item): array
    {
        $urls = [];

        foreach (['image_data', 'video_data'] as $key) {
            $data = $item[$key] ?? null;
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            if (is_array($data)) {
                foreach (['url', 'preview_url', 'src'] as $k) {
                    if (! empty($data[$k]) && is_string($data[$k])) {
                        $urls[] = $data[$k];
                    }
                }
            }
        }

        foreach (['file_url', 'url'] as $k) {
            if (! empty($item[$k]) && is_string($item[$k])) {
                $urls[] = $item[$k];
            }
        }

        $payload = $item['payload'] ?? null;
        if (is_array($payload) && ! empty($payload['url']) && is_string($payload['url'])) {
            $urls[] = $payload['url'];
        }

        return $urls;
    }

    public static function mediaUrlForInbox(array $payload, string $messageType = 'text'): ?string
    {
        return self::extractAttachmentUrl($payload);
    }
}
