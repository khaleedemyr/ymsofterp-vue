<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendMonthlyInactiveMemberNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member:notify-monthly-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to members who haven\'t made a transaction in the last 1 month (30 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for monthly inactive members (no transaction in 30 days)...');

        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();
        $twentyNineDaysAgo = $now->copy()->subDays(29)->startOfDay();
        $thirtyOneDaysAgo = $now->copy()->subDays(31)->startOfDay();

        // Find members who:
        // 1. Are active
        // 2. Allow notifications
        // 3. Have at least one transaction (to avoid notifying new members)
        // 4. Last transaction was exactly 30 days ago (with 2 day window: 29-31 days)
        //    This ensures we send notification once, approximately 30 days after last transaction
        
        // Get members with their last transaction date
        $members = DB::select("
            SELECT 
                m.id,
                m.member_id,
                m.nama_lengkap,
                m.allow_notification,
                MAX(o.created_at) as last_transaction_date
            FROM member_apps_members m
            INNER JOIN orders o ON o.member_id = m.id AND o.status = 'paid'
            WHERE m.is_active = 1
                AND m.allow_notification = 1
                AND o.member_id IS NOT NULL
            GROUP BY m.id, m.member_id, m.nama_lengkap, m.allow_notification
            HAVING DATE(last_transaction_date) >= ? 
                AND DATE(last_transaction_date) <= ?
        ", [
            $thirtyOneDaysAgo->toDateString(),
            $twentyNineDaysAgo->toDateString()
        ]);

        if (empty($members)) {
            $this->info('No monthly inactive members found (no transaction in 30 days).');
            Log::info('No monthly inactive members found for notification', [
                'date_range_start' => $thirtyOneDaysAgo->toDateString(),
                'date_range_end' => $twentyNineDaysAgo->toDateString(),
            ]);
            return 0;
        }

        $this->info("Found " . count($members) . " member(s) with no transaction in 30 days.");

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($members as $memberData) {
            try {
                $member = MemberAppsMember::find($memberData->id);

                if (!$member) {
                    $this->warn("Member {$memberData->id} not found, skipping...");
                    continue;
                }

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

                // Verify last transaction is still 30 days ago (to avoid duplicate notifications)
                $lastTransaction = DB::table('orders')
                    ->where('member_id', $member->id)
                    ->where('status', 'paid')
                    ->whereNotNull('member_id')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$lastTransaction) {
                    $this->warn("Member {$member->id} has no transactions, skipping...");
                    continue;
                }

                $lastTransactionDate = Carbon::parse($lastTransaction->created_at);
                $daysSinceLastTransaction = $now->diffInDays($lastTransactionDate, false); // false = don't round

                // Only send if exactly 30 days (with 2 day window: 29-31 days)
                // This ensures we send notification once, approximately 30 days after last transaction
                if ($daysSinceLastTransaction < 29 || $daysSinceLastTransaction > 31) {
                    $this->warn("Member {$member->id} last transaction was {$daysSinceLastTransaction} days ago, not in 29-31 day window, skipping...");
                    continue;
                }

                $this->info("Sending monthly inactive member notification to member {$member->id} ({$member->nama_lengkap})");

                $result = $fcmService->sendToMember(
                    $member,
                    'We Miss You! 💙',
                    'We miss you! Drop by today—your favorite dish is waiting.',
                    [
                        'type' => 'monthly_inactive_member',
                        'member_id' => $member->id,
                        'days_since_last_transaction' => $daysSinceLastTransaction,
                        'last_transaction_date' => $lastTransactionDate->format('Y-m-d'),
                        'action' => 'view_menu',
                    ]
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("  ✓ Notification sent successfully");
                    
                    Log::info('Monthly inactive member notification sent', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'last_transaction_date' => $lastTransactionDate->format('Y-m-d H:i:s'),
                        'days_since_last_transaction' => $daysSinceLastTransaction,
                    ]);
                } else {
                    $failedCount++;
                    $this->warn("  ✗ Failed to send notification");
                    
                    Log::warning('Failed to send monthly inactive member notification', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("  ✗ Error sending notification: {$e->getMessage()}");
                
                Log::error('Error sending monthly inactive member notification', [
                    'member_id' => $memberData->id ?? null,
                    'member_name' => $memberData->nama_lengkap ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total sent: {$successCount}");
        $this->info("Total failed: {$failedCount}");

        Log::info('Monthly inactive member notifications summary', [
            'total_members' => count($members),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        return 0;
    }
}

