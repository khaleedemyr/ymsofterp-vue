<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\FCMService;
use App\Models\WebDeviceToken;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsDeviceToken;
use Illuminate\Support\Facades\Log;

class NotificationObserver
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Handle the Notification "created" event.
     * Send FCM push notification when a notification is created
     * This runs immediately for real-time delivery (not queued, not delayed)
     */
    public function created(Notification $notification)
    {
        // DISABLED: Notification service temporarily disabled
        return;
        
        try {
            // Skip if notification doesn't have user_id
            if (!$notification->user_id) {
                Log::debug('NotificationObserver: Skipping - no user_id', [
                    'notification_id' => $notification->id,
                ]);
                return;
            }

            // Get user
            $user = $notification->user;
            if (!$user) {
                Log::warning('NotificationObserver: User not found', [
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id,
                ]);
                return;
            }

            // Get all active web device tokens for this user
            $deviceTokens = WebDeviceToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('device_token')
                ->toArray();

            if (empty($deviceTokens)) {
                Log::debug('NotificationObserver: No active device tokens found', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return;
            }

            // Prepare notification data
            $title = $notification->title ?? 'New Notification';
            $message = $notification->message ?? '';
            $data = [
                'type' => $notification->type ?? 'general',
                'notification_id' => $notification->id,
                'url' => $notification->url ?? null,
                'task_id' => $notification->task_id ?? null,
                'approval_id' => $notification->approval_id ?? null,
            ];

            // Remove null values
            $data = array_filter($data, function($value) {
                return $value !== null;
            });

            Log::info('NotificationObserver: Sending FCM notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'device_count' => count($deviceTokens),
                'title' => $title,
            ]);

            // Send to all active devices
            $result = $this->fcmService->sendToMultipleDevices(
                $deviceTokens,
                $title,
                $message,
                $data,
                null, // imageUrl
                'web' // deviceType
            );

            Log::info('NotificationObserver: FCM notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'success_count' => $result['success_count'] ?? 0,
                'failed_count' => $result['failed_count'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('NotificationObserver: Error sending FCM notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'notification_id' => $notification->id ?? null,
                'user_id' => $notification->user_id ?? null,
            ]);
            // Don't throw exception - notification should still be saved even if FCM fails
        }
    }
}

