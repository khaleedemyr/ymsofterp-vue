<?php

namespace App\Listeners;

use App\Events\MemberTierUpgraded;
use App\Services\FCMService;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendTierUpgradedNotification
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function handle(MemberTierUpgraded $event): void
    {
        try {
            $member = $event->member;
            $oldTier = $event->oldTier;
            $newTier = $event->newTier;
            $memberId = $member->id ?? null;
            
            Log::info('SendTierUpgradedNotification listener triggered', [
                'member_id' => $memberId,
                'old_tier' => $oldTier,
                'new_tier' => $newTier,
            ]);
            
            // Create unique key to prevent duplicate processing
            // Use member_id + old_tier + new_tier as key
            $oldTierNormalized = strtolower(trim($oldTier ?? 'silver'));
            $newTierNormalized = strtolower(trim($newTier ?? 'silver'));
            $notificationKey = "tier_upgraded_notif_sent:{$memberId}:{$oldTierNormalized}:{$newTierNormalized}";
            
            // Check cache first (faster) - prevent duplicate notification
            if (Cache::has($notificationKey)) {
                Log::info('SendTierUpgradedNotification: Duplicate notification prevented (already processed)', [
                    'member_id' => $memberId,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'notification_key' => $notificationKey,
                ]);
                return;
            }
            
            // Create unique lock key to prevent concurrent processing
            $lockKey = "tier_upgraded_notification_lock:{$memberId}:{$oldTierNormalized}:{$newTierNormalized}";
            
            // Try to acquire lock (expires in 30 seconds to prevent deadlock)
            $lock = Cache::lock($lockKey, 30);
            $lockAcquired = $lock->get();
            
            if (!$lockAcquired) {
                Log::info('SendTierUpgradedNotification: Duplicate notification prevented (lock already exists)', [
                    'member_id' => $memberId,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                ]);
                return;
            }
            
            // Double-check after acquiring lock (race condition protection)
            if (Cache::has($notificationKey)) {
                $lock->release();
                Log::info('SendTierUpgradedNotification: Duplicate notification prevented (already processed after lock)', [
                    'member_id' => $memberId,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                ]);
                return;
            }
            
            // Mark as processing immediately
            Cache::put($notificationKey, true, 3600); // Cache for 1 hour

            // Refresh member to get latest data
            $member->refresh();

            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                $lock->release();
                Log::info('Skipping tier upgrade notification - member has disabled notifications', [
                    'member_id' => $memberId,
                ]);
                return;
            }

            // Normalize tier names for display (capitalize first letter)
            $oldTierName = ucfirst(strtolower(trim($oldTier ?? 'Silver')));
            $newTierName = ucfirst(strtolower(trim($newTier ?? 'Silver')));

            // Determine if this is an upgrade or downgrade
            $isUpgrade = $this->isTierUpgrade($oldTier, $newTier);
            $isDowngrade = $this->isTierDowngrade($oldTier, $newTier);

            // Build notification based on upgrade or downgrade
            if ($isUpgrade) {
                // Upgrade notification
                $title = 'You\'ve been upgraded! 🎉';
                $message = "You've been upgraded! Enjoy new benefits as an {$newTierName} Member.";

                $data = [
                    'type' => 'tier_upgraded',
                    'member_id' => $member->id,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'tier_name' => $newTierName,
                    'rolling_spending' => $event->rollingSpending,
                    'action' => 'view_benefits',
                ];
            } elseif ($isDowngrade) {
                // Downgrade notification - encouraging message with benefits
                $nextTier = $this->getNextTier($newTier);
                $nextTierName = ucfirst(strtolower(trim($nextTier)));

                // Build encouraging message with benefits
                $title = 'Keep Climbing! 💪';
                
                // Different messages based on current tier (after downgrade) with specific benefits
                $currentTierLower = strtolower($newTierName);
                if ($currentTierLower === 'silver') {
                    $message = "You're back to Silver tier. Reach Loyal tier to unlock exclusive rewards, 1.5x point earnings, special member benefits, and priority customer support!";
                } elseif ($currentTierLower === 'loyal') {
                    $message = "You're at Loyal tier. Reach Elite tier to enjoy maximum benefits, 2x point earnings, premium rewards, exclusive access, and VIP treatment!";
                } else {
                    $message = "Reach {$nextTierName} tier to unlock exclusive rewards, higher point earnings, special member benefits, and amazing perks!";
                }

                $data = [
                    'type' => 'tier_downgraded',
                    'member_id' => $member->id,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'current_tier' => $newTierName,
                    'next_tier' => $nextTierName,
                    'rolling_spending' => $event->rollingSpending,
                    'action' => 'view_tier_progress',
                ];
            } else {
                // No change - skip notification
                Log::info('Skipping tier notification - tier unchanged', [
                    'member_id' => $member->id,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                ]);
                return;
            }

            $notificationType = $isUpgrade ? 'upgrade' : 'downgrade';
            Log::info("Sending tier {$notificationType} notification", [
                'member_id' => $member->id,
                'old_tier' => $oldTier,
                'new_tier' => $newTier,
                'title' => $title,
                'message' => $message,
            ]);

            // Send push notification
            $result = $this->fcmService->sendToMember(
                $member,
                $title,
                $message,
                $data
            );

            Log::info("Tier {$notificationType} notification result", [
                'member_id' => $member->id,
                'old_tier' => $oldTier,
                'new_tier' => $newTier,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => $isUpgrade ? 'tier_upgraded' : 'tier_downgraded',
                    'title' => $title,
                    'message' => $message,
                    'url' => '/tier-benefits', // URL to view tier benefits
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info("Tier {$notificationType} notification saved to database", [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error("Error saving tier {$notificationType} notification to database", [
                    'member_id' => $member->id,
                    'error' => $dbError->getMessage(),
                ]);
                // Continue even if database save fails
            }
            
            // Release lock after successful processing
            if (isset($lock)) {
                $lock->release();
            }

        } catch (\Exception $e) {
            Log::error('Error sending tier notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'old_tier' => $event->oldTier ?? null,
                'new_tier' => $event->newTier ?? null,
            ]);
            
            // Release lock on error
            if (isset($lock)) {
                try {
                    $lock->release();
                } catch (\Exception $lockError) {
                    Log::warning('Error releasing lock', [
                        'lock_key' => $lockKey ?? 'unknown',
                        'error' => $lockError->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Check if tier change is an upgrade (not downgrade)
     * Tier hierarchy: Silver < Loyal < Elite
     */
    private function isTierUpgrade($oldTier, $newTier): bool
    {
        $tierHierarchy = [
            'silver' => 1,
            'loyal' => 2,
            'elite' => 3,
        ];

        $oldTierNormalized = strtolower(trim($oldTier ?? 'silver'));
        $newTierNormalized = strtolower(trim($newTier ?? 'silver'));

        $oldTierLevel = $tierHierarchy[$oldTierNormalized] ?? 1;
        $newTierLevel = $tierHierarchy[$newTierNormalized] ?? 1;

        // Return true if new tier level is higher than old tier level
        return $newTierLevel > $oldTierLevel;
    }

    /**
     * Check if tier change is a downgrade
     * Tier hierarchy: Silver < Loyal < Elite
     */
    private function isTierDowngrade($oldTier, $newTier): bool
    {
        $tierHierarchy = [
            'silver' => 1,
            'loyal' => 2,
            'elite' => 3,
        ];

        $oldTierNormalized = strtolower(trim($oldTier ?? 'silver'));
        $newTierNormalized = strtolower(trim($newTier ?? 'silver'));

        $oldTierLevel = $tierHierarchy[$oldTierNormalized] ?? 1;
        $newTierLevel = $tierHierarchy[$newTierNormalized] ?? 1;

        // Return true if new tier level is lower than old tier level
        return $newTierLevel < $oldTierLevel;
    }

    /**
     * Get next tier from current tier
     * Tier hierarchy: Silver -> Loyal -> Elite
     */
    private function getNextTier($currentTier): string
    {
        $tierHierarchy = [
            'silver' => 'Loyal',
            'loyal' => 'Elite',
            'elite' => 'Elite', // Max tier, no next tier
        ];

        $currentTierNormalized = strtolower(trim($currentTier ?? 'silver'));
        return $tierHierarchy[$currentTierNormalized] ?? 'Loyal';
    }
}

