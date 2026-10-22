<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.notifications.index', compact('notifications'));
    }

    /**
     * Mark a notification as read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = Notification::where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->firstOrFail();

        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Get the count of unread notifications.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function unreadCount(Request $request)
    {
        $user = Auth::user();
        $count = Notification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($request->expectsJson()) {
            return response()->json(['count' => $count]);
        }

        return $count;
    }
}
