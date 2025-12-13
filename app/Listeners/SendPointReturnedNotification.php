<?php

namespace App\Listeners;

use App\Events\PointReturned;
use App\Services\FCMService;
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
            
            // Skip if no points were returned or deducted
            if ($pointsReturned == 0 && $pointsDeducted == 0) {
                Log::info('SendPointReturnedNotification: No points to notify (both returned and deducted are 0)', [
                    'member_id' => $member->id,
                ]);
                return;
            }
            
            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                Log::info('Skipping notification - member has disabled notifications', [
                    'member_id' => $member->id,
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

            // Send notification
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

        } catch (\Exception $e) {
            Log::error('Error sending point returned notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
            ]);
        }
    }
}

