<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNotificationPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification-push {user_id=26}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test push notification by creating a notification for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("Creating test notification for user ID: {$userId}");
        
        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => 'Test Push Notification',
                'message' => 'Ini adalah test notification untuk push notification ke mobile app. Jika Anda melihat ini di mobile app, berarti push notification berhasil!',
                'type' => 'test',
                'approval_id' => null,
                'task_id' => null,
                'url' => null,
                'is_read' => false,
            ]);
            
            $this->info("✅ Notification created successfully!");
            $this->info("   Notification ID: {$notification->id}");
            $this->info("   User ID: {$userId}");
            $this->info("");
            $this->info("📱 Check mobile app for push notification!");
            $this->info("📋 Check logs at storage/logs/laravel.log for details");
            $this->info("");
            $this->info("Log details:");
            $this->info("   - Look for 'NotificationObserver: Sending FCM notification'");
            $this->info("   - Look for 'NotificationObserver: FCM notification sent'");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error creating notification: " . $e->getMessage());
            Log::error('Test notification push failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}

