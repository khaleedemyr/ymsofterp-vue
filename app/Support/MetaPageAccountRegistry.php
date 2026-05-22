<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Label Facebook Page untuk badge inbox Messenger (Asian Grill, Justus Steak, dll.).
 */
final class MetaPageAccountRegistry
{
    private const CACHE_KEY = 'meta_page_account_registry_v1';

    private const FILE_RELATIVE = 'meta/page_account_registry.json';

    /**
     * @return array<string, string> page_id => label
     */
    public static function labels(): array
    {
        $raw = (string) env('META_PAGE_ACCOUNT_LABELS', '{}');
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $pageId => $label) {
            if (! is_string($label) || trim($label) === '') {
                continue;
            }
            $labels[(string) $pageId] = trim($label);
        }

        return $labels;
    }

    public static function remember(string $pageId, ?string $pageName = null): void
    {
        $pageId = trim($pageId);
        if ($pageId === '') {
            return;
        }

        try {
            $store = self::readStore();
            $entry = $store[$pageId] ?? [];

            if ($pageName !== null && $pageName !== '') {
                $entry['name'] = $pageName;
            }

            $entry['updated_at'] = now()->toIso8601String();
            $store[$pageId] = $entry;

            self::writeStore($store);
        } catch (\Throwable $e) {
            Log::warning('MetaPageAccountRegistry: tidak bisa simpan cache nama Page', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function displayLabel(string $pageId): string
    {
        $pageId = trim($pageId);
        if ($pageId === '') {
            return 'Messenger';
        }

        $labels = self::labels();
        if (isset($labels[$pageId])) {
            return $labels[$pageId];
        }

        $store = self::readStore();
        $cached = $store[$pageId]['name'] ?? null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $suffix = strlen($pageId) > 6 ? substr($pageId, -6) : $pageId;

        return 'Page · '.$suffix;
    }

    /**
     * @return array<string, array{name?: string, updated_at?: string}>
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
     * @return array<string, array{name?: string, updated_at?: string}>
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
     * @param  array<string, array{name?: string, updated_at?: string}>  $data
     */
    private static function writeStore(array $data): void
    {
        try {
            Cache::put(self::CACHE_KEY, $data, now()->addDays(90));
        } catch (\Throwable $e) {
            Log::debug('MetaPageAccountRegistry: Cache::put gagal', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $path = self::filePath();
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            Log::debug('MetaPageAccountRegistry: file write skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function filePath(): string
    {
        return storage_path('app/'.self::FILE_RELATIVE);
    }
}
