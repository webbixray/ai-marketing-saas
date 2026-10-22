<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NotificationPreference;

/**
 * @group Notification Preferences
 *
 * APIs for managing user notification preferences across channels.
 */
class NotificationPreferenceController extends Controller
{
    /**
     * Get all notification preferences for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $preferences = $user->notificationPreferences()->get();

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Create default notification preferences for the user if none exist.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $existing = $user->notificationPreferences()->count();
        if ($existing > 0) {
            return response()->json([
                'message' => 'Preferences already exist.',
                'preferences' => $user->notificationPreferences()->get(),
            ]);
        }

        $channels = ['mail', 'database', 'broadcast', 'sms'];

        foreach ($channels as $channel) {
            $user->notificationPreferences()->create([
                'channel' => $channel,
                'enabled' => true,
            ]);
        }

        return response()->json([
            'message' => 'Default preferences created.',
            'preferences' => $user->notificationPreferences()->get(),
        ], 201);
    }

    /**
     * Update a specific notification preference.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $preference = $user->notificationPreferences()->find($id);

        if (!$preference) {
            return response()->json([
                'message' => 'Preference not found',
            ], 404);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $preference->update($request->only('enabled'));

        return response()->json([
            'message' => 'Preference updated',
            'preference' => $preference,
        ]);
    }

    /**
     * Delete a notification preference.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $preference = $user->notificationPreferences()->find($id);

        if (!$preference) {
            return response()->json([
                'message' => 'Preference not found',
            ], 404);
        }

        $preference->delete();

        return response()->json([
            'message' => 'Preference deleted',
        ]);
    }
}