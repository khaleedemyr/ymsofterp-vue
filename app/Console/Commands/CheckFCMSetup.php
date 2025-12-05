<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsDeviceToken;

class CheckFCMSetup extends Command
{
    protected $signature = 'fcm:check-setup';
    protected $description = 'Check FCM setup and member device tokens';

    public function handle()
    {
        $this->info('=== FCM Setup Check ===');
        $this->newLine();

        // Check FCM keys
        $iosKey = config('services.fcm.ios_key');
        $androidKey = config('services.fcm.android_key');
        
        $this->info('FCM Configuration:');
        $this->line('  iOS Key: ' . ($iosKey ? '✓ Set' : '✗ Not Set'));
        $this->line('  Android Key: ' . ($androidKey ? '✓ Set' : '✗ Not Set'));
        $this->newLine();

        // Check device tokens
        $totalTokens = MemberAppsDeviceToken::where('is_active', true)->count();
        $membersWithTokens = MemberAppsDeviceToken::where('is_active', true)
            ->distinct('member_id')
            ->count();
        
        // Check for test tokens
        $testTokens = MemberAppsDeviceToken::where('is_active', true)
            ->where(function($query) {
                $query->where('device_token', 'like', 'test_device_%')
                      ->orWhere('device_token', 'like', 'test_%');
            })
            ->count();
        
        $validTokens = $totalTokens - $testTokens;

        $this->info('Device Tokens:');
        $this->line("  Total active tokens: {$totalTokens}");
        $this->line("  Valid FCM tokens: {$validTokens}");
        if ($testTokens > 0) {
            $this->warn("  ⚠️  Test/dummy tokens: {$testTokens} (will be skipped)");
        }
        $this->line("  Members with tokens: {$membersWithTokens}");
        $this->newLine();

        if ($membersWithTokens > 0) {
            $this->info('Sample members with device tokens:');
            $sampleMembers = MemberAppsDeviceToken::where('is_active', true)
                ->with('member')
                ->distinct('member_id')
                ->take(5)
                ->get()
                ->map(function ($token) {
                    return [
                        'member_id' => $token->member_id,
                        'name' => $token->member->nama_lengkap ?? 'N/A',
                        'allow_notification' => $token->member->allow_notification ?? false,
                        'device_type' => $token->device_type,
                    ];
                });

            foreach ($sampleMembers as $member) {
                $allowNotif = $member['allow_notification'] ? '✓' : '✗';
                $this->line("  ID: {$member['member_id']} | Name: {$member['name']} | Allow Notif: {$allowNotif} | Type: {$member['device_type']}");
            }
            $this->newLine();
            $this->info('To test notification, run:');
            $this->line("  php artisan fcm:test --member_id={$sampleMembers->first()['member_id']}");
        } else {
            $this->warn('No active device tokens found!');
            $this->info('Members need to register device token via API first.');
        }
    }
}

