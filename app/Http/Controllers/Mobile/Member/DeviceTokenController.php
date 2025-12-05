<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsDeviceToken;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /**
     * Register device token for push notifications
     */
    public function register(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'device_token' => 'required|string',
                'device_type' => 'nullable|string|in:android,ios',
                'device_id' => 'nullable|string',
                'app_version' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $deviceToken = $request->input('device_token');
            $deviceType = $request->input('device_type', 'android');
            $deviceId = $request->input('device_id');
            $appVersion = $request->input('app_version');

            // Validate FCM token format
            if (empty($deviceToken) || 
                str_starts_with($deviceToken, 'test_device_') || 
                str_starts_with($deviceToken, 'test_') ||
                strlen($deviceToken) < 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FCM token format. Token must be a valid FCM registration token from Firebase SDK, not a test/dummy token.',
                    'hint' => 'Make sure you are using the actual FCM token from FirebaseMessaging.instance.getToken(), not a test value.'
                ], 400);
            }

            // Check if device token already exists for this member
            $existingToken = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($existingToken) {
                // Update existing token (same device, no notification needed)
                $existingToken->device_type = $deviceType;
                $existingToken->device_id = $deviceId;
                $existingToken->app_version = $appVersion;
                $existingToken->is_active = true;
                $existingToken->last_used_at = now();
                $existingToken->save();

                Log::info('Device token updated', [
                    'member_id' => $member->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated successfully',
                    'data' => [
                        'id' => $existingToken->id,
                        'device_type' => $existingToken->device_type,
                        'is_active' => $existingToken->is_active,
                    ]
                ]);
            } else {
                // This is a new device - check if member has other devices registered
                $otherDevices = MemberAppsDeviceToken::where('member_id', $member->id)
                    ->where('device_token', '!=', $deviceToken)
                    ->where('is_active', true)
                    ->get();

                // Create new device token
                $newToken = MemberAppsDeviceToken::create([
                    'member_id' => $member->id,
                    'device_token' => $deviceToken,
                    'device_type' => $deviceType,
                    'device_id' => $deviceId,
                    'app_version' => $appVersion,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('Device token registered (new device)', [
                    'member_id' => $member->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'device_type' => $deviceType,
                    'other_devices_count' => $otherDevices->count(),
                ]);

                // If member has other active devices, send notification to those devices
                // Refresh member to get latest allow_notification status
                $member->refresh();
                
                if ($otherDevices->isNotEmpty() && $member->allow_notification) {
                    try {
                        $fcmService = app(FCMService::class);
                        
                        // Get device tokens for other devices with their device types
                        $otherDeviceTokens = $otherDevices->map(function ($device) {
                            return [
                                'token' => $device->device_token,
                                'type' => $device->device_type ?? 'android',
                            ];
                        })->toArray();
                        
                        // Send notification to other devices
                        $result = $fcmService->sendToMultipleDevices(
                            $otherDeviceTokens,
                            'Security Alert 🔒',
                            'A new device has logged into your account. If this wasn\'t you, please reset your password.',
                            [
                                'type' => 'new_device_login',
                                'member_id' => $member->id,
                                'new_device_type' => $deviceType,
                                'login_time' => now()->format('Y-m-d H:i:s'),
                                'action' => 'view_security',
                            ]
                        );

                        Log::info('New device login notification sent to other devices', [
                            'member_id' => $member->id,
                            'new_device_token' => substr($deviceToken, 0, 20) . '...',
                            'new_device_type' => $deviceType,
                            'other_devices_count' => $otherDevices->count(),
                            'success_count' => $result['success_count'] ?? 0,
                            'failed_count' => $result['failed_count'] ?? 0,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error sending new device login notification', [
                            'member_id' => $member->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Don't fail the device registration if notification fails
                    }
                } else {
                    Log::info('Skipping new device login notification', [
                        'member_id' => $member->id,
                        'other_devices_count' => $otherDevices->count(),
                        'allow_notification' => $member->allow_notification ?? false,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Device token registered successfully',
                    'data' => [
                        'id' => $newToken->id,
                        'device_type' => $newToken->device_type,
                        'is_active' => $newToken->is_active,
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error registering device token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unregister device token (logout or disable notifications)
     */
    public function unregister(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'device_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $deviceToken = $request->input('device_token');

            // Deactivate device token
            $token = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($token) {
                $token->is_active = false;
                $token->save();

                Log::info('Device token unregistered', [
                    'member_id' => $member->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token unregistered successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Device token not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error unregistering device token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister device token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all device tokens for authenticated member
     */
    public function index(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
                ->orderBy('last_used_at', 'desc')
                ->get()
                ->map(function ($token) {
                    return [
                        'id' => $token->id,
                        'device_type' => $token->device_type,
                        'device_id' => $token->device_id,
                        'app_version' => $token->app_version,
                        'is_active' => $token->is_active,
                        'last_used_at' => $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i:s') : null,
                        'created_at' => $token->created_at ? $token->created_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $deviceTokens,
                'message' => 'Device tokens retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting device tokens', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get device tokens: ' . $e->getMessage()
            ], 500);
        }
    }
}
