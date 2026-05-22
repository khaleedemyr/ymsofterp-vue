<?php

namespace App\Support;

use App\Services\Meta\MetaInstagramInboxSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Jalankan sync Instagram saat halaman inbox / poll AJAX — cadangan jika cron schedule:run tidak jalan.
 */
final class MetaInstagramInboxSyncTrigger
{
    public static function maybeRunFromInboxRequest(): void
    {
        if (! filter_var(config('services.meta.instagram_inbox_sync_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (MetaInstagramTokens::resolved() === []) {
            return;
        }

        $seconds = max(15, (int) config('omnichannel.instagram_inbox_poll_sync_seconds', 30));
        $lockKey = 'meta_instagram_inbox_sync_inbox_trigger';

        if (! Cache::add($lockKey, 1, now()->addSeconds($seconds))) {
            return;
        }

        app()->terminating(function () use ($lockKey, $seconds): void {
            try {
                $result = app(MetaInstagramInboxSyncService::class)->syncAll(false);
                Log::info('Instagram inbox sync (triggered by open inbox)', [
                    'imported' => $result['imported'] ?? 0,
                    'accounts' => count($result['accounts'] ?? []),
                ]);
            } catch (\Throwable $e) {
                Cache::forget($lockKey);
                Log::error('Instagram inbox sync (inbox trigger) failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
