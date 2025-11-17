<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /**
     * Register or update device token
     * Called when member logs in or app opens
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string|max:255',
            'device_type' => 'required|in:android,ios,web',
            'device_id' => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get member from authenticated user (using Sanctum)
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login first.'
                ], 401);
            }

            // Check if token already exists for this member
            // Priority: Check by member_id + device_token combination
            $existingToken = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('device_token', $request->device_token)
                ->first();

            // If not found, check by device_token only (might be from different member)
            if (!$existingToken) {
                $existingToken = MemberAppsDeviceToken::where('device_token', $request->device_token)->first();
            }

            if ($existingToken) {
                // Update existing token (update member_id if different)
                $existingToken->update([
                    'member_id' => $member->id,
                    'device_type' => $request->device_type,
                    'device_id' => $request->device_id,
                    'app_version' => $request->app_version,
                    'is_active' => true,
                    'last_used_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated successfully',
                    'data' => $existingToken
                ]);
            }

            // Create new token
            $deviceToken = MemberAppsDeviceToken::create([
                'member_id' => $member->id,
                'device_token' => $request->device_token,
                'device_type' => $request->device_type,
                'device_id' => $request->device_id,
                'app_version' => $request->app_version,
                'is_active' => true,
                'last_used_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token registered successfully',
                'data' => $deviceToken
            ], 201);

        } catch (\Exception $e) {
            Log::error('Register Device Token Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unregister device token (when member logs out)
     */
    public function unregister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Deactivate token instead of deleting (for history)
            $deviceToken = MemberAppsDeviceToken::where('device_token', $request->device_token)
                ->where('member_id', $member->id)
                ->first();

            if ($deviceToken) {
                $deviceToken->update([
                    'is_active' => false
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token unregistered successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Device token not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Unregister Device Token Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister device token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active device tokens for current member
     */
    public function index(Request $request)
    {
        try {
            $member = $request->user();
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $tokens = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tokens
            ]);

        } catch (\Exception $e) {
            Log::error('Get Device Tokens Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get device tokens',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

