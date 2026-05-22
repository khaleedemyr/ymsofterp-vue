<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Label akun IG bisnis untuk badge inbox (Justus, Tempayan, dll.).
 *
 * Prioritas tampilan:
 * 1. META_INSTAGRAM_LOGIN_ACCOUNT_LABELS (manual di .env)
 * 2. @username dari cache (diisi otomatis saat meta:sync-instagram-inbox)
 * 3. "IG · {6 digit terakhir ID}"
 */
final class MetaInstagramAccountRegistry
{
    private const CACHE_RELATIVE = 'meta/instagram_account_registry.json';

    /**
     * @return array<string, string> ig_professional_id => label
     */
    public static function labels(): array
    {
        $raw = (string) env('META_INSTAGRAM_LOGIN_ACCOUNT_LABELS', '{}');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $igId => $label) {
            if (! is_string($label) || trim($label) === '') {
                continue;
            }
            $labels[(string) $igId] = trim($label);
        }

        return $labels;
    }

    public static function remember(string $igProfessionalId, ?string $username = null): void
    {
        $igProfessionalId = trim($igProfessionalId);
        if ($igProfessionalId === '') {
            return;
        }

        $cache = self::cache();
        $entry = $cache[$igProfessionalId] ?? [];

        if ($username !== null && $username !== '') {
            $entry['username'] = ltrim($username, '@');
        }

        $entry['updated_at'] = now()->toIso8601String();
        $cache[$igProfessionalId] = $entry;

        self::writeCache($cache);
    }

    public static function displayLabel(string $igProfessionalId): string
    {
        $igProfessionalId = trim($igProfessionalId);
        if ($igProfessionalId === '') {
            return 'Instagram';
        }

        $labels = self::labels();
        if (isset($labels[$igProfessionalId])) {
            return $labels[$igProfessionalId];
        }

        $cached = self::cache()[$igProfessionalId]['username'] ?? null;
        if (is_string($cached) && $cached !== '') {
            return '@'.$cached;
        }

        $suffix = strlen($igProfessionalId) > 6
            ? substr($igProfessionalId, -6)
            : $igProfessionalId;

        return 'IG · '.$suffix;
    }

    /**
     * @return array<string, array{username?: string, updated_at?: string}>
     */
    public static function cache(): array
    {
        $path = self::cachePath();
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array{username?: string, updated_at?: string}>  $data
     */
    private static function writeCache(array $data): void
    {
        $path = self::cachePath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function cachePath(): string
    {
        return storage_path('app/'.self::CACHE_RELATIVE);
    }
}
