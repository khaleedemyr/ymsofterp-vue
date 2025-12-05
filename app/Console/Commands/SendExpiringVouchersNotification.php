<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsMemberVoucher;
use App\Services\FCMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendExpiringVouchersNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'member:notify-expiring-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to members who have vouchers expiring in 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring vouchers (expiring in 7 days)...');

        $now = Carbon::now();
        $sevenDaysFromNow = $now->copy()->addDays(7)->endOfDay();
        $sixDaysFromNow = $now->copy()->addDays(6)->startOfDay();

        // Find vouchers expiring in 7 days
        // Only include active vouchers that haven't been used
        $expiringVouchers = MemberAppsMemberVoucher::where('status', 'active')
            ->whereNull('used_at') // Not yet used
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$sixDaysFromNow->toDateString(), $sevenDaysFromNow->toDateString()])
            ->whereHas('member', function ($query) {
                $query->where('is_active', true)
                    ->where('allow_notification', true);
            })
            ->with(['member', 'voucher'])
            ->get();

        if ($expiringVouchers->isEmpty()) {
            $this->info('No expiring vouchers found.');
            Log::info('No expiring vouchers found for notification', [
                'date_range_start' => $sixDaysFromNow->toDateString(),
                'date_range_end' => $sevenDaysFromNow->toDateString(),
            ]);
            return 0;
        }

        // Group by member to avoid sending multiple notifications
        $membersToNotify = collect();

        foreach ($expiringVouchers as $memberVoucher) {
            $memberId = $memberVoucher->member_id;
            if (!$membersToNotify->has($memberId)) {
                $membersToNotify->put($memberId, [
                    'member' => $memberVoucher->member,
                    'expiring_vouchers' => collect(),
                ]);
            }
            $membersToNotify[$memberId]['expiring_vouchers']->push($memberVoucher);
        }

        $this->info("Found {$membersToNotify->count()} member(s) with expiring vouchers.");

        $fcmService = app(FCMService::class);
        $successCount = 0;
        $failedCount = 0;

        foreach ($membersToNotify as $memberId => $data) {
            try {
                $member = $data['member'];
                $expiringVouchers = $data['expiring_vouchers'];

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

                // Count expiring vouchers
                $voucherCount = $expiringVouchers->count();

                // Build notification message (in English)
                $title = 'Voucher Expiring Soon! ⏰';
                
                if ($voucherCount === 1) {
                    $voucher = $expiringVouchers->first();
                    $voucherName = $voucher->voucher ? $voucher->voucher->name : 'Voucher';
                    $message = "Your voucher \"{$voucherName}\" is expiring soon—don't miss out! Use it before it expires.";
                } else {
                    $message = "You have {$voucherCount} vouchers expiring soon—don't miss out! Use them before they expire.";
                }

                $data = [
                    'type' => 'vouchers_expiring',
                    'member_id' => $member->id,
                    'expiring_vouchers_count' => $voucherCount,
                    'expires_in_days' => 7,
                    'action' => 'view_vouchers',
                ];

                // Add details about expiring vouchers
                $data['expiring_vouchers'] = $expiringVouchers->map(function ($mv) {
                    return [
                        'id' => $mv->id,
                        'voucher_id' => $mv->voucher_id,
                        'voucher_name' => $mv->voucher ? $mv->voucher->name : 'Voucher',
                        'voucher_code' => $mv->voucher_code,
                        'serial_code' => $mv->serial_code,
                        'expires_at' => $mv->expires_at ? $mv->expires_at->format('Y-m-d') : null,
                    ];
                })->toArray();

                $this->info("Sending expiring vouchers notification to member {$member->id} ({$member->nama_lengkap})");

                $result = $fcmService->sendToMember(
                    $member,
                    $title,
                    $message,
                    $data
                );

                if ($result['success_count'] > 0) {
                    $successCount++;
                    $this->info("  ✓ Notification sent successfully");
                    
                    Log::info('Expiring vouchers notification sent', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'expiring_vouchers_count' => $voucherCount,
                    ]);
                } else {
                    $failedCount++;
                    $this->warn("  ✗ Failed to send notification");
                    
                    Log::warning('Failed to send expiring vouchers notification', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'result' => $result,
                    ]);
                }

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("  ✗ Error sending notification: {$e->getMessage()}");
                
                Log::error('Error sending expiring vouchers notification', [
                    'member_id' => $memberId ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Total sent: {$successCount}");
        $this->info("Total failed: {$failedCount}");

        Log::info('Expiring vouchers notifications summary', [
            'total_members' => $membersToNotify->count(),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        return 0;
    }
}

