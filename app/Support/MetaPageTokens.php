<?php

namespace App\Support;

/**
 * Parse META_PAGE_TOKENS JSON (page_id => Page access token).
 *
 * WAJIB satu baris di .env (seperti META_PAGE_ACCOUNT_LABELS), contoh:
 * META_PAGE_TOKENS='{"1587793758107643":"EAA...","682421618556416":"EAA..."}'
 *
 * Jangan pecah multi-baris tanpa quote — dotenv hanya membaca `{` lalu JSON gagal.
 *
 * Catatan: jangan pakai Log/Facade di sini — method ini dipanggil dari config/services.php
 * saat bootstrap (termasuk `config:clear`), sebelum facade root siap.
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
