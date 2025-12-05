<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsChallengeProgress;
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
    protected $description = 'Send notification to members who have points or challenge rewards expiring in 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring points and rewards (expiring in 7 days)...');

        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $sixDaysFromNow = $now->copy()->addDays(6)->startOfDay();

        // Find members with points expiring in 7 days
        // Only include points that are not fully redeemed (have remaining_points > 0)
        $expiringPoints = MemberAppsPointTransaction::where('transaction_type', 'earn')
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$sixDaysFromNow->toDateString(), $sevenDaysFromNow->toDateString()])
            ->whereHas('earning', function ($query) {
                // Only include points that are not fully redeemed
                $query->where('is_fully_redeemed', false)
                    ->where('remaining_points', '>', 0);
            })
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'earning'])
            ->get();

        // Find members with challenge rewards expiring in 7 days
        $expiringRewards = MemberAppsChallengeProgress::where('is_completed', true)
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

        // Group by member to avoid sending multiple notifications
        $membersToNotify = collect();

        // Process expiring points
        foreach ($expiringPoints as $pointTransaction) {
            $memberId = $pointTransaction->member_id;
            if (!$membersToNotify->has($memberId)) {
                $membersToNotify->put($memberId, [
                    'member' => $pointTransaction->member,
                    'expiring_points' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify[$memberId]['expiring_points']->push($pointTransaction);
        }

        // Process expiring rewards
        foreach ($expiringRewards as $reward) {
            $memberId = $reward->member_id;
            if (!$membersToNotify->has($memberId)) {
                $membersToNotify->put($memberId, [
                    'member' => $reward->member,
                    'expiring_points' => collect(),
                    'expiring_rewards' => collect(),
                ]);
            }
            $membersToNotify[$memberId]['expiring_rewards']->push($reward);
        }

        if ($membersToNotify->isEmpty()) {
            $this->info('No members found with expiring points or rewards.');
            Log::info('No expiring points or rewards found for notification', [
                'date_range_start' => $sixDaysFromNow->toDateString(),
                'date_range_end' => $sevenDaysFromNow->toDateString(),
            ]);
            return 0;
        }

        $this->info("Found {$membersToNotify->count()} member(s) with expiring points or rewards.");

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($membersToNotify as $memberId => $data) {
            try {
                $member = $data['member'];
                $expiringPoints = $data['expiring_points'];
                $expiringRewards = $data['expiring_rewards'];

                // Refresh member to get latest data
                $member->refresh();

                // Double check: ensure member still allows notifications
                if (!$member->allow_notification) {
                    $this->warn("Member {$member->id} has disabled notifications, skipping...");
                    continue;
                }

                // Double check: ensure member is still active
                if (!$member->is_active) {
                    $this->warn("Member {$member->id} is not active, skipping...");
                    continue;
                }

                // Calculate total expiring points (only count remaining points, not fully redeemed)
                $totalExpiringPoints = $expiringPoints->sum(function ($pt) {
                    return $pt->earning ? $pt->earning->remaining_points : $pt->point_amount;
                });

                // Build notification message
                $title = 'Points Expiring Soon! ⏰';
                $message = "Your Just-Points are about to expire—redeem them before they're gone.";

                // If there are also expiring rewards, mention them
                if ($expiringRewards->isNotEmpty()) {
                    $rewardCount = $expiringRewards->count();
                    if ($totalExpiringPoints > 0) {
                        $message = "You have {$totalExpiringPoints} Just-Points and {$rewardCount} reward(s) expiring soon—redeem them before they're gone.";
                    } else {
                        $message = "You have {$rewardCount} reward(s) expiring soon—redeem them before they're gone.";
                    }
                } elseif ($totalExpiringPoints > 0) {
                    $message = "You have {$totalExpiringPoints} Just-Points expiring soon—redeem them before they're gone.";
                }

                $data = [
                    'type' => 'points_expiring',
                    'member_id' => $member->id,
                    'total_expiring_points' => $totalExpiringPoints,
                    'expiring_rewards_count' => $expiringRewards->count(),
                    'expires_in_days' => 7,
                    'action' => 'view_rewards',
                ];

                // Add details about expiring items
                if ($expiringPoints->isNotEmpty()) {
                    $data['expiring_point_transactions'] = $expiringPoints->map(function ($pt) {
                        $remainingPoints = $pt->earning ? $pt->earning->remaining_points : $pt->point_amount;
                        return [
                            'id' => $pt->id,
                            'points' => $remainingPoints,
                            'original_points' => $pt->point_amount,
                            'expires_at' => $pt->expires_at ? $pt->expires_at->format('Y-m-d') : null,
                        ];
                    })->toArray();
                }

                if ($expiringRewards->isNotEmpty()) {
                    $data['expiring_rewards'] = $expiringRewards->map(function ($reward) {
                        return [
                            'id' => $reward->id,
                            'challenge_id' => $reward->challenge_id,
                            'challenge_title' => $reward->challenge ? $reward->challenge->title : null,
                            'expires_at' => $reward->reward_expires_at ? $reward->reward_expires_at->format('Y-m-d H:i:s') : null,
                        ];
                    })->toArray();
                }

                $this->info("Sending expiring points notification to member {$member->id} ({$member->nama_lengkap})");

                $result = $fcmService->sendToMember(
                    $member,
                    $title,
                    $message,
                    $data
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("  ✓ Notification sent successfully");
                    
                    Log::info('Expiring points notification sent', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'total_expiring_points' => $totalExpiringPoints,
                        'expiring_rewards_count' => $expiringRewards->count(),
                    ]);
                } else {
                    $failedCount++;
                    $this->warn("  ✗ Failed to send notification");
                    
                    Log::warning('Failed to send expiring points notification', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("  ✗ Error sending notification: {$e->getMessage()}");
                
                Log::error('Error sending expiring points notification', [
                    'member_id' => $memberId ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total sent: {$successCount}");
        $this->info("Total failed: {$failedCount}");

        Log::info('Expiring points notifications summary', [
            'total_members' => $membersToNotify->count(),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        return 0;
    }
}

