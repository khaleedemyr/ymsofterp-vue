<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberAppsVoucher;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsMemberVoucher;
use App\Models\MemberAppsPointTransaction;
use App\Services\FCMService;
use App\Services\PointEarningService;
use App\Events\VoucherReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DistributeBirthdayVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vouchers:distribute-birthday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribute birthday vouchers to members who have their birthday today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting birthday voucher distribution...');
        Log::info('Birthday voucher distribution started', ['date' => now()->toDateString()]);

        // Get today's date (month and day only, ignore year)
        $today = now();
        $todayMonth = $today->month;
        $todayDay = $today->day;

        // Get all members whose birthday is today (do this first so we can send notifications even if no vouchers)
        $membersWithBirthday = MemberAppsMember::where('is_active', true)
            ->whereNotNull('tanggal_lahir')
            ->whereRaw('MONTH(tanggal_lahir) = ?', [$todayMonth])
            ->whereRaw('DAY(tanggal_lahir) = ?', [$todayDay])
            ->get();

        if ($membersWithBirthday->isEmpty()) {
            $this->info('No members have their birthday today.');
            Log::info('No members have their birthday today', ['date' => $today->toDateString()]);
            return 0;
        }

        $this->info("Found {$membersWithBirthday->count()} member(s) with birthday today.");

        // Get all active birthday vouchers
        $birthdayVouchers = MemberAppsVoucher::where('is_birthday_voucher', true)
            ->where('is_active', true)
            ->get();

        if ($birthdayVouchers->isEmpty()) {
            $this->info('No active birthday vouchers found. Skipping voucher distribution.');
            Log::info('No active birthday vouchers found');
            
            // Still award birthday points and send notifications even if no vouchers
            $this->info("\n=== Awarding Birthday Bonus Points ===");
            $this->awardBirthdayPoints($membersWithBirthday);
            
            $this->info("\n=== Sending Birthday Notifications ===");
            $this->sendBirthdayNotifications($membersWithBirthday);
            
            return 0;
        }

        $this->info("Found {$birthdayVouchers->count()} active birthday voucher(s).");

        $totalDistributed = 0;
        $totalSkipped = 0;

        DB::beginTransaction();
        try {
            foreach ($birthdayVouchers as $voucher) {
                $this->info("Processing voucher: {$voucher->name} (ID: {$voucher->id})");

                foreach ($membersWithBirthday as $member) {
                    // Check if member already has this voucher today (to avoid duplicates)
                    $existingVoucher = MemberAppsMemberVoucher::where('voucher_id', $voucher->id)
                        ->where('member_id', $member->id)
                        ->whereDate('created_at', $today->toDateString())
                        ->first();

                    if ($existingVoucher) {
                        $this->warn("  Member {$member->member_id} ({$member->nama_lengkap}) already has this voucher today. Skipping.");
                        $totalSkipped++;
                        continue;
                    }

                    // Check if member already has an active voucher of this type (if one-time)
                    // For birthday vouchers, we allow one per day, but check if they already have one from today
                    $hasActiveVoucher = MemberAppsMemberVoucher::where('voucher_id', $voucher->id)
                        ->where('member_id', $member->id)
                        ->where('status', 'active')
                        ->whereDate('created_at', $today->toDateString())
                        ->exists();

                    if ($hasActiveVoucher) {
                        $this->warn("  Member {$member->member_id} ({$member->nama_lengkap}) already has an active voucher from today. Skipping.");
                        $totalSkipped++;
                        continue;
                    }

                    // Generate voucher code and serial code
                    $voucherCode = $this->generateVoucherCode($voucher->id, $member->id);
                    $serialCode = $this->generateVoucherSerialCode($voucher->id, $member->id);

                    // Calculate expiration date (if voucher has valid_until, use it; otherwise set to 1 year from now)
                    $expiresAt = null;
                    if ($voucher->valid_until) {
                        $expiresAt = Carbon::parse($voucher->valid_until);
                    } else {
                        $expiresAt = $today->copy()->addYear();
                    }

                    // Create member voucher
                    $memberVoucher = MemberAppsMemberVoucher::create([
                        'voucher_id' => $voucher->id,
                        'member_id' => $member->id,
                        'voucher_code' => $voucherCode,
                        'serial_code' => $serialCode,
                        'status' => 'active',
                        'expires_at' => $expiresAt,
                    ]);

                    // Refresh member and voucher relationships
                    $memberVoucher->load(['member', 'voucher']);

                    // Dispatch event for push notification
                    try {
                        event(new VoucherReceived(
                            $memberVoucher->member,
                            $memberVoucher
                        ));
                    } catch (\Exception $e) {
                        Log::error('Error dispatching VoucherReceived event for birthday voucher', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucher->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $this->info("  ✓ Distributed to member {$member->member_id} ({$member->nama_lengkap})");
                    Log::info('Birthday voucher distributed', [
                        'voucher_id' => $voucher->id,
                        'voucher_name' => $voucher->name,
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'member_voucher_id' => $memberVoucher->id,
                        'serial_code' => $serialCode,
                    ]);

                    $totalDistributed++;
                }
            }

            DB::commit();
            
            // Award birthday bonus points (100 points) to all members with birthday today
            $this->info("\n=== Awarding Birthday Bonus Points ===");
            $this->awardBirthdayPoints($membersWithBirthday);
            
            // Send birthday notifications to all members with birthday today
            $this->info("\n=== Sending Birthday Notifications ===");
            $this->sendBirthdayNotifications($membersWithBirthday);
            
            $this->info("\n=== Summary ===");
            $this->info("Total distributed: {$totalDistributed}");
            $this->info("Total skipped: {$totalSkipped}");
            $this->info('Birthday voucher distribution completed successfully!');

            Log::info('Birthday voucher distribution completed', [
                'total_distributed' => $totalDistributed,
                'total_skipped' => $totalSkipped,
                'vouchers_processed' => $birthdayVouchers->count(),
                'members_processed' => $membersWithBirthday->count(),
            ]);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error distributing birthday vouchers: {$e->getMessage()}");
            Log::error('Birthday voucher distribution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Generate unique voucher code
     */
    private function generateVoucherCode($voucherId, $memberId)
    {
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        return "V{$voucherId}-M{$memberId}-{$random}";
    }

    /**
     * Generate unique 8-character alphanumeric serial code
     */
    private function generateVoucherSerialCode($voucherId, $memberId)
    {
        $maxAttempts = 100;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // Generate 8-character alphanumeric code
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $serialCode = '';
            for ($i = 0; $i < 8; $i++) {
                $serialCode .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Check if serial code already exists
            $exists = MemberAppsMemberVoucher::where('serial_code', $serialCode)->exists();
            if (!$exists) {
                return $serialCode;
            }

            $attempts++;
        }

        throw new \Exception("Failed to generate unique serial code for voucher {$voucherId} and member {$memberId} after {$maxAttempts} attempts");
    }

    /**
     * Award birthday bonus points (100 points) to members
     */
    private function awardBirthdayPoints($members)
    {
        if ($members->isEmpty()) {
            return;
        }

        $pointService = app(PointEarningService::class);
        $today = now();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();
        
        $totalAwarded = 0;
        $totalSkipped = 0;

        foreach ($members as $member) {
            try {
                // Check if birthday bonus already given this year
                $existingBonus = MemberAppsPointTransaction::where('member_id', $member->id)
                    ->where('transaction_type', 'bonus')
                    ->where('channel', 'birthday')
                    ->whereBetween('transaction_date', [$yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')])
                    ->first();

                if ($existingBonus) {
                    $this->warn("  Member {$member->member_id} ({$member->nama_lengkap}) already received birthday points this year. Skipping.");
                    $totalSkipped++;
                    Log::info('Birthday bonus already given this year', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'year' => $today->year
                    ]);
                    continue;
                }

                // Award birthday bonus points (100 points)
                $result = $pointService->earnBonusPoints($member->id, 'birthday');

                if ($result) {
                    $totalAwarded++;
                    $this->info("  ✓ Awarded 100 birthday points to member {$member->member_id} ({$member->nama_lengkap})");
                    Log::info('Birthday bonus points awarded', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'points' => 100,
                        'year' => $today->year,
                    ]);
                } else {
                    $totalSkipped++;
                    $this->warn("  ✗ Failed to award birthday points to member {$member->member_id} ({$member->nama_lengkap})");
                    Log::warning('Failed to award birthday bonus points', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                    ]);
                }

            } catch (\Exception $e) {
                $totalSkipped++;
                $this->error("  ✗ Error awarding birthday points to member {$member->member_id} ({$member->nama_lengkap}): {$e->getMessage()}");
                Log::error('Error awarding birthday bonus points', [
                    'member_id' => $member->id ?? null,
                    'member_name' => $member->nama_lengkap ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Birthday points: {$totalAwarded} awarded, {$totalSkipped} skipped");
        
        Log::info('Birthday points summary', [
            'total_members' => $members->count(),
            'total_awarded' => $totalAwarded,
            'total_skipped' => $totalSkipped,
        ]);
    }

    /**
     * Send birthday notifications to members
     */
    private function sendBirthdayNotifications($members)
    {
        if ($members->isEmpty()) {
            return;
        }

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($members as $member) {
            try {
                // Skip if member has disabled notifications
                if (!$member->allow_notification) {
                    $this->warn("  Member {$member->member_id} ({$member->nama_lengkap}) has disabled notifications. Skipping.");
                    continue;
                }

                // Refresh member to get latest data
                $member->refresh();

                $this->info("  Sending birthday notification to member {$member->member_id} ({$member->nama_lengkap})");

                $result = $fcmService->sendToMember(
                    $member,
                    'Happy Birthday! 🎉',
                    'Happy Birthday! Celebrate with your exclusive birthday reward.',
                    [
                        'type' => 'birthday',
                        'member_id' => $member->id,
                        'action' => 'view_rewards',
                    ]
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("    ✓ Notification sent successfully");
                    
                    Log::info('Birthday notification sent', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'birthday_date' => $member->tanggal_lahir ? $member->tanggal_lahir->format('Y-m-d') : null,
                    ]);
                } else {
                    $failedCount++;
                    $this->warn("    ✗ Failed to send notification");
                    
                    Log::warning('Failed to send birthday notification', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("    ✗ Error sending notification: {$e->getMessage()}");
                
                Log::error('Error sending birthday notification', [
                    'member_id' => $member->id ?? null,
                    'member_name' => $member->nama_lengkap ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Birthday notifications: {$successCount} sent, {$failedCount} failed");
        
        Log::info('Birthday notifications summary', [
            'total_members' => $members->count(),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }
}

