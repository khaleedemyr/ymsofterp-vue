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
}
