<?php

namespace App\Http\Controllers\Mobile\ApprovalApp;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeviceToken;
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
            $user = $request->user();
            if (!$user) {
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

            // Check if device token already exists for this user
            $existingToken = EmployeeDeviceToken::where('user_id', $user->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($existingToken) {
                // Update existing token
                $existingToken->device_type = $deviceType;
                $existingToken->device_id = $deviceId;
                $existingToken->app_version = $appVersion;
                $existingToken->is_active = true;
                $existingToken->last_used_at = now();
                $existingToken->save();

                Log::info('Employee device token updated', [
                    'user_id' => $user->id,
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
                // Create new device token
                $newToken = EmployeeDeviceToken::create([
                    'user_id' => $user->id,
                    'device_token' => $deviceToken,
                    'device_type' => $deviceType,
                    'device_id' => $deviceId,
                    'app_version' => $appVersion,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('Employee device token registered', [
                    'user_id' => $user->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'device_type' => $deviceType,
                ]);

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
            Log::error('Error registering employee device token', [
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
            $user = $request->user();
            if (!$user) {
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
            $token = EmployeeDeviceToken::where('user_id', $user->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($token) {
                $token->is_active = false;
                $token->save();

                Log::info('Employee device token unregistered', [
                    'user_id' => $user->id,
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
            Log::error('Error unregistering employee device token', [
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
     * Get all device tokens for authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $deviceTokens = EmployeeDeviceToken::where('user_id', $user->id)
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
            Log::error('Error getting employee device tokens', [
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

