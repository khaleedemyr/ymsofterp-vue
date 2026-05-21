<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MetaWhatsAppClient
{
    public function sendText(string $toWaId, string $text, ?string $phoneNumberId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $toWaId,
                'type' => 'text',
                'text' => [
                    'body' => $text,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    public function uploadMedia(string $absolutePath, string $mimeType, ?string $phoneNumberId = null): string
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp media upload failed: '.$response->body(),
                $response->status()
            );
        }

        $id = (string) ($response->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Meta WhatsApp media upload returned no id.');
        }

        return $id;
    }

    /**
     * Unduh media inbound dari Graph API (URL dari Meta kedaluwarsa — simpan ke storage lokal).
     *
     * @return array{local_media_url: string, media_filename: string, media_mime: string}|null
     */
    public function downloadMedia(string $mediaId, ?string $preferredFilename = null, ?string $phoneNumberId = null): ?array
    {
        $token = config('services.meta.whatsapp_access_token');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || $mediaId === '') {
            return null;
        }

        $metaResponse = Http::withToken($token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$version}/{$mediaId}");

        if (! $metaResponse->successful()) {
            Log::warning('Meta WhatsApp media metadata failed', [
                'media_id' => $mediaId,
                'status' => $metaResponse->status(),
                'body' => $metaResponse->body(),
            ]);

            return null;
        }

        $downloadUrl = (string) ($metaResponse->json('url') ?? '');
        $mime = (string) ($metaResponse->json('mime_type') ?? 'application/octet-stream');

        if ($downloadUrl === '') {
            return null;
        }

        $fileResponse = Http::withToken($token)->get($downloadUrl);

        if (! $fileResponse->successful()) {
            Log::warning('Meta WhatsApp media download failed', [
                'media_id' => $mediaId,
                'status' => $fileResponse->status(),
            ]);

            return null;
        }

        $filename = $preferredFilename ?: ('wa-media.'.$this->extensionFromMime($mime));
        $filename = $this->sanitizeFilename($filename);
        $storedPath = 'omni-inbound/'.date('Y/m').'/'.$mediaId.'_'.$filename;

        Storage::disk('public')->put($storedPath, $fileResponse->body());

        return [
            'local_media_url' => Storage::disk('public')->url($storedPath),
            'media_filename' => $filename,
            'media_mime' => $mime,
        ];
    }

    public function sendImage(string $toWaId, string $mediaId, ?string $caption = null, ?string $phoneNumberId = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'image',
            'image' => ['id' => $mediaId],
        ];
        if ($caption !== null && $caption !== '') {
            $payload['image']['caption'] = $caption;
        }

        return $this->postMessage($payload, $phoneNumberId);
    }

    public function sendDocument(string $toWaId, string $mediaId, ?string $caption = null, ?string $filename = null, ?string $phoneNumberId = null): array
    {
        $doc = ['id' => $mediaId];
        if ($caption !== null && $caption !== '') {
            $doc['caption'] = $caption;
        }
        if ($filename !== null && $filename !== '') {
            $doc['filename'] = $filename;
        }

        return $this->postMessage([
            'messaging_product' => 'whatsapp',
            'to' => $toWaId,
            'type' => 'document',
            'document' => $doc,
        ], $phoneNumberId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postMessage(array $payload, ?string $phoneNumberId = null): array
    {
        $token = config('services.meta.whatsapp_access_token');
        $phoneNumberId = $phoneNumberId ?: config('services.meta.whatsapp_phone_number_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('Meta WhatsApp API credentials are not configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta WhatsApp send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    private function extensionFromMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'jpeg'), $mime === 'image/jpg' => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mp4'), str_contains($mime, 'video') => 'mp4',
            default => 'bin',
        };
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', $name) ?? 'file';
        $name = trim($name, ' .');

        return $name !== '' ? $name : 'file.bin';
    }
}
