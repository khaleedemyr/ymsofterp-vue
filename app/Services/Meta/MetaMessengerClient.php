<?php

namespace App\Services\Meta;

use App\Support\MetaPageTokens;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kirim pesan Facebook Messenger & Instagram DM (Graph API Send API, Page token).
 * Mendukung beberapa Facebook Page (token per page_id).
 */
class MetaMessengerClient
{
    public function sendText(string $recipientPsid, string $text, ?string $pageId = null): array
    {
        [$token, $pageId] = $this->resolveCredentials($pageId);
        $version = config('services.meta.graph_api_version', 'v25.0');

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

    /**
     * @return array{0: string, 1: string} [access_token, page_id]
     */
    private function resolveCredentials(?string $pageId): array
    {
        $pageId = $pageId ?: (string) config('services.meta.page_id');
        $tokens = MetaPageTokens::resolved();

        $token = null;
        if ($pageId !== '' && isset($tokens[$pageId])) {
            $token = $tokens[$pageId];
        }

        $token = $token ?: config('services.meta.page_access_token');

        if (! $token || $pageId === '') {
            throw new RuntimeException(
                'Token Page tidak dikonfigurasi untuk Page ID '.$pageId.'. '
                .'Isi META_PAGE_TOKENS (JSON) atau META_PAGE_ACCESS_TOKEN + META_PAGE_ID.'
            );
        }

        return [$token, $pageId];
    }
}
