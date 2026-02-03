<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index()
    {
        // Cache notifications for 30 seconds to reduce database load
        $userId = Auth::id();
        $cacheKey = "notifications_user_{$userId}";
        
        $notifications = Cache::remember($cacheKey, 30, function () use ($userId) {
            return Notification::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'task_id' => $notification->task_id,
                        'type' => $notification->type,
                        'title' => $notification->title ?? '',
                        'message' => $notification->message,
                        'url' => $notification->url,
                        'is_read' => (bool) $notification->is_read,
                        'created_at' => $notification->created_at,
                        'time' => $notification->created_at->diffForHumans(),
                    ];
                });
        });

        return response()->json($notifications);
    }

    public function markAsRead($id)
    {
        $userId = Auth::id();
        $notification = Notification::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);
        
        // Clear cache after marking as read
        Cache::forget("notifications_user_{$userId}");
        Cache::forget("notifications_unread_count_{$userId}");

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $userId = Auth::id();
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        // Clear cache after marking all as read
        Cache::forget("notifications_user_{$userId}");
        Cache::forget("notifications_unread_count_{$userId}");

        return response()->json(['success' => true]);
    }

    public function unreadCount()
    {
        // Cache unread count for 30 seconds to reduce database load
        $userId = Auth::id();
        $cacheKey = "notifications_unread_count_{$userId}";
        
        $count = Cache::remember($cacheKey, 30, function () use ($userId) {
            return Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->count();
        });

        return response()->json(['count' => $count]);
    }
} 