<?php

namespace App\Support;

/**
 * Parse META_PAGE_TOKENS JSON (page_id / ig_id => Page access token).
 *
 * json_decode casts numeric JSON keys to int; config must use string keys for lookup.
 */
final class MetaPageTokens
{
    /**
     * @return array<string, string>
     */
    public static function parse(?string $raw = null): array
    {
        $raw ??= (string) env('META_PAGE_TOKENS', '{}');
        $decoded = json_decode($raw, true);

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
