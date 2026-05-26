<?php

namespace App\Services\Omni;

use App\Models\OmniConversation;
use App\Services\Meta\MetaInstagramLoginClient;
use App\Services\Meta\MetaWhatsAppClient;
use App\Support\MetaInstagramTokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OmnichannelInboxOutboundMediaService
{
    public const MAX_ATTACHMENTS = 10;

    /**
     * @return array{message_type: string, body: string, preview: string, payload: array<string, mixed>, meta_message_id: string}
     */
    public function sendOutboundFile(
        OmniConversation $conversation,
        UploadedFile $file,
        ?string $caption = null
    ): array {
        $channel = (string) $conversation->channel;
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');
        $caption = $caption !== null && trim($caption) !== '' ? trim($caption) : null;

        if ($channel === 'whatsapp') {
            return $this->sendWhatsApp($conversation, $file, $mime, $isImage, $caption);
        }

        if ($channel === 'instagram' && $this->useInstagramLoginApi()) {
            return $this->sendInstagram($conversation, $file, $mime, $isImage);
        }

        throw new RuntimeException('Lampiran untuk channel ini belum didukung.');
    }

    /**
     * @return array{message_type: string, body: string, preview: string, payload: array<string, mixed>, meta_message_id: string}
     */
    private function sendWhatsApp(
        OmniConversation $conversation,
        UploadedFile $file,
        string $mime,
        bool $isImage,
        ?string $caption
    ): array {
        $client = app(MetaWhatsAppClient::class);
        $storedPath = $file->store("omni-outbound/{$conversation->id}", 'public');
        $localUrl = Storage::disk('public')->url($storedPath);
        $absolutePath = Storage::disk('public')->path($storedPath);
        $mediaId = $client->uploadMedia($absolutePath, $mime, $conversation->phone_number_id);

        if ($isImage) {
            $result = $client->sendImage(
                $conversation->external_contact_id,
                $mediaId,
                $caption,
                $conversation->phone_number_id
            );
            $messageType = 'image';
            $preview = $caption ?? '[Gambar]';
            $body = $caption ?? '';
        } else {
            $result = $client->sendDocument(
                $conversation->external_contact_id,
                $mediaId,
                $caption,
                $file->getClientOriginalName(),
                $conversation->phone_number_id
            );
            $messageType = 'document';
            $preview = $caption ?? '[Lampiran: '.$file->getClientOriginalName().']';
            $body = $caption ?? '';
        }

        $payload = array_merge(is_array($result) ? $result : [], [
            'local_media_url' => $localUrl,
            'media_filename' => $file->getClientOriginalName(),
            'media_mime' => $mime,
        ]);

        return [
            'message_type' => $messageType,
            'body' => $body,
            'preview' => $preview,
            'payload' => $payload,
            'meta_message_id' => (string) ($result['messages'][0]['id'] ?? ''),
        ];
    }

    /**
     * @return array{message_type: string, body: string, preview: string, payload: array<string, mixed>, meta_message_id: string}
     */
    private function sendInstagram(
        OmniConversation $conversation,
        UploadedFile $file,
        string $mime,
        bool $isImage
    ): array {
        $igClient = app(MetaInstagramLoginClient::class);
        $recipient = $conversation->external_contact_id;
        $storedPath = $file->store("omni-outbound/{$conversation->id}", 'public');
        $publicUrl = $this->publicStorageUrl($storedPath);

        if ($isImage) {
            if ($file->getSize() > 8 * 1024 * 1024) {
                throw new RuntimeException('Gambar untuk Instagram maksimal 8 MB.');
            }
            if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
                throw new RuntimeException('Instagram mendukung gambar PNG atau JPEG.');
            }
            $result = $igClient->sendImage($recipient, $publicUrl, $conversation->phone_number_id);
            $messageType = 'image';
            $preview = '[Gambar]';
        } elseif ($mime === 'application/pdf') {
            if ($file->getSize() > 25 * 1024 * 1024) {
                throw new RuntimeException('PDF untuk Instagram maksimal 25 MB.');
            }
            $result = $igClient->sendFile($recipient, $publicUrl, $conversation->phone_number_id);
            $messageType = 'document';
            $preview = '[PDF: '.$file->getClientOriginalName().']';
        } else {
            throw new RuntimeException(
                'Lampiran Instagram: gambar (PNG/JPEG) atau PDF. Video/audio belum didukung di inbox.'
            );
        }

        $payload = array_merge(is_array($result) ? $result : [], [
            'local_media_url' => $publicUrl,
            'media_filename' => $file->getClientOriginalName(),
            'media_mime' => $mime,
        ]);

        return [
            'message_type' => $messageType,
            'body' => '',
            'preview' => $preview,
            'payload' => $payload,
            'meta_message_id' => (string) ($result['message_id'] ?? ''),
        ];
    }

    private function useInstagramLoginApi(): bool
    {
        return MetaInstagramTokens::resolved() !== []
            || (string) config('services.meta.instagram_login_access_token') !== '';
    }

    private function publicStorageUrl(string $storedPath): string
    {
        $relative = Storage::disk('public')->url($storedPath);

        return str_starts_with($relative, 'http')
            ? $relative
            : url($relative);
    }
}
