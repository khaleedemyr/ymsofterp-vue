<?php

namespace App\Listeners;

use App\Events\PointEarned;
use App\Services\FCMService;
use App\Models\MemberAppsReward;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SendPointEarnedNotification
{
    protected $fcmService;

    /**
     * Create the event listener.
     */
    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Handle the event.
     */
    public function handle(PointEarned $event): void
    {
        try {
            // Create unique key to prevent duplicate processing
            $transactionId = $event->pointTransaction->id ?? null;
            $memberId = $event->member->id ?? null;
            $pointsEarned = $event->pointsEarned ?? null;
            
            if (!$transactionId || !$memberId) {
                Log::warning('SendPointEarnedNotification: Missing transaction_id or member_id', [
                    'transaction_id' => $transactionId,
                    'member_id' => $memberId,
                ]);
                return;
            }
            
            // Check if notification already sent for this transaction using database
            // Use a combination of transaction_id and a flag in cache/database
            $notificationKey = "point_earned_notif_sent:{$transactionId}";
            
            // Check cache first (faster)
            if (Cache::has($notificationKey)) {
                Log::info('SendPointEarnedNotification: Duplicate notification prevented (already processed)', [
                    'member_id' => $memberId,
                    'transaction_id' => $transactionId,
                    'points' => $pointsEarned,
                ]);
                return;
            }
            
            // Create unique lock key to prevent concurrent processing
            $lockKey = "point_earned_notification_lock:{$transactionId}";
            
            // Try to acquire lock (expires in 30 seconds to prevent deadlock)
            $lock = Cache::lock($lockKey, 30);
            $lockAcquired = $lock->get();
            
            if (!$lockAcquired) {
                Log::info('SendPointEarnedNotification: Duplicate notification prevented (lock already exists)', [
                    'member_id' => $memberId,
                    'transaction_id' => $transactionId,
                    'points' => $pointsEarned,
                ]);
                return;
            }
            
            // Double-check after acquiring lock (race condition protection)
            if (Cache::has($notificationKey)) {
                $lock->release();
                Log::info('SendPointEarnedNotification: Duplicate notification prevented (already processed after lock)', [
                    'member_id' => $memberId,
                    'transaction_id' => $transactionId,
                    'points' => $pointsEarned,
                ]);
                return;
            }
            
            // Mark as processing immediately
            Cache::put($notificationKey, true, 3600); // Cache for 1 hour
            
            Log::info('SendPointEarnedNotification listener triggered', [
                'member_id' => $memberId,
                'points' => $pointsEarned,
                'transaction_id' => $transactionId,
            ]);
            
            $member = $event->member;
            $source = $event->source;
            $sourceDetails = $event->sourceDetails;

            // Calculate previous points before refresh (to check if reward just became redeemable)
            $previousPoints = ($member->just_points ?? 0) - $pointsEarned;

            // Refresh member to get latest data
            $member->refresh();

            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                Log::info('Skipping notification - member has disabled notifications', [
                    'member_id' => $member->id,
                    'allow_notification' => $member->allow_notification,
                ]);
                return;
            }

            // Build notification message based on source
            $data = [
                'type' => 'point_earned',
                'points' => $pointsEarned,
                'total_points' => $member->just_points ?? 0,
                'transaction_id' => $event->pointTransaction->id,
                'source' => $source,
            ];

            if ($source === 'challenge') {
                // Point from challenge reward
                $title = 'Challenge Completed! 🎉';
                $challengeTitle = $sourceDetails['challenge_title'] ?? 'Challenge';
                $message = "Congratulations! You've earned {$pointsEarned} points for completing: {$challengeTitle}. Keep up the great work!";
                $data['challenge_id'] = $sourceDetails['challenge_id'] ?? null;
                $data['challenge_title'] = $challengeTitle;
            } else {
                // Point from transaction (POS)
                $title = 'Points Successfully Added! 🎉';
                $outletName = $sourceDetails['outlet_name'] ?? 'Outlet';
                $orderId = $sourceDetails['order_id'] ?? null;
                
                // Include order_id in message if available
                if ($orderId) {
                    $message = "You've earned {$pointsEarned} points from a transaction at {$outletName} (Order: {$orderId}). Check your balance and redeem delicious rewards.";
                } else {
                    $message = "You've earned {$pointsEarned} points from a transaction at {$outletName}. Check your balance and redeem delicious rewards.";
                }
                
                $data['order_id'] = $orderId;
                $data['outlet_name'] = $outletName;
            }

            // Add expiration info if available
            if ($event->pointTransaction->expires_at) {
                $data['expires_at'] = $event->pointTransaction->expires_at->format('Y-m-d');
            }

            Log::info('Sending point earned notification', [
                'member_id' => $member->id,
                'points' => $pointsEarned,
                'source' => $source,
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

            Log::info('Point earned notification result', [
                'member_id' => $member->id,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
                'total_devices' => $result['success_count'] + $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'point_earned',
                    'title' => $title,
                    'message' => $message,
                    'url' => $data['order_id'] ? ('/orders/' . $data['order_id']) : null,
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info('Point earned notification saved to database', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error('Error saving point earned notification to database', [
                    'member_id' => $member->id,
                    'error' => $dbError->getMessage(),
                ]);
                // Continue even if database save fails
            }

            // Check if there are rewards that can now be redeemed (point sudah cukup)
            // Only check for transaction source (not challenge, to avoid duplicate)
            if ($source === 'transaction') {
                $this->checkAndNotifyNewRedeemableRewards($member, $previousPoints, $pointsEarned);
            }
            
            // Release lock after successful processing
            if (isset($lock)) {
                $lock->release();
            }

        } catch (\Exception $e) {
            Log::error('Error sending point earned notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'transaction_id' => $transactionId ?? null,
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
     * Check if there are rewards that can now be redeemed (point sudah cukup)
     * and send notification for newly redeemable rewards
     */
    private function checkAndNotifyNewRedeemableRewards($member, $previousPoints, $pointsEarned): void
    {
        try {
            $currentPoints = $member->just_points ?? 0;
            
            // Find rewards that just became redeemable
            // (points_required > previousPoints AND points_required <= currentPoints)
            // This means the reward was not redeemable before but is now redeemable
            
            $newlyRedeemableRewards = DB::select("
                SELECT 
                    r.id,
                    r.item_id,
                    r.points_required,
                    i.name as item_name
                FROM member_apps_rewards r
                LEFT JOIN items i ON i.id = r.item_id
                WHERE r.is_active = 1
                    AND r.points_required > 0
                    AND r.points_required > ?
                    AND r.points_required <= ?
                    AND r.item_id IS NOT NULL
                ORDER BY r.points_required ASC
                LIMIT 1
            ", [$previousPoints, $currentPoints]);

            if (empty($newlyRedeemableRewards)) {
                Log::debug('No newly redeemable reward found', [
                    'member_id' => $member->id,
                    'previous_points' => $previousPoints,
                    'current_points' => $currentPoints,
                    'points_earned' => $pointsEarned,
                ]);
                return;
            }

            $newlyRedeemableReward = $newlyRedeemableRewards[0];
            $itemName = $newlyRedeemableReward->item_name ?? 'Reward Item';
            $pointsRequired = $newlyRedeemableReward->points_required;

            Log::info('Found newly redeemable reward for notification', [
                'member_id' => $member->id,
                'reward_id' => $newlyRedeemableReward->id,
                'item_name' => $itemName,
                'points_required' => $pointsRequired,
                'previous_points' => $previousPoints,
                'current_points' => $currentPoints,
            ]);

            // Send push notification
            $result = $this->fcmService->sendToMember(
                $member,
                'New Reward Unlocked! 🎁',
                'Congrats! You\'ve unlocked a new reward. Redeem it on your next visit.',
                [
                    'type' => 'reward_unlocked',
                    'reward_id' => $newlyRedeemableReward->id,
                    'item_id' => $newlyRedeemableReward->item_id,
                    'item_name' => $itemName,
                    'points_required' => $pointsRequired,
                    'current_points' => $currentPoints,
                ]
            );

            Log::info('Reward unlocked notification sent', [
                'member_id' => $member->id,
                'reward_id' => $newlyRedeemableReward->id,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'reward_unlocked',
                    'title' => 'New Reward Unlocked! 🎁',
                    'message' => 'Congrats! You\'ve unlocked a new reward. Redeem it on your next visit.',
                    'url' => '/rewards',
                    'data' => [
                        'type' => 'reward_unlocked',
                        'reward_id' => $newlyRedeemableReward->id,
                        'item_id' => $newlyRedeemableReward->item_id,
                        'item_name' => $itemName,
                        'points_required' => $pointsRequired,
                        'current_points' => $currentPoints,
                    ],
                    'is_read' => false,
                ]);
                
                Log::info('Reward unlocked notification saved to database', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error('Error saving reward unlocked notification to database', [
                    'member_id' => $member->id,
                    'error' => $dbError->getMessage(),
                ]);
                // Continue even if database save fails
            }

        } catch (\Exception $e) {
            Log::error('Error checking and notifying new redeemable rewards', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $member->id ?? null,
            ]);
        }
    }
}

