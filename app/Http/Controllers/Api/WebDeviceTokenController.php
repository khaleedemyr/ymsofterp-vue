<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WebDeviceTokenController extends Controller
{
    /**
     * Register device token for push notifications (web browser)
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
                'browser' => 'nullable|string|max:50',
                'user_agent' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $deviceToken = $request->input('device_token');
            $browser = $request->input('browser');
            $userAgent = $request->input('user_agent', $request->header('User-Agent'));

            // Validate FCM token format
            if (empty($deviceToken) || 
                str_starts_with($deviceToken, 'test_device_') || 
                str_starts_with($deviceToken, 'test_') ||
                strlen($deviceToken) < 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FCM token format. Token must be a valid FCM registration token from Firebase SDK, not a test/dummy token.',
                    'hint' => 'Make sure you are using the actual FCM token from Firebase SDK for web.'
                ], 400);
            }

            // Check if device token already exists for this user
            $existingToken = WebDeviceToken::where('user_id', $user->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($existingToken) {
                // Update existing token
                $existingToken->browser = $browser;
                $existingToken->user_agent = $userAgent;
                $existingToken->is_active = true;
                $existingToken->last_used_at = now();
                $existingToken->save();

                Log::info('Web device token updated', [
                    'user_id' => $user->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated successfully',
                    'data' => [
                        'id' => $existingToken->id,
                        'browser' => $existingToken->browser,
                        'is_active' => $existingToken->is_active,
                    ]
                ]);
            } else {
                // Limit: Keep only the 5 most recent active tokens per user
                // Deactivate older tokens to prevent too many notifications
                $activeTokenCount = WebDeviceToken::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->count();
                
                if ($activeTokenCount >= 5) {
                    // Deactivate oldest tokens, keep only 4 most recent
                    $oldTokens = WebDeviceToken::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->orderBy('last_used_at', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->limit($activeTokenCount - 4)
                        ->get();
                    
                    foreach ($oldTokens as $oldToken) {
                        $oldToken->is_active = false;
                        $oldToken->save();
                    }
                    
                    Log::info('Deactivated old web device tokens', [
                        'user_id' => $user->id,
                        'deactivated_count' => $oldTokens->count(),
                    ]);
                }
                
                // Create new token
                $newToken = WebDeviceToken::create([
                    'user_id' => $user->id,
                    'device_token' => $deviceToken,
                    'browser' => $browser,
                    'user_agent' => $userAgent,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('Web device token registered', [
                    'user_id' => $user->id,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'active_token_count' => $activeTokenCount + 1,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token registered successfully',
                    'data' => [
                        'id' => $newToken->id,
                        'browser' => $newToken->browser,
                        'is_active' => $newToken->is_active,
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error registering web device token', [
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
            $token = WebDeviceToken::where('user_id', $user->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($token) {
                $token->is_active = false;
                $token->save();

                Log::info('Web device token unregistered', [
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
            Log::error('Error unregistering web device token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister device token: ' . $e->getMessage()
            ], 500);
        }
    }
}

