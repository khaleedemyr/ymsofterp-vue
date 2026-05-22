<?php

namespace App\Support;

use App\Services\Meta\MetaMessengerInboxSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sync Messenger saat inbox web dibuka / di-poll (cadangan jika cron tidak jalan).
 */
final class MetaMessengerInboxSyncTrigger
{
    public static function maybeRunFromInboxRequest(): void
    {
        if (! filter_var(config('services.meta.messenger_inbox_sync_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (MetaPageTokens::resolved() === [] && ! config('services.meta.page_access_token')) {
            return;
        }

        $seconds = max(15, (int) config('omnichannel.messenger_inbox_poll_sync_seconds', 30));
        $lockKey = 'meta_messenger_inbox_sync_inbox_trigger';

        if (! Cache::add($lockKey, 1, now()->addSeconds($seconds))) {
            return;
        }

        $recentMinutes = max(15, (int) config('omnichannel.messenger_inbox_sync_recent_minutes', 45));

        dispatch(function () use ($lockKey, $recentMinutes): void {
            try {
                $result = app(MetaMessengerInboxSyncService::class)->syncAll(false, $recentMinutes);
                Log::info('Messenger inbox sync (triggered by open inbox)', [
                    'imported' => $result['imported'] ?? 0,
                    'recent_minutes' => $recentMinutes,
                    'accounts' => count($result['accounts'] ?? []),
                ]);
            } catch (\Throwable $e) {
                Cache::forget($lockKey);
                Log::error('Messenger inbox sync (inbox trigger) failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
