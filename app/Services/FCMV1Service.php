<?php

namespace App\Services;

use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FCMV1Service
{
    private $serviceAccountPath;
    private $projectId;
    private $accessToken;
    private $accessTokenExpiry;

    public function __construct()
    {
        $this->serviceAccountPath = config('services.fcm.service_account_path');
        $this->projectId = config('services.fcm.project_id');
        
        if (!$this->serviceAccountPath || !$this->projectId) {
            Log::warning('FCM V1 Service Account not configured. Please set FCM_SERVICE_ACCOUNT_PATH and FCM_PROJECT_ID in .env file');
        }
    }

    /**
     * Get OAuth 2.0 access token from Service Account
     */
    private function getAccessToken()
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->accessTokenExpiry && $this->accessTokenExpiry > time() + 60) {
            return $this->accessToken;
        }

        if (!$this->serviceAccountPath) {
            Log::error('FCM Service Account path not configured');
            return null;
        }

        try {
            // Read service account JSON
            $serviceAccountPath = storage_path('app/' . ltrim($this->serviceAccountPath, '/'));
            if (!file_exists($serviceAccountPath)) {
                // Try absolute path
                $serviceAccountPath = $this->serviceAccountPath;
            }
            
            if (!file_exists($serviceAccountPath)) {
                Log::error('FCM Service Account file not found', [
                    'path' => $serviceAccountPath,
                ]);
                return null;
            }

            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            
            if (!$serviceAccount) {
                Log::error('FCM Service Account JSON is invalid');
                return null;
            }

            // Generate JWT for OAuth 2.0
            $jwt = $this->generateJWT($serviceAccount);
            
            if (!$jwt) {
                return null;
            }

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->accessToken = $tokenData['access_token'] ?? null;
                $this->accessTokenExpiry = time() + ($tokenData['expires_in'] ?? 3600);
                
                Log::info('FCM V1 access token obtained', [
                    'expires_in' => $tokenData['expires_in'] ?? null,
                ]);
                
                return $this->accessToken;
            } else {
                Log::error('Failed to get FCM V1 access token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error getting FCM V1 access token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generate JWT for OAuth 2.0
     */
    private function generateJWT($serviceAccount)
    {
        try {
            $now = time();
            $exp = $now + 3600; // 1 hour
            
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];
            
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $exp,
                'iat' => $now,
            ];
            
            // Encode header and payload
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
            
            // Create signature
            $signatureInput = $headerEncoded . '.' . $payloadEncoded;
            $privateKeyString = $serviceAccount['private_key'];
            
            // Convert private key string to resource
            $privateKeyResource = openssl_pkey_get_private($privateKeyString);
            
            if (!$privateKeyResource) {
                Log::error('Failed to load private key', [
                    'openssl_error' => openssl_error_string(),
                ]);
                return null;
            }
            
            // Sign with private key
            $signature = '';
            $success = openssl_sign($signatureInput, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
            openssl_free_key($privateKeyResource);
            
            if (!$success || empty($signature)) {
                Log::error('Failed to sign JWT', [
                    'openssl_error' => openssl_error_string(),
                ]);
                return null;
            }
            
            $signatureEncoded = $this->base64UrlEncode($signature);
            
            return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        } catch (\Exception $e) {
            Log::error('Error generating JWT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Check if device token is valid FCM token
     */
    private function isValidFCMToken($deviceToken)
    {
        // FCM tokens are typically long strings, not starting with "test_"
        // Valid FCM token format: usually 152+ characters, alphanumeric with some special chars
        if (empty($deviceToken)) {
            return false;
        }
        
        // Skip test/dummy tokens
        if (str_starts_with($deviceToken, 'test_device_') || 
            str_starts_with($deviceToken, 'test_') ||
            strlen($deviceToken) < 50) {
            return false;
        }
        
        return true;
    }

    /**
     * Send push notification using HTTP v1 API
     */
    public function sendToDevice($deviceToken, $title, $message, $data = [], $imageUrl = null)
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            Log::error('FCM V1 access token not available');
            return false;
        }

        if (!$this->projectId) {
            Log::error('FCM Project ID not configured');
            return false;
        }

        if (empty($deviceToken)) {
            Log::warning('Device token is empty');
            return false;
        }

        // Validate FCM token format
        if (!$this->isValidFCMToken($deviceToken)) {
            Log::warning('Invalid FCM token format (likely test/dummy token)', [
                'token_preview' => substr($deviceToken, 0, 30) . '...',
                'token_length' => strlen($deviceToken),
            ]);
            return false;
        }

        try {
            // HTTP v1 API payload format
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                    ],
                    'data' => array_map('strval', array_merge([
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ], $data)),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'icon' => 'ic_launcher', // Use app icon for notifications
                            'sound' => 'default',
                            'channel_id' => 'fcm_channel',
                        ],
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                ],
            ];

            // Add image if provided
            if ($imageUrl) {
                $payload['message']['notification']['image'] = $imageUrl;
            }

            $url = str_replace('{project_id}', $this->projectId, 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send');

            Log::info('Sending FCM V1 notification', [
                'url' => $url,
                'device_token' => substr($deviceToken, 0, 20) . '...',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            Log::info('FCM V1 API response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('FCM V1 notification sent successfully', [
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'name' => $responseData['name'] ?? null,
                ]);
                return true;
            } else {
                $errorBody = $response->body();
                $errorData = json_decode($errorBody, true);
                
                // Check if it's an invalid token error (common for test/dummy tokens)
                $isInvalidToken = false;
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                    if (str_contains($errorMessage, 'registration token is not a valid') ||
                        str_contains($errorMessage, 'INVALID_ARGUMENT')) {
                        $isInvalidToken = true;
                    }
                }
                
                if ($isInvalidToken) {
                    // Log as warning for invalid tokens (likely test tokens)
                    Log::warning('FCM V1 API: Invalid device token (skipped)', [
                        'status' => $response->status(),
                        'token_preview' => substr($deviceToken, 0, 30) . '...',
                        'error_message' => $errorData['error']['message'] ?? 'Invalid token',
                    ]);
                } else {
                    // Log as error for other failures
                    Log::error('FCM V1 API request failed', [
                        'status' => $response->status(),
                        'response' => $errorBody,
                    ]);
                }
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending FCM V1 notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send to multiple devices (HTTP v1 API sends one by one)
     */
    public function sendToMultipleDevices($deviceTokens, $title, $message, $data = [], $imageUrl = null)
    {
        $successCount = 0;
        $failedCount = 0;

        foreach ($deviceTokens as $token) {
            $deviceToken = is_array($token) ? ($token['token'] ?? $token) : $token;
            
            if ($this->sendToDevice($deviceToken, $title, $message, $data, $imageUrl)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * Send push notification to a member
     */
    public function sendToMember($member, $title, $message, $data = [], $imageUrl = null)
    {
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

        // Get all active device tokens
        $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
            ->where('is_active', true)
            ->pluck('device_token')
            ->toArray();

        if (empty($deviceTokens)) {
            Log::info('No active device tokens found for member', [
                'member_id' => $member->id,
            ]);
            return ['success_count' => 0, 'failed_count' => 0];
        }

        Log::info('Sending FCM V1 notification to member', [
            'member_id' => $member->id,
            'device_count' => count($deviceTokens),
            'title' => $title,
        ]);

        return $this->sendToMultipleDevices($deviceTokens, $title, $message, $data, $imageUrl);
    }
}

