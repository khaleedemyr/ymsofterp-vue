<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendIncompleteProfileNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member:notify-incomplete-profile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to members who registered 24 hours ago but haven\'t completed their profile';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for members with incomplete profiles...');

        // Get members who registered exactly 24 hours ago (with 1 hour window for flexibility)
        $twentyFourHoursAgo = Carbon::now()->subHours(24);
        $twentyThreeHoursAgo = Carbon::now()->subHours(23);
        $twentyFiveHoursAgo = Carbon::now()->subHours(25);

        // Find members who registered 24 hours ago and don't have photo
        $members = MemberAppsMember::whereBetween('created_at', [$twentyFiveHoursAgo, $twentyThreeHoursAgo])
            ->whereNull('photo')
            ->where('is_active', true)
            ->where('allow_notification', true)
            ->get();

        $this->info("Found {$members->count()} members with incomplete profiles");

        if ($members->isEmpty()) {
            $this->info('No members found with incomplete profiles.');
            return 0;
        }

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($members as $member) {
            try {
                // Check if profile is still incomplete
                if ($member->isProfileComplete()) {
                    $this->warn("Member {$member->id} ({$member->member_id}) already completed profile, skipping...");
                    continue;
                }

                // Check if we already sent notification in the last 24 hours (prevent duplicate)
                // We'll track this by checking if member has received this notification recently
                // For now, we'll send it anyway since this is a scheduled job that runs once

                $this->info("Sending incomplete profile notification to member {$member->id} ({$member->member_id})");

                $result = $fcmService->sendToMember(
                    $member,
                    'Complete Your Profile',
                    'Complete your profile to unlock tailored rewards just for you.',
                    [
                        'type' => 'incomplete_profile',
                        'member_id' => $member->id,
                        'action' => 'complete_profile',
                    ]
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("✓ Notification sent to member {$member->id}");
                    
                    Log::info('Incomplete profile notification sent', [
                        'member_id' => $member->id,
                        'member_member_id' => $member->member_id,
                        'registered_at' => $member->created_at->format('Y-m-d H:i:s'),
                        'hours_since_registration' => Carbon::now()->diffInHours($member->created_at),
                    ]);
                } else {
                    $failedCount++;
                    $this->error("✗ Failed to send notification to member {$member->id}");
                    
                    Log::warning('Failed to send incomplete profile notification', [
                        'member_id' => $member->id,
                        'member_member_id' => $member->member_id,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Error sending notification to member {$member->id}: {$e->getMessage()}");
                
                Log::error('Error sending incomplete profile notification', [
                    'member_id' => $member->id,
                    'member_member_id' => $member->member_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Completed: {$successCount} sent, {$failedCount} failed");

        return 0;
    }
}

