<?php

namespace App\Listeners;

use App\Events\VoucherReceived;
use App\Services\FCMService;
use App\Models\MemberAppsNotification;
use Illuminate\Support\Facades\Log;

class SendVoucherReceivedNotification
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function handle(VoucherReceived $event): void
    {
        try {
            // Log with timestamp and process ID to help identify duplicate calls
            Log::info('SendVoucherReceivedNotification listener triggered', [
                'member_id' => $event->member->id ?? null,
                'voucher_id' => $event->voucher->id ?? null,
                'member_voucher_id' => $event->memberVoucher->id ?? null,
                'timestamp' => now()->toDateTimeString(),
                'process_id' => getmypid(),
            ]);

            $member = $event->member;
            $voucher = $event->voucher;
            $memberVoucher = $event->memberVoucher;

            // Refresh member to get latest data
            $member->refresh();

            // Skip if member has disabled notifications
            if (!$member->allow_notification) {
                Log::info('Skipping voucher received notification - member has disabled notifications', [
                    'member_id' => $member->id,
                ]);
                return;
            }

            // Prevent duplicate notifications: Check if notification for this member_voucher_id was sent in the last 5 minutes
            // Use JSON_EXTRACT to check data->member_voucher_id in JSON column
            $recentNotification = MemberAppsNotification::where('member_id', $member->id)
                ->where('type', 'voucher_received')
                ->whereRaw('JSON_EXTRACT(data, "$.member_voucher_id") = ?', [$memberVoucher->id])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($recentNotification) {
                Log::info('Skipping duplicate voucher received notification - notification already sent recently', [
                    'member_id' => $member->id,
                    'member_voucher_id' => $memberVoucher->id,
                    'existing_notification_id' => $recentNotification->id,
                    'existing_notification_created_at' => $recentNotification->created_at,
                ]);
                return;
            }

            // Build notification message
            $title = 'New Voucher Available! 🎁';
            $message = "You have new member rewards! Enjoy member-only deals available all day.";

            $data = [
                'type' => 'voucher_received',
                'member_id' => $member->id,
                'voucher_id' => $voucher->id ?? null,
                'voucher_name' => $voucher->name ?? 'Voucher',
                'member_voucher_id' => $memberVoucher->id,
                'voucher_code' => $memberVoucher->voucher_code ?? null,
                'serial_code' => $memberVoucher->serial_code ?? null,
                'expires_at' => $memberVoucher->expires_at ? $memberVoucher->expires_at->format('Y-m-d') : null,
                'action' => 'view_vouchers',
            ];

            Log::info('Sending voucher received notification', [
                'member_id' => $member->id,
                'voucher_id' => $voucher->id ?? null,
                'voucher_name' => $voucher->name ?? null,
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

            Log::info('Voucher received notification result', [
                'member_id' => $member->id,
                'voucher_id' => $voucher->id ?? null,
                'success_count' => $result['success_count'],
                'failed_count' => $result['failed_count'],
            ]);

            // Save notification to database
            // Double-check for duplicate before saving (race condition protection)
            try {
                // Check again right before saving to prevent race condition
                $duplicateCheck = MemberAppsNotification::where('member_id', $member->id)
                    ->where('type', 'voucher_received')
                    ->whereRaw('JSON_EXTRACT(data, "$.member_voucher_id") = ?', [$memberVoucher->id])
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->first();

                if ($duplicateCheck) {
                    Log::warning('Duplicate notification detected right before save - skipping database save', [
                        'member_id' => $member->id,
                        'member_voucher_id' => $memberVoucher->id,
                        'existing_notification_id' => $duplicateCheck->id,
                    ]);
                    return;
                }

                MemberAppsNotification::create([
                    'member_id' => $member->id,
                    'type' => 'voucher_received',
                    'title' => $title,
                    'message' => $message,
                    'url' => '/vouchers',
                    'data' => $data,
                    'is_read' => false,
                ]);
                
                Log::info('Voucher received notification saved to database', [
                    'member_id' => $member->id,
                    'member_voucher_id' => $memberVoucher->id,
                ]);
            } catch (\Exception $dbError) {
                // Check if error is due to duplicate entry
                if (strpos($dbError->getMessage(), 'Duplicate entry') !== false || 
                    strpos($dbError->getMessage(), '1062') !== false) {
                    Log::warning('Duplicate notification detected via database constraint', [
                        'member_id' => $member->id,
                        'member_voucher_id' => $memberVoucher->id,
                        'error' => $dbError->getMessage(),
                    ]);
                } else {
                    Log::error('Error saving voucher received notification to database', [
                        'member_id' => $member->id,
                        'error' => $dbError->getMessage(),
                    ]);
                }
                // Continue even if database save fails
            }

        } catch (\Exception $e) {
            Log::error('Error sending voucher received notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'member_id' => $event->member->id ?? null,
                'voucher_id' => $event->voucher->id ?? null,
            ]);
        }
    }
}

