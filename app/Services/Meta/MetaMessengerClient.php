<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kirim pesan Facebook Messenger & Instagram DM (Graph API Send API, Page token).
 */
class MetaMessengerClient
{
    public function sendText(string $recipientPsid, string $text, ?string $pageId = null): array
    {
        $token = config('services.meta.page_access_token');
        $pageId = $pageId ?: config('services.meta.page_id');
        $version = config('services.meta.graph_api_version', 'v25.0');

        if (! $token || ! $pageId) {
            throw new RuntimeException('Meta Page API credentials are not configured (META_PAGE_ACCESS_TOKEN, META_PAGE_ID).');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$pageId}/messages", [
                'messaging_type' => 'RESPONSE',
                'recipient' => ['id' => $recipientPsid],
                'message' => ['text' => $text],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Meta Messenger/Instagram send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }
}
