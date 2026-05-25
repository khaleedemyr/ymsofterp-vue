<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Simpan & replay payload webhook WhatsApp (cadangan jika poll Graph tidak tersedia).
 */
final class MetaWhatsAppWebhookArchive
{
    public static function isEnabled(): bool
    {
        return filter_var(
            config('services.meta.whatsapp_webhook_archive', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function storeFromRequest(Request $request): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $body = $request->getContent();
        if ($body === '') {
            $body = file_get_contents('php://input') ?: '';
        }

        if ($body === '') {
            return null;
        }

        $dir = self::directory();
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $name = now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        File::put($path, $body);

        return $path;
    }

    /**
     * @return list<string>
     */
    public static function pendingFiles(bool $includeProcessed = false): array
    {
        $dir = self::directory();
        if (! File::isDirectory($dir)) {
            return [];
        }

        $files = [];
        foreach (File::files($dir) as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.json')) {
                continue;
            }
            if (! $includeProcessed && str_ends_with($path, '.done.json')) {
                continue;
            }
            if (str_contains(basename($path), '.done.')) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    public static function markProcessed(string $path): void
    {
        if (! File::exists($path) || str_contains(basename($path), '.done.')) {
            return;
        }

        $done = preg_replace('/\.json$/', '.done.json', $path);
        if (is_string($done) && $done !== $path) {
            File::move($path, $done);
        }
    }

    public static function directory(): string
    {
        return storage_path('app/meta-webhook-archive/whatsapp');
    }
}
