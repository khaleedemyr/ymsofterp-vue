<?php

namespace App\Services;

use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsMember;
use App\Services\FCMV1Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    private $fcmApiKey;
    private $fcmIosKey;
    private $fcmAndroidKey;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    private $fcmV1Url = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
    private $useV1Api;
    private $v1Service;

    public function __construct()
    {
        // Check if should use HTTP v1 API
        $this->useV1Api = config('services.fcm.use_v1_api', true);
        
        if ($this->useV1Api) {
            // Try to use HTTP v1 API with Service Account
            try {
                $this->v1Service = new FCMV1Service();
            } catch (\Exception $e) {
                Log::warning('FCM V1 Service failed to initialize, falling back to Legacy API', [
                    'error' => $e->getMessage(),
                ]);
                $this->useV1Api = false;
            }
        }
        
        // Legacy API keys (for fallback)
        $this->fcmApiKey = config('services.fcm.server_key'); // Default/fallback
        $this->fcmIosKey = config('services.fcm.ios_key');
        $this->fcmAndroidKey = config('services.fcm.android_key');
        
        if (!$this->useV1Api && !$this->fcmApiKey && !$this->fcmIosKey && !$this->fcmAndroidKey) {
            Log::warning('FCM API Key not configured. Please set FCM_SERVICE_ACCOUNT_PATH and FCM_PROJECT_ID (for V1) or FCM_SERVER_KEY (for Legacy) in .env file');
        }
    }

    /**
     * Get FCM API Key based on device type
     */
    private function getApiKey($deviceType = null)
    {
        if ($deviceType === 'ios' && $this->fcmIosKey) {
            return $this->fcmIosKey;
        } elseif ($deviceType === 'android' && $this->fcmAndroidKey) {
            return $this->fcmAndroidKey;
        }
        
        // Fallback to default key
        return $this->fcmApiKey ?: ($this->fcmIosKey ?: $this->fcmAndroidKey);
    }

    /**
     * Send push notification to a single device token with detailed error
     * 
     * @param string $deviceToken FCM device token
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param string|null $imageUrl Optional image URL for notification
     * @param string|null $deviceType Device type ('ios' or 'android') to use correct API key
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function sendToDeviceWithError($deviceToken, $title, $message, $data = [], $imageUrl = null, $deviceType = null)
    {
        $apiKey = $this->getApiKey($deviceType);
        
        if (!$apiKey) {
            return [
                'success' => false,
                'error' => 'FCM API Key not configured for ' . ($deviceType ?? 'device')
            ];
        }

        if (empty($deviceToken)) {
            return [
                'success' => false,
                'error' => 'Device token is empty'
            ];
        }

        try {
            $payload = [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => array_merge([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ], $data),
                'priority' => 'high',
            ];

            // Add image if provided
            if ($imageUrl) {
                $payload['notification']['image'] = $imageUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if message was sent successfully
                if (isset($responseData['success']) && $responseData['success'] == 1) {
                    Log::info('FCM notification sent successfully', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'title' => $title,
                    ]);
                    return ['success' => true, 'error' => null];
                } else {
                    // Extract error message from response
                    $errorMsg = 'FCM send failed';
                    if (isset($responseData['results'][0]['error'])) {
                        $errorMsg = $responseData['results'][0]['error'];
                    } elseif (isset($responseData['error'])) {
                        $errorMsg = $responseData['error'];
                    }
                    
                    Log::warning('FCM notification failed', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'response' => $responseData,
                        'error' => $errorMsg,
                    ]);
                    return ['success' => false, 'error' => $errorMsg];
                }
            } else {
                $errorMsg = 'FCM API request failed: HTTP ' . $response->status();
                $responseBody = $response->body();
                if ($responseBody) {
                    $errorMsg .= ' - ' . substr($responseBody, 0, 200);
                }
                
                Log::error('FCM API request failed', [
                    'status' => $response->status(),
                    'response' => $responseBody,
                ]);
                return ['success' => false, 'error' => $errorMsg];
            }
        } catch (\Exception $e) {
            $errorMsg = 'Exception: ' . $e->getMessage();
            Log::error('Error sending FCM notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Send push notification to a single device token
     * 
     * @param string $deviceToken FCM device token
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param string|null $imageUrl Optional image URL for notification
     * @param string|null $deviceType Device type ('ios' or 'android') to use correct API key
     * @return bool
     */
    public function sendToDevice($deviceToken, $title, $message, $data = [], $imageUrl = null, $deviceType = null)
    {
        $apiKey = $this->getApiKey($deviceType);
        
        if (!$apiKey) {
            Log::error('FCM API Key not configured');
            return false;
        }

        if (empty($deviceToken)) {
            Log::warning('Device token is empty');
            return false;
        }

        try {
            $payload = [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => array_merge([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ], $data),
                'priority' => 'high',
            ];

            // Add image if provided
            if ($imageUrl) {
                $payload['notification']['image'] = $imageUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if message was sent successfully
                if (isset($responseData['success']) && $responseData['success'] == 1) {
                    Log::info('FCM notification sent successfully', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'title' => $title,
                    ]);
                    return true;
                } else {
                    // Extract error message from response
                    $errorMsg = 'FCM send failed';
                    if (isset($responseData['results'][0]['error'])) {
                        $errorMsg = 'FCM Error: ' . $responseData['results'][0]['error'];
                    } elseif (isset($responseData['error'])) {
                        $errorMsg = 'FCM Error: ' . $responseData['error'];
                    }
                    
                    Log::warning('FCM notification failed', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'response' => $responseData,
                        'error' => $errorMsg,
                    ]);
                    return false;
                }
            } else {
                $errorMsg = 'FCM API request failed: HTTP ' . $response->status();
                $responseBody = $response->body();
                if ($responseBody) {
                    $errorMsg .= ' - ' . substr($responseBody, 0, 200);
                }
                
                Log::error('FCM API request failed', [
                    'status' => $response->status(),
                    'response' => $responseBody,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending FCM notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send push notification to multiple device tokens
     * 
     * @param array $deviceTokens Array of FCM device tokens or array of ['token' => '...', 'type' => 'ios/android']
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param string|null $imageUrl Optional image URL for notification
     * @return array Results with success count and failed tokens
     */
    public function sendToMultipleDevices($deviceTokens, $title, $message, $data = [], $imageUrl = null)
    {
        // Check if we have any API key configured
        if (!$this->fcmApiKey && !$this->fcmIosKey && !$this->fcmAndroidKey) {
            Log::error('FCM API Key not configured');
            return ['success_count' => 0, 'failed_count' => count($deviceTokens)];
        }

        // Group tokens by device type if tokens are in format ['token' => '...', 'type' => '...']
        $iosTokens = [];
        $androidTokens = [];
        $webTokens = [];
        $unknownTokens = [];

        foreach ($deviceTokens as $token) {
            if (is_array($token) && isset($token['token']) && isset($token['type'])) {
                if ($token['type'] === 'ios') {
                    $iosTokens[] = $token['token'];
                } elseif ($token['type'] === 'android') {
                    $androidTokens[] = $token['token'];
                } elseif ($token['type'] === 'web') {
                    $webTokens[] = $token['token'];
                } else {
                    $unknownTokens[] = $token['token'];
                }
            } else {
                // Simple string token - check if deviceType parameter is provided
                $tokenValue = is_array($token) ? ($token['token'] ?? $token) : $token;
                // If deviceType is 'web', add to web tokens, otherwise unknown
                if (func_num_args() > 4 && func_get_arg(5) === 'web') {
                    $webTokens[] = $tokenValue;
                } else {
                    $unknownTokens[] = $tokenValue;
                }
            }
        }

        $totalSuccess = 0;
        $totalFailed = 0;

        // Send to iOS devices
        if (!empty($iosTokens)) {
            $result = $this->sendToDeviceGroup($iosTokens, $title, $message, $data, $imageUrl, 'ios');
            $totalSuccess += $result['success_count'];
            $totalFailed += $result['failed_count'];
        }

        // Send to Android devices
        if (!empty($androidTokens)) {
            $result = $this->sendToDeviceGroup($androidTokens, $title, $message, $data, $imageUrl, 'android');
            $totalSuccess += $result['success_count'];
            $totalFailed += $result['failed_count'];
        }

        // Send to Web browsers
        if (!empty($webTokens)) {
            $result = $this->sendToDeviceGroup($webTokens, $title, $message, $data, $imageUrl, 'web');
            $totalSuccess += $result['success_count'];
            $totalFailed += $result['failed_count'];
        }

        // Send to unknown devices (use default key)
        if (!empty($unknownTokens)) {
            $result = $this->sendToDeviceGroup($unknownTokens, $title, $message, $data, $imageUrl, null);
            $totalSuccess += $result['success_count'];
            $totalFailed += $result['failed_count'];
        }

        return [
            'success_count' => $totalSuccess,
            'failed_count' => $totalFailed,
        ];
    }

    /**
     * Send to a group of devices with same device type
     */
    private function sendToDeviceGroup($deviceTokens, $title, $message, $data = [], $imageUrl = null, $deviceType = null)
    {
        $apiKey = $this->getApiKey($deviceType);
        
        if (!$apiKey) {
            Log::error('FCM API Key not configured for device type: ' . ($deviceType ?? 'unknown'));
            return ['success_count' => 0, 'failed_count' => count($deviceTokens)];
        }

        if (empty($deviceTokens)) {
            return ['success_count' => 0, 'failed_count' => 0];
        }

        // Remove duplicates and empty tokens
        $deviceTokens = array_unique(array_filter($deviceTokens));

        if (count($deviceTokens) > 1000) {
            // FCM supports max 1000 tokens per request
            // Split into chunks
            $chunks = array_chunk($deviceTokens, 1000);
            $totalSuccess = 0;
            $totalFailed = 0;

            foreach ($chunks as $chunk) {
                $result = $this->sendToDeviceGroup($chunk, $title, $message, $data, $imageUrl, $deviceType);
                $totalSuccess += $result['success_count'];
                $totalFailed += $result['failed_count'];
            }

            return [
                'success_count' => $totalSuccess,
                'failed_count' => $totalFailed,
            ];
        }

        try {
            $payload = [
                'registration_ids' => $deviceTokens,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => array_merge([
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ], $data),
                'priority' => 'high',
            ];

            // Add image if provided
            if ($imageUrl) {
                $payload['notification']['image'] = $imageUrl;
            }

            Log::info('Sending FCM batch request', [
                'url' => $this->fcmUrl,
                'device_count' => count($deviceTokens),
                'device_type' => $deviceType,
                'api_key_prefix' => substr($apiKey, 0, 20) . '...',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            Log::info('FCM API response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                $successCount = $responseData['success'] ?? 0;
                $failureCount = $responseData['failure'] ?? 0;

                // Log detailed results if available
                if (isset($responseData['results'])) {
                    $errors = [];
                    foreach ($responseData['results'] as $index => $result) {
                        if (isset($result['error'])) {
                            $errors[] = [
                                'index' => $index,
                                'error' => $result['error'],
                            ];
                        }
                    }
                    if (!empty($errors)) {
                        Log::warning('FCM batch notification errors', [
                            'errors' => $errors,
                        ]);
                    }
                }

                Log::info('FCM batch notification sent', [
                    'total_tokens' => count($deviceTokens),
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ]);

                return [
                    'success_count' => $successCount,
                    'failed_count' => $failureCount,
                ];
            } else {
                $errorBody = $response->body();
                Log::error('FCM batch API request failed', [
                    'status' => $response->status(),
                    'response' => $errorBody,
                    'url' => $this->fcmUrl,
                    'api_key_type' => str_starts_with($apiKey, 'AIza') ? 'Google API Key' : 'FCM Server Key',
                ]);
                
                // Check if it's a 404 - might be wrong endpoint or API key type
                if ($response->status() === 404) {
                    Log::error('FCM 404 Error - Possible issues:', [
                        'issue_1' => 'Server Key might not be valid or expired',
                        'issue_2' => 'Server Key might not match the Firebase project used for device tokens',
                        'issue_3' => 'Legacy FCM API might be disabled for this project',
                        'issue_4' => 'Check Firebase Console > Project Settings > Cloud Messaging > Server Key',
                        'current_key_prefix' => substr($apiKey, 0, 30),
                        'suggestion' => 'Verify server key in Firebase Console and ensure it matches the project',
                    ]);
                }
                
                // Check if it's a 401 - unauthorized
                if ($response->status() === 401) {
                    Log::error('FCM 401 Error - Unauthorized:', [
                        'issue' => 'Server Key is invalid or incorrect',
                        'suggestion' => 'Get a new Server Key from Firebase Console > Project Settings > Cloud Messaging',
                    ]);
                }
                
                return [
                    'success_count' => 0,
                    'failed_count' => count($deviceTokens),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error sending FCM batch notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success_count' => 0,
                'failed_count' => count($deviceTokens),
            ];
        }
    }

    /**
     * Send push notification to a member
     * Gets all active device tokens for the member and sends notification
     * 
     * @param int|MemberAppsMember $member Member ID or Member model
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $data Additional data payload
     * @param string|null $imageUrl Optional image URL for notification
     * @return array Results with success count and failed count
     */
    public function sendToMember($member, $title, $message, $data = [], $imageUrl = null)
    {
        // Use HTTP v1 API if available
        if ($this->useV1Api && $this->v1Service) {
            try {
                return $this->v1Service->sendToMember($member, $title, $message, $data, $imageUrl);
            } catch (\Exception $e) {
                Log::warning('FCM V1 API failed, falling back to Legacy API', [
                    'error' => $e->getMessage(),
                ]);
                // Fall through to Legacy API
            }
        }

        // Fallback to Legacy API
        // Get member model if ID is provided
        if (is_numeric($member)) {
            $member = MemberAppsMember::find($member);
        }

        if (!$member) {
            Log::warning('Member not found for FCM notification', [
                'member' => $member,
            ]);
            return ['success_count' => 0, 'failed_count' => 0];
        }

        // Check if member allows notifications
        if (!$member->allow_notification) {
            Log::info('Member has disabled notifications', [
                'member_id' => $member->id,
            ]);
            return ['success_count' => 0, 'failed_count' => 0];
        }

        // Get all active device tokens for this member with device type
        $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
            ->where('is_active', true)
            ->get()
            ->map(function ($token) {
                return [
                    'token' => $token->device_token,
                    'type' => $token->device_type ?? 'android', // Default to android if null
                ];
            })
            ->toArray();

        if (empty($deviceTokens)) {
            Log::info('No active device tokens found for member', [
                'member_id' => $member->id,
            ]);
            return ['success_count' => 0, 'failed_count' => 0];
        }

        Log::info('Sending FCM notification to member (Legacy API)', [
            'member_id' => $member->id,
            'device_count' => count($deviceTokens),
            'title' => $title,
        ]);

        return $this->sendToMultipleDevices($deviceTokens, $title, $message, $data, $imageUrl);
    }
}
