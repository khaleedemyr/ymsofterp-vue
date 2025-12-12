<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\FCMService;
use App\Models\WebDeviceToken;
use App\Models\EmployeeDeviceToken;
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

            // Get all active web device tokens for this user
            $webDeviceTokens = WebDeviceToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('device_token')
                ->toArray();

            // Get all active employee device tokens for this user (for approval app)
            $employeeDeviceTokens = EmployeeDeviceToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->get()
                ->map(function ($token) {
                    return [
                        'token' => $token->device_token,
                        'type' => $token->device_type ?? 'android', // Default to android if null
                    ];
                })
                ->toArray();

            $totalDeviceCount = count($webDeviceTokens) + count($employeeDeviceTokens);

            if ($totalDeviceCount === 0) {
                Log::debug('NotificationObserver: No active device tokens found', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                ]);
                return;
            }

            Log::info('NotificationObserver: Sending FCM notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'web_device_count' => count($webDeviceTokens),
                'employee_device_count' => count($employeeDeviceTokens),
                'total_device_count' => $totalDeviceCount,
                'title' => $title,
            ]);

            $totalSuccess = 0;
            $totalFailed = 0;
            $webResult = ['success_count' => 0, 'failed_count' => 0];
            $employeeResult = ['success_count' => 0, 'failed_count' => 0];

            // Send to web devices
            if (!empty($webDeviceTokens)) {
                $webResult = $this->fcmService->sendToMultipleDevices(
                    $webDeviceTokens,
                    $title,
                    $message,
                    $data,
                    null, // imageUrl
                    'web' // deviceType
                );
                $totalSuccess += $webResult['success_count'] ?? 0;
                $totalFailed += $webResult['failed_count'] ?? 0;
            }

            // Send to employee devices (approval app)
            if (!empty($employeeDeviceTokens)) {
                $employeeResult = $this->fcmService->sendToMultipleDevices(
                    $employeeDeviceTokens,
                    $title,
                    $message,
                    $data,
                    null // imageUrl
                );
                $totalSuccess += $employeeResult['success_count'] ?? 0;
                $totalFailed += $employeeResult['failed_count'] ?? 0;
            }

            Log::info('NotificationObserver: FCM notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'web_success' => $webResult['success_count'] ?? 0,
                'web_failed' => $webResult['failed_count'] ?? 0,
                'employee_success' => $employeeResult['success_count'] ?? 0,
                'employee_failed' => $employeeResult['failed_count'] ?? 0,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
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

