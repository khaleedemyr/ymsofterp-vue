<?php

namespace App\Support;

/**
 * Token Instagram API with Instagram Login (graph.instagram.com).
 *
 * Format .env META_INSTAGRAM_LOGIN_TOKENS:
 * {"<IG_PROFESSIONAL_ID>":"<USER_ACCESS_TOKEN>",...}
 */
final class MetaInstagramTokens
{
    /**
     * @return array<string, string> ig_professional_account_id => access_token
     */
    public static function parse(?string $raw = null): array
    {
        $raw ??= (string) env('META_INSTAGRAM_LOGIN_TOKENS', '{}');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $tokens = [];
        foreach ($decoded as $igId => $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }
            $tokens[(string) $igId] = $token;
        }

        return $tokens;
    }

    /**
     * @return array<string, string>
     */
    public static function resolved(): array
    {
        $tokens = [];
        $fromConfig = config('services.meta.instagram_login_tokens', []);
        if (is_array($fromConfig) && $fromConfig !== []) {
            $tokens = $fromConfig;
        } else {
            $tokens = self::parse();
        }

        // Satu akun: META_INSTAGRAM_LOGIN_ACCESS_TOKEN + META_INSTAGRAM_LOGIN_DEFAULT_ID
        $singleToken = config('services.meta.instagram_login_access_token');
        $defaultId = (string) config('services.meta.instagram_login_default_id', '');
        if (is_string($singleToken) && $singleToken !== '' && $defaultId !== '') {
            if (! isset($tokens[$defaultId])) {
                $tokens[$defaultId] = $singleToken;
            }
        }

        return $tokens;
    }
}
