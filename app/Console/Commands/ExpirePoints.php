<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpirePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'points:expire 
                            {--date= : Date to check for expiry (Y-m-d format, defaults to today)}
                            {--dry-run : Show what would be expired without actually expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire points that have passed their expiration date and reduce member point balance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $dryRun = $this->option('dry-run');

        $this->info("Checking for expired points as of {$date->format('Y-m-d')}...");

        // Find all point transactions that are expired but not yet marked as expired
        $expiredTransactions = MemberAppsPointTransaction::where('transaction_type', 'earn')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $date->toDateString())
            ->where('is_expired', false)
            ->where('point_amount', '>', 0)
            ->get();

        if ($expiredTransactions->isEmpty()) {
            $this->info('No expired points found.');
            return 0;
        }

        $this->info("Found {$expiredTransactions->count()} expired point transaction(s).");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(
                ['Member ID', 'Transaction ID', 'Point Amount', 'Expires At', 'Current Balance'],
                $expiredTransactions->map(function($transaction) {
                    $member = MemberAppsMember::find($transaction->member_id);
                    return [
                        $transaction->member_id,
                        $transaction->id,
                        $transaction->point_amount,
                        $transaction->expires_at->format('Y-m-d'),
                        $member ? $member->just_points : 'N/A'
                    ];
                })
            );
            return 0;
        }

        $totalExpired = 0;
        $totalPointsDeducted = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($expiredTransactions as $transaction) {
                try {
                    $member = MemberAppsMember::find($transaction->member_id);
                    
                    if (!$member) {
                        $errors[] = "Member not found for transaction ID: {$transaction->id}";
                        continue;
                    }

                    $pointsToDeduct = $transaction->point_amount;
                    $currentBalance = $member->just_points ?? 0;

                    // Only deduct if member has enough points
                    if ($currentBalance >= $pointsToDeduct) {
                        // Deduct points from member
                        $member->just_points = max(0, $currentBalance - $pointsToDeduct);
                        $member->save();

                        // Mark transaction as expired
                        $transaction->is_expired = true;
                        $transaction->expired_at = now();
                        $transaction->save();

                        // Create expired transaction record for tracking
                        MemberAppsPointTransaction::create([
                            'member_id' => $member->id,
                            'transaction_type' => 'expired',
                            'point_amount' => -$pointsToDeduct, // Negative amount for deduction
                            'transaction_amount' => 0,
                            'reference_id' => $transaction->id, // Reference to original earning transaction
                            'channel' => 'system',
                            'transaction_date' => $date->toDateString(),
                            'description' => "Point expired from transaction #{$transaction->id} (earned on {$transaction->transaction_date->format('Y-m-d')})",
                            'earning_rate' => null,
                            'expires_at' => null,
                            'is_expired' => false,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        $totalExpired++;
                        $totalPointsDeducted += $pointsToDeduct;

                        Log::info('Point expired', [
                            'member_id' => $member->id,
                            'transaction_id' => $transaction->id,
                            'points_deducted' => $pointsToDeduct,
                            'old_balance' => $currentBalance,
                            'new_balance' => $member->just_points,
                            'expires_at' => $transaction->expires_at->format('Y-m-d')
                        ]);
                    } else {
                        // Member doesn't have enough points (might have been redeemed)
                        // Still mark as expired but don't deduct
                        $transaction->is_expired = true;
                        $transaction->expired_at = now();
                        $transaction->save();

                        $totalExpired++;

                        Log::warning('Point transaction expired but member has insufficient balance', [
                            'member_id' => $member->id,
                            'transaction_id' => $transaction->id,
                            'points_should_deduct' => $pointsToDeduct,
                            'current_balance' => $currentBalance
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error processing transaction ID {$transaction->id}: " . $e->getMessage();
                    Log::error('Error expiring point transaction', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            $this->info("Successfully expired {$totalExpired} point transaction(s).");
            $this->info("Total points deducted: {$totalPointsDeducted}");

            if (!empty($errors)) {
                $this->warn("Errors encountered:");
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error expiring points: " . $e->getMessage());
            Log::error('Error in expire points command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

