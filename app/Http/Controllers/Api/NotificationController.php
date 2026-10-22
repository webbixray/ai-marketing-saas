<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Notification;

/**
 * @group Notifications
 *
 * APIs for managing user notifications.
 */
class NotificationController extends Controller
{
    /**
     * Get the authenticated user's notifications.
     */
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get the count of unread notifications for the authenticated user.
     */
    public function unreadCount(Request $request)
    {
        $count = $request->user()->notifications()
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
        ]);
    }
}
