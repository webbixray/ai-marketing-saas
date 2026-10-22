<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * @group Users & Profile
 *
 * APIs for managing user profiles, passwords, and avatars.
 */
class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only(['name', 'email']));

        Log::info('User profile updated', [
            'user_id' => $user->id,
            'updated_fields' => array_keys($request->only(['name', 'email']))
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'min:8', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all existing tokens to force re-login with new password
        $user->tokens()->delete();

        Log::info('User password changed', [
            'user_id' => $user->id
        ]);

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.'
        ]);
    }

    /**
     * Upload avatar for the authenticated user.
     * (Optional feature - requires storage configuration)
     */
    public function uploadAvatar(Request $request)
    {
        $user = $request->user();
        Log::debug('AVATAR UPLOAD STARTED');
        
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        // Store the avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        // Delete old avatar if exists
        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
        }

        // Update user with avatar path
        $user->update([
            'avatar' => $path
        ]);

        Log::info('User avatar uploaded', [
            'user_id' => $user->id,
            'avatar_path' => $path
        ]);

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => \Storage::url($path)
        ]);
    }

    /**
     * Delete avatar for the authenticated user.
     */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        
        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
            
            Log::info('User avatar deleted', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Avatar deleted successfully'
            ]);
        }

        return response()->json([
            'message' => 'No avatar to delete'
        ], 400);
    }
}