<?php

namespace App\Listeners;

use App\Events\ChallengeCreated;
use App\Services\FCMService;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendChallengeCreatedNotification
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
    public function handle(ChallengeCreated $event): void
    {
        try {
            $challenge = $event->challenge;
            
            // Refresh challenge to ensure we have latest data
            $challenge->refresh();
            
            Log::info('SendChallengeCreatedNotification listener triggered', [
                'challenge_id' => $challenge->id,
                'challenge_title' => $challenge->title,
                'is_active' => $challenge->is_active,
            ]);

            // Only send notification if challenge is active
            if (!$challenge->is_active) {
                Log::info('Skipping notification - challenge is not active', [
                    'challenge_id' => $challenge->id,
                    'is_active' => $challenge->is_active,
                ]);
                return;
            }

            // Check if notifications for this challenge were already sent recently (prevent duplicate listener trigger)
            // Check if any notification was created for this challenge in the last 2 minutes
            $recentChallengeNotification = MemberAppsNotification::where('type', 'challenge_created')
                ->whereRaw("JSON_EXTRACT(data, '$.challenge_id') = ?", [$challenge->id])
                ->where('created_at', '>=', now()->subMinutes(2))
                ->first();

            if ($recentChallengeNotification) {
                Log::info('Duplicate challenge created notification prevented - already sent recently', [
                    'challenge_id' => $challenge->id,
                    'existing_notification_id' => $recentChallengeNotification->id,
                    'existing_notification_created_at' => $recentChallengeNotification->created_at,
                    'member_id' => $recentChallengeNotification->member_id,
                ]);
                return; // Exit early, don't send to any member
            }

            // Build notification message
            $title = 'New Challenge Available! 🎯';
            $message = "A new challenge '{$challenge->title}' is now available! Start now and earn amazing rewards.";
            
            // Prepare data for FCM (all values must be strings)
            $fcmData = [
                'type' => 'challenge_created',
                'challenge_id' => (string)$challenge->id,
                'challenge_title' => $challenge->title,
            ];

            // Prepare data for database (keep as array for JSON storage)
            $dbData = [
                'type' => 'challenge_created',
                'challenge_id' => $challenge->id,
                'challenge_title' => $challenge->title,
            ];

            // Get all active members
            $activeMembers = MemberAppsMember::where('is_active', true)
                ->where('allow_notification', true)
                ->get();

            Log::info('Sending challenge created notification to active members', [
                'challenge_id' => $challenge->id,
                'total_active_members' => $activeMembers->count(),
                'member_ids' => $activeMembers->pluck('id')->toArray(),
            ]);

            if ($activeMembers->isEmpty()) {
                Log::warning('No active members found to send challenge created notification', [
                    'challenge_id' => $challenge->id,
                ]);
                return;
            }

            $successCount = 0;
            $failedCount = 0;

            // Send notification to each active member
            foreach ($activeMembers as $member) {
                try {
                    // Check for duplicate notification (prevent multiple notifications for same challenge)
                    // Check if notification was already sent in the last 5 minutes for this challenge
                    $recentNotification = MemberAppsNotification::where('member_id', $member->id)
                        ->where('type', 'challenge_created')
                        ->whereRaw("JSON_EXTRACT(data, '$.challenge_id') = ?", [$challenge->id])
                        ->where('created_at', '>=', now()->subMinutes(5))
                        ->first();

                    if ($recentNotification) {
                        Log::info('Duplicate challenge created notification prevented', [
                            'member_id' => $member->id,
                            'challenge_id' => $challenge->id,
                            'existing_notification_id' => $recentNotification->id,
                            'existing_notification_created_at' => $recentNotification->created_at,
                        ]);
                        continue; // Skip this member, continue to next
                    }

                    Log::info('Sending challenge created notification to member', [
                        'member_id' => $member->id,
                        'challenge_id' => $challenge->id,
                    ]);

                    // Send push notification
                    $result = $this->fcmService->sendToMember(
                        $member,
                        $title,
                        $message,
                        $fcmData
                    );

                    Log::info('FCM send result for member', [
                        'member_id' => $member->id,
                        'success_count' => $result['success_count'] ?? 0,
                        'failed_count' => $result['failed_count'] ?? 0,
                        'result' => $result,
                    ]);

                    if ($result['success_count'] > 0) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }

                    // Save notification to database
                    try {
                        MemberAppsNotification::create([
                            'member_id' => $member->id,
                            'type' => 'challenge_created',
                            'title' => $title,
                            'message' => $message,
                            'url' => '/challenges',
                            'data' => $dbData,
                            'is_read' => false,
                        ]);
                    } catch (\Exception $dbError) {
                        Log::error('Error saving challenge created notification to database', [
                            'member_id' => $member->id,
                            'challenge_id' => $challenge->id,
                            'error' => $dbError->getMessage(),
                        ]);
                        // Continue even if database save fails
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Error sending challenge created notification to member', [
                        'member_id' => $member->id,
                        'challenge_id' => $challenge->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue to next member
                }
            }

            Log::info('Challenge created notification completed', [
                'challenge_id' => $challenge->id,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_members' => $activeMembers->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in SendChallengeCreatedNotification listener', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'challenge_id' => $event->challenge->id ?? null,
            ]);
        }
    }
}
