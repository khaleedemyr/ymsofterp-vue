<?php

namespace App\Listeners;

use App\Events\VoucherReceived;
use App\Services\FCMService;
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
            Log::info('SendVoucherReceivedNotification listener triggered', [
                'member_id' => $event->member->id ?? null,
                'voucher_id' => $event->voucher->id ?? null,
                'member_voucher_id' => $event->memberVoucher->id ?? null,
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

            // Send notification
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

