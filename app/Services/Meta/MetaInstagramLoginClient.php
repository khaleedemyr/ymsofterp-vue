<?php

namespace App\Services\Meta;

use App\Support\MetaInstagramTokens;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kirim DM Instagram via Instagram API with Instagram Login (graph.instagram.com).
 */
class MetaInstagramLoginClient
{
    public function sendText(string $recipientIgsid, string $text, ?string $igProfessionalId = null): array
    {
        [$token, $igId] = $this->resolveCredentials($igProfessionalId);
        $version = config('services.meta.instagram_graph_version', 'v25.0');

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.instagram.com/{$version}/{$igId}/messages", [
                'recipient' => ['id' => $recipientIgsid],
                'message' => ['text' => $text],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Instagram Login API send failed: '.$response->body(),
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * @return array{0: string, 1: string} [access_token, ig_professional_id]
     */
    private function resolveCredentials(?string $igId): array
    {
        $tokens = MetaInstagramTokens::resolved();
        $igId = $igId ?: (string) config('services.meta.instagram_login_default_id');

        $token = ($igId !== '' && isset($tokens[$igId])) ? $tokens[$igId] : null;
        $token = $token ?: config('services.meta.instagram_login_access_token');

        if (! $token || $igId === '') {
            if ($tokens !== []) {
                $igId = (string) array_key_first($tokens);
                $token = $tokens[$igId];
            }
        }

        if (! $token || $igId === '') {
            throw new RuntimeException(
                'Token Instagram Login tidak dikonfigurasi. Isi META_INSTAGRAM_LOGIN_TOKENS atau '
                .'META_INSTAGRAM_LOGIN_ACCESS_TOKEN + META_INSTAGRAM_LOGIN_DEFAULT_ID.'
            );
        }

        return [$token, $igId];
    }
}
