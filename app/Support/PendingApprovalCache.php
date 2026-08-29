<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PendingApprovalCache
{
    public const CACHE_KEY_PREFIX = 'all_pending_approvals_v9_';

    public static function cacheKey(int|string $userId): string
    {
        return self::CACHE_KEY_PREFIX . $userId;
    }

    public static function forgetForUser(int|string|null $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        Cache::forget(self::cacheKey($userId));
    }

    /**
     * @param  list<int|string|null>  $userIds
     */
    public static function forgetForUsers(array $userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            self::forgetForUser($userId);
        }
    }
}
