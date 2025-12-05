<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FCMService;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsDeviceToken;

class TestFCMNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test 
                            {--member_id= : Member ID to test}
                            {--device_token= : Device token to test directly}
                            {--device_type=android : Device type (ios or android)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test FCM push notification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fcmService = new FCMService();
        
        $memberId = $this->option('member_id');
        $deviceToken = $this->option('device_token');
        $deviceType = $this->option('device_type');

        $this->info('=== FCM Notification Test ===');
        $this->newLine();

        // Check FCM keys configuration
        $this->info('Checking FCM Configuration...');
        $iosKey = config('services.fcm.ios_key');
        $androidKey = config('services.fcm.android_key');
        $serverKey = config('services.fcm.server_key');

        if ($iosKey) {
            $this->info('✓ FCM iOS Key: ' . substr($iosKey, 0, 20) . '...');
        } else {
            $this->warn('✗ FCM iOS Key: NOT SET');
        }

        if ($androidKey) {
            $this->info('✓ FCM Android Key: ' . substr($androidKey, 0, 20) . '...');
        } else {
            $this->warn('✗ FCM Android Key: NOT SET');
        }

        if ($serverKey) {
            $this->info('✓ FCM Server Key (fallback): ' . substr($serverKey, 0, 20) . '...');
        } else {
            $this->warn('✗ FCM Server Key (fallback): NOT SET');
        }

        $this->newLine();

        // Test with device token directly
        if ($deviceToken) {
            $this->info("Testing with device token directly...");
            $this->info("Device Token: " . substr($deviceToken, 0, 30) . '...');
            $this->info("Device Type: {$deviceType}");
            $this->newLine();

            $result = $fcmService->sendToDevice(
                $deviceToken,
                'Test Notification',
                'Ini adalah test notifikasi FCM. Jika Anda melihat ini, berarti FCM berfungsi dengan baik!',
                ['type' => 'test', 'timestamp' => now()->toIso8601String()],
                null,
                $deviceType
            );

            if ($result) {
                $this->info('✓ Notification sent successfully!');
            } else {
                $this->error('✗ Failed to send notification. Check logs for details.');
            }
            return;
        }

        // Test with member ID
        if ($memberId) {
            $member = MemberAppsMember::find($memberId);
            
            if (!$member) {
                $this->error("Member with ID {$memberId} not found!");
                return;
            }

            $this->info("Testing with Member ID: {$memberId}");
            $this->info("Member Name: {$member->nama_lengkap}");
            $this->info("Allow Notification: " . ($member->allow_notification ? 'Yes' : 'No'));
            $this->newLine();

            // Get device tokens
            $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('is_active', true)
                ->get();

            if ($deviceTokens->isEmpty()) {
                $this->warn('✗ No active device tokens found for this member!');
                $this->info('Please register device token first using the API endpoint.');
                return;
            }

            $this->info("Found {$deviceTokens->count()} active device token(s):");
            foreach ($deviceTokens as $token) {
                $this->info("  - Device Type: {$token->device_type}, Token: " . substr($token->device_token, 0, 30) . '...');
            }
            $this->newLine();

            $this->info('Sending test notification...');
            $result = $fcmService->sendToMember(
                $member,
                'Test Notification',
                'Ini adalah test notifikasi FCM. Jika Anda melihat ini, berarti FCM berfungsi dengan baik!',
                ['type' => 'test', 'timestamp' => now()->toIso8601String()]
            );

            $this->newLine();
            $this->info("Success: {$result['success_count']}");
            $this->info("Failed: {$result['failed_count']}");

            if ($result['success_count'] > 0) {
                $this->info('✓ Notification sent successfully!');
            } else {
                $this->error('✗ Failed to send notification. Check logs for details.');
            }
            return;
        }

        // No options provided, show help
        $this->warn('Please provide either --member_id or --device_token option');
        $this->newLine();
        $this->info('Usage examples:');
        $this->line('  php artisan fcm:test --member_id=1');
        $this->line('  php artisan fcm:test --device_token=YOUR_TOKEN_HERE --device_type=android');
        $this->line('  php artisan fcm:test --device_token=YOUR_TOKEN_HERE --device_type=ios');
    }
}

