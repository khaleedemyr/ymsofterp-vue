<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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
    private const CACHE_KEY = 'meta_instagram_account_registry_v1';

    private const FILE_RELATIVE = 'meta/instagram_account_registry.json';

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

        try {
            $store = self::readStore();
            $entry = $store[$igProfessionalId] ?? [];

            if ($username !== null && $username !== '') {
                $entry['username'] = ltrim($username, '@');
            }

            $entry['updated_at'] = now()->toIso8601String();
            $store[$igProfessionalId] = $entry;

            self::writeStore($store);
        } catch (\Throwable $e) {
            Log::warning('MetaInstagramAccountRegistry: tidak bisa simpan cache username', [
                'ig_id' => $igProfessionalId,
                'error' => $e->getMessage(),
            ]);
        }
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

        $store = self::readStore();
        $cached = $store[$igProfessionalId]['username'] ?? null;
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
    private static function readStore(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable) {
            // fallback file
        }

        return self::readFileStore();
    }

    /**
     * @return array<string, array{username?: string, updated_at?: string}>
     */
    private static function readFileStore(): array
    {
        try {
            $path = self::filePath();
            if (! File::exists($path)) {
                return [];
            }

            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, array{username?: string, updated_at?: string}>  $data
     */
    private static function writeStore(array $data): void
    {
        try {
            Cache::put(self::CACHE_KEY, $data, now()->addDays(90));
        } catch (\Throwable $e) {
            Log::debug('MetaInstagramAccountRegistry: Cache::put gagal', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $path = self::filePath();
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::debug('MetaInstagramAccountRegistry: file write skipped', [
                'path' => self::filePath(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function filePath(): string
    {
        return storage_path('app/'.self::FILE_RELATIVE);
    }
}
