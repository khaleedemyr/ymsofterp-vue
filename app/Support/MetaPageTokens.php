<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Parse META_PAGE_TOKENS JSON (page_id => Page access token).
 *
 * WAJIB satu baris di .env (seperti META_PAGE_ACCOUNT_LABELS), contoh:
 * META_PAGE_TOKENS='{"1587793758107643":"EAA...","682421618556416":"EAA..."}'
 *
 * Jangan pecah multi-baris tanpa quote — dotenv hanya membaca `{` lalu JSON gagal.
 */
final class MetaPageTokens
{
    /**
     * @return array<string, string>
     */
    public static function parse(?string $raw = null): array
    {
        $raw ??= (string) env('META_PAGE_TOKENS', '{}');
        $raw = trim($raw);

        if ($raw === '' || $raw === '{}') {
            return [];
        }

        // Hapus wrapping quote yang kadang tersisa dari editor.
        if (
            (str_starts_with($raw, "'") && str_ends_with($raw, "'"))
            || (str_starts_with($raw, '"') && str_ends_with($raw, '"'))
        ) {
            $raw = substr($raw, 1, -1);
        }

        $decoded = json_decode($raw, true);

        // Percobaan ringan: JSON multi-line yang ter-quote di .env (jarang).
        if (! is_array($decoded) && str_contains($raw, "\n")) {
            $decoded = json_decode(preg_replace('/\s+/', '', $raw) ?? $raw, true);
        }

        if (! is_array($decoded)) {
            Log::warning('[MetaPageTokens] META_PAGE_TOKENS JSON tidak valid. Pakai SATU BARIS seperti META_PAGE_ACCOUNT_LABELS. Nilai terpotong biasanya karena multi-line tanpa quote.', [
                'preview' => mb_substr($raw, 0, 80),
                'json_error' => json_last_error_msg(),
            ]);

            return [];
        }

        $tokens = [];
        foreach ($decoded as $pageId => $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }
            $tokens[(string) $pageId] = $token;
        }

        return $tokens;
    }

    /**
     * Tokens for Messenger/Instagram send API (config + live .env fallback).
     *
     * @return array<string, string>
     */
    public static function resolved(): array
    {
        $fromConfig = config('services.meta.page_tokens', []);
        if (is_array($fromConfig) && $fromConfig !== []) {
            return $fromConfig;
        }

        return self::parse();
    }
}
