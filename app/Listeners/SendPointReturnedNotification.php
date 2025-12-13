<?php

namespace App\Listeners;

use App\Events\PointReturned;
use App\Services\FCMService;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendPointReturnedNotification
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
    public function handle(PointReturned $event): void
    {
        try {
            $member = $event->member;
            $pointsReturned = $event->pointsReturned;
            $pointsDeducted = $event->pointsDeducted;
            $source = $event->source;
            $sourceDetails = $event->sourceDetails;
            
            $memberId = $member->id ?? null;
            $orderId = $sourceDetails['order_id'] ?? null;
            $orderNomor = $sourceDetails['order_nomor'] ?? null;
            
            // Create unique key to prevent duplicate processing
            // Use order_id or order_nomor as key, fallback to member_id + timestamp if not available
            $notificationKey = null;
            if ($orderId) {
                $notificationKey = "point_returned_notif_sent:{$memberId}:{$orderId}";
            } elseif ($orderNomor) {
                $notificationKey = "point_returned_notif_sent:{$memberId}:{$orderNomor}";
            } else {
                // Fallback: use member_id + current hour to prevent too many duplicates
                $notificationKey = "point_returned_notif_sent:{$memberId}:" . now()->format('Y-m-d-H');
            }
            
            // Check cache first (faster) - prevent duplicate notification
            if (Cache::has($notificationKey)) {
                Log::info('SendPointReturnedNotification: Duplicate notification prevented (already processed)', [
                    'member_id' => $memberId,
                    'order_id' => $orderId,
                    'order_nomor' => $orderNomor,
                    'notification_key' => $notificationKey,
                ]);
                return;
            }
            
            // Create unique lock key to prevent concurrent processing
            $lockKey = "point_returned_notification_lock:{$memberId}:" . ($orderId ?? $orderNomor ?? 'unknown');
            
            // Try to acquire lock (expires in 30 seconds to prevent deadlock)
            $lock = Cache::lock($lockKey, 30);
            $lockAcquired = $lock->get();
            
            if (!$lockAcquired) {
                Log::info('SendPointReturnedNotification: Duplicate notification prevented (lock already exists)', [
                    'member_id' => $memberId,
                    'order_id' => $orderId,
                    'order_nomor' => $orderNomor,
                ]);
                return;
            }
            
            // Double-check after acquiring lock (race condition protection)
            if (Cache::has($notificationKey)) {
                $lock->release();
                Log::info('SendPointReturnedNotification: Duplicate notification prevented (already processed after lock)', [
                    'member_id' => $memberId,
                    'order_id' => $orderId,
                    'order_nomor' => $orderNomor,
                ]);
                return;
            }
            
            // Mark as processing immediately
            Cache::put($notificationKey, true, 3600); // Cache for 1 hour
            
            // Skip if no points were returned or deducted
            if ($pointsReturned == 0 && $pointsDeducted == 0) {
                $lock->release();
                Log::info('SendPointReturnedNotification: No points to notify (both returned and deducted are 0)', [
                    'member_id' => $memberId,
                ]);
                return;
            }
            
            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                $lock->release();
                Log::info('Skipping notification - member has disabled notifications', [
                    'member_id' => $memberId,
                    'allow_notification' => $member->allow_notification,
                ]);
                return;
            }

            // Refresh member to get latest points
            $member->refresh();

            // Build notification message
            $orderId = $sourceDetails['order_id'] ?? null;
            $orderNomor = $sourceDetails['order_nomor'] ?? null;
            $orderInfo = $orderNomor ? " (Order: {$orderNomor})" : ($orderId ? " (Order: {$orderId})" : '');
            
            $title = 'Points Updated';
            $message = '';
            $data = [
                'type' => 'point_returned',
                'total_points' => $member->just_points ?? 0,
                'source' => $source,
            ];

            if ($pointsReturned > 0 && $pointsDeducted > 0) {
                // Both returned and deducted (net change)
                $netChange = $pointsReturned - $pointsDeducted;
                if ($netChange > 0) {
                    $title = 'Points Returned! ✅';
                    $message = "Your transaction has been voided{$orderInfo}. {$pointsReturned} points have been returned to your account. Your current balance is {$member->just_points} points.";
                    $data['points_returned'] = $pointsReturned;
                    $data['points_deducted'] = $pointsDeducted;
                    $data['net_change'] = $netChange;
                } elseif ($netChange < 0) {
                    $title = 'Points Updated';
                    $message = "Your transaction has been voided{$orderInfo}. Your points have been updated. Your current balance is {$member->just_points} points.";
                    $data['points_returned'] = $pointsReturned;
                    $data['points_deducted'] = $pointsDeducted;
                    $data['net_change'] = $netChange;
                } else {
                    // Net change is 0, no notification needed
                    Log::info('SendPointReturnedNotification: Net change is 0, skipping notification', [
                        'member_id' => $member->id,
                        'points_returned' => $pointsReturned,
                        'points_deducted' => $pointsDeducted,
                    ]);
                    return;
                }
            } elseif ($pointsReturned > 0) {
                // Only returned (from redemption)
                $title = 'Points Returned! ✅';
                $message = "Your transaction has been voided{$orderInfo}. {$pointsReturned} points have been returned to your account. Your current balance is {$member->just_points} points.";
                $data['points_returned'] = $pointsReturned;
            } elseif ($pointsDeducted > 0) {
                // Only deducted (from earning)
                $title = 'Points Updated';
                $message = "Your transaction has been voided{$orderInfo}. Your points have been updated. Your current balance is {$member->just_points} points.";
                $data['points_deducted'] = $pointsDeducted;
            }

            if ($orderId) {
                $data['order_id'] = $orderId;
            }
            if ($orderNomor) {
                $data['order_nomor'] = $orderNomor;
            }

            Log::info('Sending point returned notification', [
                'member_id' => $member->id,
                'points_returned' => $pointsReturned,
                'points_deducted' => $pointsDeducted,
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

            Log::info('Point returned notification result', [
                'member_id' => $member->id,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
                'total_devices' => $result['success_count'] + $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'point_returned',
                    'title' => $title,
                    'message' => $message,
                    'url' => $orderId ? ('/orders/' . $orderId) : null,
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info('Point returned notification saved to database', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error('Error saving point returned notification to database', [
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
            Log::error('Error sending point returned notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
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
}

