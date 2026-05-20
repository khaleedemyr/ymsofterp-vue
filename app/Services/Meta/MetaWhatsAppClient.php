<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
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
}
