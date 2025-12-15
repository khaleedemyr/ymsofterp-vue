<?php

namespace App\Listeners;

use App\Events\ChallengeRolledBack;
use App\Services\FCMService;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;

class SendChallengeRolledBackNotification
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
    public function handle(ChallengeRolledBack $event): void
    {
        try {
            Log::info('SendChallengeRolledBackNotification listener triggered', [
                'member_id' => $event->member->id ?? null,
                'order_id' => $event->orderId,
                'rewards_count' => count($event->rewardsRolledBack),
            ]);
            
            $member = $event->member;
            $orderId = $event->orderId;
            $rewardsRolledBack = $event->rewardsRolledBack;

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

            // Skip if no rewards were rolled back
            if (empty($rewardsRolledBack)) {
                Log::info('No rewards rolled back, skipping notification', [
                    'member_id' => $member->id,
                ]);
                return;
            }

            // Build notification message
            $challengeCount = count($rewardsRolledBack);
            $title = 'Challenge Progress Updated ⚠️';
            
            if ($challengeCount === 1) {
                $challengeTitle = $rewardsRolledBack[0]['challenge_title'] ?? 'Challenge';
                $message = "Your transaction for order {$orderId} was voided. Challenge progress for '{$challengeTitle}' has been updated.";
            } else {
                $message = "Your transaction for order {$orderId} was voided. Progress for {$challengeCount} challenges has been updated.";
            }

            $data = [
                'type' => 'challenge_rolled_back',
                'order_id' => $orderId,
                'challenges_affected' => $challengeCount,
                'rewards_rolled_back' => $rewardsRolledBack,
            ];

            Log::info('Sending challenge rolled back notification', [
                'member_id' => $member->id,
                'order_id' => $orderId,
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

            Log::info('Challenge rolled back notification result', [
                'member_id' => $member->id,
                'order_id' => $orderId,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
                'total_devices' => $result['success_count'] + $result['failed_count'],
            ]);

            // Save notification to database
            try {
                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'challenge_rolled_back',
                    'title' => $title,
                    'message' => $message,
                    'url' => '/challenges',
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info('Challenge rolled back notification saved to database', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $dbError) {
                Log::error('Error saving challenge rolled back notification to database', [
                    'member_id' => $member->id,
                    'error' => $dbError->getMessage(),
                ]);
                // Continue even if database save fails
            }

        } catch (\Exception $e) {
            Log::error('Error sending challenge rolled back notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'order_id' => $event->orderId ?? null,
            ]);
        }
    }
}
