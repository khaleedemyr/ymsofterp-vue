<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get notifications for authenticated member
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

            // Get pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 50);
            $offset = ($page - 1) * $limit;

            // Get total count for pagination
            $totalCount = MemberAppsNotification::where('member_id', $member->id)->count();

            // Get notifications with pagination
            $notifications = MemberAppsNotification::where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $result = $notifications->map(function($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'url' => $notification->url,
                    'is_read' => (bool) $notification->is_read,
                    'data' => $notification->data,
                    'created_at' => $notification->created_at ? $notification->created_at->format('Y-m-d H:i:s') : null,
                    'time' => $notification->created_at ? $notification->created_at->diffForHumans() : null,
                ];
            });

            // Calculate pagination info
            $hasMore = ($offset + $limit) < $totalCount;
            $totalPages = ceil($totalCount / $limit);

            return response()->json([
                'success' => true,
                'notifications' => $result,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_more' => $hasMore
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $count = MemberAppsNotification::where('member_id', $member->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting unread count', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $notification = MemberAppsNotification::where('member_id', $member->id)
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            MemberAppsNotification::where('member_id', $member->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read: ' . $e->getMessage()
            ], 500);
        }
    }
}

