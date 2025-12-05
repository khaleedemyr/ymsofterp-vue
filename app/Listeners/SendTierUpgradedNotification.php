<?php

namespace App\Listeners;

use App\Events\MemberTierUpgraded;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;

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
            Log::info('SendTierUpgradedNotification listener triggered', [
                'member_id' => $event->member->id ?? null,
                'old_tier' => $event->oldTier ?? null,
                'new_tier' => $event->newTier ?? null,
            ]);

            $member = $event->member;
            $oldTier = $event->oldTier;
            $newTier = $event->newTier;

            // Refresh member to get latest data
            $member->refresh();

            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                Log::info('Skipping tier upgrade notification - member has disabled notifications', [
                    'member_id' => $member->id,
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

            // Send notification
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

        } catch (\Exception $e) {
            Log::error('Error sending tier notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'old_tier' => $event->oldTier ?? null,
                'new_tier' => $event->newTier ?? null,
            ]);
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

