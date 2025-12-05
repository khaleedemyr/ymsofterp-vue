<?php

namespace App\Console\Commands;

use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsMember;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendIncompleteChallengeNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member:notify-incomplete-challenge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to members who started a challenge but haven\'t completed it within 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for members with incomplete challenges...');

        // Get challenge progresses that:
        // 1. Not completed (is_completed = false)
        // 2. Has started_at (challenge has been started)
        // 3. Started 24 hours ago (or multiple of 24 hours) with 1 hour window for flexibility
        $now = Carbon::now();
        $twentyThreeHoursAgo = $now->copy()->subHours(23);
        $twentyFiveHoursAgo = $now->copy()->subHours(25);

        // Find all incomplete challenge progresses
        // Filter at query level to avoid processing completed or expired challenges
        $allProgresses = MemberAppsChallengeProgress::where('is_completed', false)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $twentyThreeHoursAgo) // At least 23 hours since start
            ->whereHas('challenge', function ($query) use ($now) {
                // Only include challenges that are still active and not expired
                $query->where('is_active', true)
                    ->where(function ($q) use ($now) {
                        // end_date is a date field, so compare with today's date
                        // Challenge is valid if end_date is null or end_date >= today
                        $q->whereNull('end_date')
                          ->orWhereDate('end_date', '>=', $now->toDateString());
                    });
            })
            ->whereHas('member', function ($query) {
                // Only include members who allow notifications
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'challenge'])
            ->get();

        // Filter progresses that are at 24-hour intervals (24h, 48h, 72h, etc.)
        $challengeProgresses = $allProgresses->filter(function ($progress) use ($now) {
            if (!$progress->started_at) {
                return false;
            }
            
            // Double check: ensure progress is still incomplete (safety check)
            if ($progress->is_completed) {
                return false;
            }
            
            // Double check: ensure challenge is still active and not expired
            if (!$progress->challenge || !$progress->challenge->is_active) {
                return false;
            }
            
            // Check if challenge end_date has passed (end_date is a date field, so compare with today)
            if ($progress->challenge->end_date) {
                $endDate = Carbon::parse($progress->challenge->end_date)->endOfDay();
                if ($now->isAfter($endDate)) {
                    return false;
                }
            }
            
            $hoursSinceStart = $now->diffInHours($progress->started_at);
            
            // Check if it's approximately a multiple of 24 hours (with 1 hour window)
            // e.g., 23-25 hours, 47-49 hours, 71-73 hours, etc.
            $hoursMod24 = $hoursSinceStart % 24;
            
            // Allow 23-25 hour window (1 hour before and after exact 24-hour mark)
            return $hoursMod24 >= 23 || $hoursMod24 <= 1;
        });

        $this->info("Found {$challengeProgresses->count()} incomplete challenges ready for notification");

        if ($challengeProgresses->isEmpty()) {
            $this->info('No incomplete challenges found.');
            return 0;
        }

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($challengeProgresses as $progress) {
            try {
                // Final safety checks (should already be filtered, but double-check for safety)
                if (!$progress->challenge || !$progress->member) {
                    $this->warn("Challenge or member not found for progress ID {$progress->id}, skipping...");
                    continue;
                }

                // Final check: ensure progress is still incomplete
                // Refresh from database to get latest status
                $progress->refresh();
                if ($progress->is_completed) {
                    $this->warn("Progress {$progress->id} is already completed (status changed), skipping...");
                    continue;
                }

                $challenge = $progress->challenge;
                
                // Final check: ensure challenge is still active and not expired
                $challenge->refresh();
                if (!$challenge->is_active) {
                    $this->warn("Challenge {$challenge->id} is not active (status changed), skipping...");
                    continue;
                }
                
                // Check if challenge end_date has passed (end_date is a date field, so compare with today)
                if ($challenge->end_date) {
                    $endDate = Carbon::parse($challenge->end_date)->endOfDay();
                    if ($now->isAfter($endDate)) {
                        $this->warn("Challenge {$challenge->id} has expired (status changed), skipping...");
                        continue;
                    }
                }

                // Final check: ensure member still allows notifications
                $progress->member->refresh();
                if (!$progress->member->allow_notification) {
                    $this->warn("Member {$progress->member->id} has disabled notifications (status changed), skipping...");
                    continue;
                }

                $this->info("Sending incomplete challenge notification to member {$progress->member->id} for challenge {$challenge->id}");

                $result = $fcmService->sendToMember(
                    $progress->member,
                    'Complete Your Challenge! 🎯',
                    'Let\'s complete your challenge! You\'re almost there—come visit us!',
                    [
                        'type' => 'incomplete_challenge',
                        'challenge_id' => $challenge->id,
                        'challenge_title' => $challenge->title,
                        'progress_id' => $progress->id,
                        'action' => 'view_challenge',
                    ]
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("✓ Notification sent to member {$progress->member->id} for challenge {$challenge->id}");
                    
                    Log::info('Incomplete challenge notification sent', [
                        'member_id' => $progress->member->id,
                        'challenge_id' => $challenge->id,
                        'progress_id' => $progress->id,
                        'challenge_title' => $challenge->title,
                        'started_at' => $progress->started_at ? $progress->started_at->format('Y-m-d H:i:s') : null,
                        'last_updated' => $progress->updated_at->format('Y-m-d H:i:s'),
                        'hours_since_start' => $progress->started_at ? Carbon::now()->diffInHours($progress->started_at) : null,
                    ]);
                } else {
                    $failedCount++;
                    $this->error("✗ Failed to send notification to member {$progress->member->id}");
                    
                    Log::warning('Failed to send incomplete challenge notification', [
                        'member_id' => $progress->member->id,
                        'challenge_id' => $challenge->id,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Error sending notification: {$e->getMessage()}");
                
                Log::error('Error sending incomplete challenge notification', [
                    'progress_id' => $progress->id ?? null,
                    'member_id' => $progress->member->id ?? null,
                    'challenge_id' => $progress->challenge_id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Completed: {$successCount} sent, {$failedCount} failed");

        return 0;
    }
}

