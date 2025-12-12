<?php

namespace App\Observers;

use App\Models\Notification;
use App\Services\FCMService;
use App\Services\FCMV1Service;
use App\Models\WebDeviceToken;
use App\Models\EmployeeDeviceToken;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsDeviceToken;
use Illuminate\Support\Facades\Log;

class NotificationObserver
{
    protected $fcmService;
    protected $fcmV1Service;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
        // Try to initialize V1 service for employee devices
        try {
            $this->fcmV1Service = new FCMV1Service();
        } catch (\Exception $e) {
            Log::debug('FCM V1 Service not available, will use Legacy API', [
                'error' => $e->getMessage(),
            ]);
            $this->fcmV1Service = null;
        }
    }
    
    /**
     * Send to devices using V1 API if available
     * Legacy API is deprecated and returns 404
     */
    protected function sendToDevicesWithV1($deviceTokens, $title, $message, $data, $imageUrl)
    {
        // Try V1 API first (recommended - doesn't require deprecated Server Key)
        if ($this->fcmV1Service) {
            try {
                // Extract tokens from array format
                $tokens = array_map(function($token) {
                    return is_array($token) ? $token['token'] : $token;
                }, $deviceTokens);
                
                Log::info('NotificationObserver: Using FCM V1 API', [
                    'token_count' => count($tokens),
                ]);
                
                return $this->fcmV1Service->sendToMultipleDevices($tokens, $title, $message, $data, $imageUrl);
            } catch (\Exception $e) {
                Log::warning('FCM V1 API failed', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fallback to Legacy - it's deprecated
                return ['success_count' => 0, 'failed_count' => count($deviceTokens)];
            }
        }
        
        // V1 API not available - log warning and skip
        Log::warning('NotificationObserver: FCM V1 API not configured. Notifications skipped.', [
            'device_count' => count($deviceTokens),
            'instruction' => 'Please configure FCM_SERVICE_ACCOUNT_PATH and FCM_PROJECT_ID in .env',
        ]);
        
        return ['success_count' => 0, 'failed_count' => count($deviceTokens)];
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
            // Use V1 API (Legacy API is deprecated and returns 404)
            if (!empty($webDeviceTokens)) {
                $webTokensArray = array_map(function($token) {
                    return ['token' => $token, 'type' => 'web'];
                }, $webDeviceTokens);
                $webResult = $this->sendToDevicesWithV1($webTokensArray, $title, $message, $data, null);
                $totalSuccess += $webResult['success_count'] ?? 0;
                $totalFailed += $webResult['failed_count'] ?? 0;
            }

            // Send to employee devices (approval app)
            // Use V1 API (Legacy API is deprecated and returns 404)
            if (!empty($employeeDeviceTokens)) {
                $employeeResult = $this->sendToDevicesWithV1($employeeDeviceTokens, $title, $message, $data, null);
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

