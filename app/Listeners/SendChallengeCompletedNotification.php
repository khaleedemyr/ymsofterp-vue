<?php

namespace App\Listeners;

use App\Events\ChallengeCompleted;
use App\Services\FCMService;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;

class SendChallengeCompletedNotification
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
    public function handle(ChallengeCompleted $event): void
    {
        try {
            Log::info('SendChallengeCompletedNotification listener triggered', [
                'member_id' => $event->member->id ?? null,
                'challenge_id' => $event->challengeId,
                'reward_type' => $event->rewardType,
            ]);
            
            $member = $event->member;
            $challengeId = $event->challengeId;
            $challengeTitle = $event->challengeTitle;
            $rewardType = $event->rewardType;
            $rewardData = $event->rewardData;

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

            // Build notification message based on reward type
            $title = '';
            $message = '';
            $data = [
                'type' => 'challenge_completed',
                'challenge_id' => $challengeId,
                'challenge_title' => $challengeTitle,
                'reward_type' => $rewardType,
            ];

            switch ($rewardType) {
                case 'point':
                    $pointsEarned = $rewardData['points'] ?? $rewardData['points_earned'] ?? 0;
                    $title = 'Challenge Completed! 🎉';
                    $message = "Congratulations! You've earned {$pointsEarned} points for completing: {$challengeTitle}. Keep up the great work!";
                    $data['points'] = $pointsEarned;
                    $data['total_points'] = $member->just_points ?? 0;
                    break;

                case 'item':
                    $itemName = $rewardData['item_name'] ?? 'Free Item';
                    $title = 'Free Item Unlocked! 🎁';
                    $message = "Amazing! You've unlocked a free {$itemName} for completing: {$challengeTitle}. Redeem it now at any outlet!";
                    $data['item_id'] = $rewardData['item_id'] ?? null;
                    $data['item_name'] = $itemName;
                    $data['serial_code'] = $rewardData['serial_code'] ?? null;
                    break;

                case 'voucher':
                    $voucherName = $rewardData['voucher_name'] ?? 'Voucher';
                    $title = 'Voucher Unlocked! 🎫';
                    $message = "Fantastic! You've unlocked a {$voucherName} voucher for completing: {$challengeTitle}. Use it on your next visit!";
                    $data['voucher_id'] = $rewardData['voucher_id'] ?? null;
                    $data['voucher_name'] = $voucherName;
                    break;

                default:
                    // Fallback for unknown reward types
                    $title = 'Challenge Completed! 🎉';
                    $message = "Congratulations! You've completed: {$challengeTitle}. Check your rewards!";
                    break;
            }

            Log::info('Sending challenge completed notification', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'reward_type' => $rewardType,
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

            Log::info('Challenge completed notification result', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'reward_type' => $rewardType,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
                'total_devices' => $result['success_count'] + $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'challenge_completed',
                    'title' => $title,
                    'message' => $message,
                    'url' => '/challenges',
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info('Challenge completed notification saved to database', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error('Error saving challenge completed notification to database', [
                    'member_id' => $member->id,
                    'error' => $dbError->getMessage(),
                ]);
                // Continue even if database save fails
            }

        } catch (\Exception $e) {
            Log::error('Error sending challenge completed notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'challenge_id' => $event->challengeId ?? null,
            ]);
        }
    }
}

