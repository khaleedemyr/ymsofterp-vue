<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsNotification;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendExpiringPointsNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member:notify-expiring-points';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to members who have points or challenge rewards expiring in 14 days (2 weeks) or 7 days (1 week)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring points and rewards (expiring in 14 days / 2 weeks and 7 days / 1 week)...');

        $now = Carbon::now();
        
        // Calculate date ranges for both notification periods
        // 14 days before expiration
        $fourteenDaysFromNow = $now->copy()->addDays(14)->endOfDay();
        $thirteenDaysFromNow = $now->copy()->addDays(13)->startOfDay();
        
        // 7 days before expiration
        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $sixDaysFromNow = $now->copy()->addDays(6)->startOfDay();

        // Find members with points expiring in 14 days (2 weeks) OR 7 days (1 week)
        // Query directly from MemberAppsPointEarning for more accuracy
        // Only include points that are not fully redeemed (have remaining_points > 0)
        $expiringPointEarnings14Days = MemberAppsPointEarning::where('is_expired', false)
            ->where('is_fully_redeemed', false)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$thirteenDaysFromNow->toDateString(), $fourteenDaysFromNow->toDateString()])
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'transaction'])
            ->get();

        $expiringPointEarnings7Days = MemberAppsPointEarning::where('is_expired', false)
            ->where('is_fully_redeemed', false)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$sixDaysFromNow->toDateString(), $sevenDaysFromNow->toDateString()])
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'transaction'])
            ->get();

        // Find members with challenge rewards expiring in 14 days (2 weeks) OR 7 days (1 week)
        $expiringRewards14Days = MemberAppsChallengeProgress::where('is_completed', true)
            ->where('reward_claimed', true)
            ->whereNull('reward_redeemed_at') // Not yet redeemed
            ->whereNotNull('reward_expires_at')
            ->whereRaw('DATE(reward_expires_at) >= ?', [$thirteenDaysFromNow->toDateString()])
            ->whereRaw('DATE(reward_expires_at) <= ?', [$fourteenDaysFromNow->toDateString()])
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'challenge'])
            ->get();

        $expiringRewards7Days = MemberAppsChallengeProgress::where('is_completed', true)
            ->where('reward_claimed', true)
            ->whereNull('reward_redeemed_at') // Not yet redeemed
            ->whereNotNull('reward_expires_at')
            ->whereRaw('DATE(reward_expires_at) >= ?', [$sixDaysFromNow->toDateString()])
            ->whereRaw('DATE(reward_expires_at) <= ?', [$sevenDaysFromNow->toDateString()])
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'challenge'])
            ->get();

        // Group by member and expiration period (14 days or 7 days)
        $membersToNotify14Days = collect();
        $membersToNotify7Days = collect();

        // Process expiring points for 14 days
        foreach ($expiringPointEarnings14Days as $pointEarning) {
            $memberId = $pointEarning->member_id;
            if (!$membersToNotify14Days->has($memberId)) {
                $membersToNotify14Days->put($memberId, [
                    'member' => $pointEarning->member,
                    'expiring_point_earnings' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify14Days[$memberId]['expiring_point_earnings']->push($pointEarning);
        }

        // Process expiring points for 7 days
        foreach ($expiringPointEarnings7Days as $pointEarning) {
            $memberId = $pointEarning->member_id;
            if (!$membersToNotify7Days->has($memberId)) {
                $membersToNotify7Days->put($memberId, [
                    'member' => $pointEarning->member,
                    'expiring_point_earnings' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify7Days[$memberId]['expiring_point_earnings']->push($pointEarning);
        }

        // Process expiring rewards for 14 days
        foreach ($expiringRewards14Days as $reward) {
            $memberId = $reward->member_id;
            if (!$membersToNotify14Days->has($memberId)) {
                $membersToNotify14Days->put($memberId, [
                    'member' => $reward->member,
                    'expiring_point_earnings' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify14Days[$memberId]['expiring_rewards']->push($reward);
        }

        // Process expiring rewards for 7 days
        foreach ($expiringRewards7Days as $reward) {
            $memberId = $reward->member_id;
            if (!$membersToNotify7Days->has($memberId)) {
                $membersToNotify7Days->put($memberId, [
                    'member' => $reward->member,
                    'expiring_point_earnings' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify7Days[$memberId]['expiring_rewards']->push($reward);
        }

        $totalMembers14Days = $membersToNotify14Days->count();
        $totalMembers7Days = $membersToNotify7Days->count();

        if ($totalMembers14Days === 0 && $totalMembers7Days === 0) {
            $this->info('No members found with expiring points or rewards.');
            Log::info('No expiring points or rewards found for notification', [
                'date_range_14_days' => [
                    'start' => $thirteenDaysFromNow->toDateString(),
                    'end' => $fourteenDaysFromNow->toDateString(),
                ],
                'date_range_7_days' => [
                    'start' => $sixDaysFromNow->toDateString(),
                    'end' => $sevenDaysFromNow->toDateString(),
                ],
            ]);
            return 0;
        }

        $this->info("Found {$totalMembers14Days} member(s) with points/rewards expiring in 14 days.");
        $this->info("Found {$totalMembers7Days} member(s) with points/rewards expiring in 7 days.");

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        // Send notifications for 14 days expiration
        $this->info("\n=== Sending notifications for 14 days expiration ===");
        foreach ($membersToNotify14Days as $memberId => $data) {
            $this->sendNotification($fcmService, $memberId, $data, 14, $successCount, $failedCount);
        }

        // Send notifications for 7 days expiration
        $this->info("\n=== Sending notifications for 7 days expiration ===");
        foreach ($membersToNotify7Days as $memberId => $data) {
            $this->sendNotification($fcmService, $memberId, $data, 7, $successCount, $failedCount);
        }

        $this->info("\n=== Summary ===");
        $this->info("Total sent: {$successCount}");
        $this->info("Total failed: {$failedCount}");

        Log::info('Expiring points notifications summary', [
            'total_members_14_days' => $totalMembers14Days,
            'total_members_7_days' => $totalMembers7Days,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        return 0;
    }

    /**
     * Send notification to a member for expiring points/rewards
     */
    private function sendNotification($fcmService, $memberId, $data, $daysUntilExpiration, &$successCount, &$failedCount)
    {
        try {
            $member = $data['member'];
            $expiringPointEarnings = $data['expiring_point_earnings'] ?? collect();
            $expiringRewards = $data['expiring_rewards'] ?? collect();

            // Refresh member to get latest data
            $member->refresh();

            // Double check: ensure member still allows notifications
            if (!$member->allow_notification) {
                $this->warn("Member {$member->id} has disabled notifications, skipping...");
                return;
            }

            // Double check: ensure member is still active
            if (!$member->is_active) {
                $this->warn("Member {$member->id} is not active, skipping...");
                return;
            }

            // Calculate total expiring points (only count remaining points, not fully redeemed)
            $totalExpiringPoints = $expiringPointEarnings->sum(function ($earning) {
                return $earning->remaining_points ?? 0;
            });

            // Check for duplicate notification for this specific expiration period
            // Check if notification was already sent today for this member with the same days_until_expiration
            $recentNotification = MemberAppsNotification::where('member_id', $member->id)
                ->where('type', 'points_expiring')
                ->whereRaw("JSON_EXTRACT(data, '$.expires_in_days') = ?", [$daysUntilExpiration])
                ->whereDate('created_at', '>=', now()->startOfDay())
                ->first();

            if ($recentNotification) {
                $this->info("  ⚠ Skipping - notification already sent today for {$daysUntilExpiration} days expiration to member {$member->id}");
                Log::info('Duplicate expiring points notification prevented', [
                    'member_id' => $member->id,
                    'days_until_expiration' => $daysUntilExpiration,
                    'existing_notification_id' => $recentNotification->id,
                    'existing_notification_created_at' => $recentNotification->created_at,
                ]);
                return; // Skip this member for this period
            }

            // Build notification message based on days until expiration
            $title = 'Points Expiring Soon! ⏰';
            $timeText = $daysUntilExpiration === 14 ? '2 weeks' : '1 week';
            $message = "Your Just-Points will expire in {$timeText}—redeem them before they're gone.";

            // If there are also expiring rewards, mention them
            if ($expiringRewards->isNotEmpty()) {
                $rewardCount = $expiringRewards->count();
                if ($totalExpiringPoints > 0) {
                    $message = "You have {$totalExpiringPoints} Just-Points and {$rewardCount} reward(s) expiring in {$timeText}—redeem them before they're gone.";
                } else {
                    $message = "You have {$rewardCount} reward(s) expiring in {$timeText}—redeem them before they're gone.";
                }
            } elseif ($totalExpiringPoints > 0) {
                $message = "You have {$totalExpiringPoints} Just-Points expiring in {$timeText}—redeem them before they're gone.";
            }

            $notificationData = [
                'type' => 'points_expiring',
                'member_id' => $member->id,
                'total_expiring_points' => $totalExpiringPoints,
                'expiring_rewards_count' => $expiringRewards->count(),
                'expires_in_days' => $daysUntilExpiration,
                'action' => 'view_rewards',
            ];

            // Add details about expiring items
            if ($expiringPointEarnings->isNotEmpty()) {
                $notificationData['expiring_point_earnings'] = $expiringPointEarnings->map(function ($earning) {
                    return [
                        'id' => $earning->id,
                        'point_earning_id' => $earning->id,
                        'points' => $earning->remaining_points ?? 0,
                        'original_points' => $earning->point_amount ?? 0,
                        'expires_at' => $earning->expires_at ? (is_string($earning->expires_at) ? $earning->expires_at : $earning->expires_at->format('Y-m-d')) : null,
                        'transaction_id' => $earning->point_transaction_id,
                    ];
                })->toArray();
            }

            if ($expiringRewards->isNotEmpty()) {
                $notificationData['expiring_rewards'] = $expiringRewards->map(function ($reward) {
                    return [
                        'id' => $reward->id,
                        'challenge_id' => $reward->challenge_id,
                        'challenge_title' => $reward->challenge ? $reward->challenge->title : null,
                        'expires_at' => $reward->reward_expires_at ? $reward->reward_expires_at->format('Y-m-d H:i:s') : null,
                    ];
                })->toArray();
            }

            $this->info("Sending expiring points notification ({$daysUntilExpiration} days) to member {$member->id} ({$member->nama_lengkap})");

            // Prepare FCM data (all values must be strings)
            $fcmData = [];
            foreach ($notificationData as $key => $value) {
                if (is_array($value)) {
                    $fcmData[$key] = json_encode($value);
                } else {
                    $fcmData[$key] = (string)$value;
                }
            }

            $result = $fcmService->sendToMember(
                $member,
                $title,
                $message,
                $fcmData
            );

            if ($result['success_count'] > 0) {
                $successCount++;
                $this->info("  ✓ Notification sent successfully");
                
                // Save notification to database
                try {
                    MemberAppsNotification::create([
                        'member_id' => $member->id,
                        'type' => 'points_expiring',
                        'title' => $title,
                        'message' => $message,
                        'url' => '/rewards',
                        'data' => $notificationData, // Use notificationData (with arrays) for database
                        'is_read' => false,
                    ]);
                } catch (\Exception $dbError) {
                    Log::error('Error saving expiring points notification to database', [
                        'member_id' => $member->id,
                        'error' => $dbError->getMessage(),
                    ]);
                    // Continue even if database save fails
                }
                
                Log::info('Expiring points notification sent', [
                    'member_id' => $member->id,
                    'member_name' => $member->nama_lengkap,
                    'days_until_expiration' => $daysUntilExpiration,
                    'total_expiring_points' => $totalExpiringPoints,
                    'expiring_rewards_count' => $expiringRewards->count(),
                ]);
            } else {
                $failedCount++;
                $this->warn("  ✗ Failed to send notification");
                
                Log::warning('Failed to send expiring points notification', [
                    'member_id' => $member->id,
                    'member_name' => $member->nama_lengkap,
                    'days_until_expiration' => $daysUntilExpiration,
                    'result' => $result,
                ]);
            }

        } catch (\Exception $e) {
            $failedCount++;
            $this->error("  ✗ Error sending notification: {$e->getMessage()}");
            
            Log::error('Error sending expiring points notification', [
                'member_id' => $memberId ?? null,
                'days_until_expiration' => $daysUntilExpiration ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

