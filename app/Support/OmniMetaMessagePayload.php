<?php

namespace App\Support;

use App\Models\OmniMessage;

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
                    'image', 'animated_image_share', 'ig_reel', 'reel' => 'image',
                    'video' => 'video',
                    'audio' => 'audio',
                    'file' => 'document',
                    'ephemeral' => 'ephemeral',
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
                'ephemeral' => '[Foto sekali lihat — tidak dapat ditampilkan di inbox]',
                default => '[Lampiran]',
            };
        }

        if ($body === null && self::isEphemeralOnly($payload)) {
            $body = '[Foto sekali lihat — tidak dapat ditampilkan di inbox]';
            $messageType = 'ephemeral';
        }

        return [
            'body' => $body,
            'message_type' => $messageType,
            'attachment_url' => $attachmentUrl,
            'media_mime' => $mediaMime,
            'media_filename' => $mediaFilename,
        ];
    }

    /**
     * Pesan view-once dari kamera IG: webhook type=ephemeral tanpa URL (tidak bisa di-cache).
     */
    public static function isEphemeralOnly(array $payload): bool
    {
        $attachments = self::attachmentsList($payload);
        if ($attachments === []) {
            return false;
        }

        $hasEphemeral = false;
        foreach ($attachments as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if ($type === 'ephemeral') {
                $hasEphemeral = true;
                if (self::attachmentUrlCandidates($item) !== []) {
                    return false;
                }
            }
        }

        return $hasEphemeral;
    }

    /**
     * @return list<string>
     */
    public static function extractAttachmentIds(array $payload): array
    {
        $ids = [];
        foreach (self::attachmentsList($payload) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $payloadObj = $item['payload'] ?? null;
            if (is_array($payloadObj) && ! empty($payloadObj['attachment_id'])) {
                $id = (string) $payloadObj['attachment_id'];
                if ($id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function messageIdCandidates(OmniMessage $message): array
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        $candidates = [
            (string) ($message->meta_message_id ?? ''),
            (string) ($payload['id'] ?? ''),
            (string) ($payload['mid'] ?? ''),
            (string) ($payload['message_id'] ?? ''),
        ];

        $out = [];
        foreach ($candidates as $c) {
            $c = trim($c);
            if ($c !== '' && ! in_array($c, $out, true)) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function attachmentsList(array $payload): array
    {
        $attachments = $payload['attachments']['data'] ?? $payload['attachments'] ?? [];

        return is_array($attachments) ? $attachments : [];
    }

    public static function extractAttachmentUrl(array $payload): ?string
    {
        if (self::isEphemeralOnly($payload)) {
            return null;
        }

        if (isset($payload['local_media_url']) && is_string($payload['local_media_url']) && $payload['local_media_url'] !== '') {
            return $payload['local_media_url'];
        }

        if (isset($payload['attachment_url']) && is_string($payload['attachment_url']) && $payload['attachment_url'] !== '') {
            return $payload['attachment_url'];
        }

        // WhatsApp Cloud API (payload = message + hasil download)
        foreach (['image', 'video', 'audio', 'document', 'sticker'] as $kind) {
            if (! empty($payload[$kind]['link']) && is_string($payload[$kind]['link'])) {
                return $payload[$kind]['link'];
            }
        }

        foreach (self::attachmentsList($payload) as $item) {
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
                foreach (['url', 'preview_url', 'src', 'animated_gif_url', 'raw_image_url', 'original_url'] as $k) {
                    if (! empty($data[$k]) && is_string($data[$k])) {
                        $urls[] = $data[$k];
                    }
                }
            }
        }

        foreach (['file_url', 'url', 'image_url'] as $k) {
            if (! empty($item[$k]) && is_string($item[$k])) {
                $urls[] = $item[$k];
            }
        }

        $payload = $item['payload'] ?? null;
        if (is_array($payload)) {
            foreach (['url', 'image_url', 'file_url'] as $k) {
                if (! empty($payload[$k]) && is_string($payload[$k])) {
                    $urls[] = $payload[$k];
                }
            }
        }

        return $urls;
    }

    public static function mediaUrlForInbox(array $payload, string $messageType = 'text'): ?string
    {
        return self::extractAttachmentUrl($payload);
    }
}
