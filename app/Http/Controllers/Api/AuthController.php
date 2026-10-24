<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

/**
 * @group Authentication
 *
 * APIs for user authentication and registration.
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @unauthenticated
     */
    public function register(Request $request)
    {
        Log::debug('Register method called');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $request->tenant_id,
        ]);

        // Create default notification preferences
        $channels = ['mail', 'database', 'broadcast', 'sms'];
        foreach ($channels as $channel) {
            $user->notificationPreferences()->create([
                'channel' => $channel,
                'enabled' => true,
            ]);
        }

        Log::debug('User created', ['user_id' => $user->id, 'email' => $user->email]);

        // Assign default role 'user' with api guard
        $userRole = Role::where('name', 'user')
            ->where('guard_name', 'api')
            ->first();

        Log::debug('Role lookup result', [
            'role_found' => $userRole !== null,
            'role_name' => $userRole ? $userRole->name : null,
            'role_guard' => $userRole ? $userRole->guard_name : null
        ]);

        if ($userRole) {
            Log::debug('About to assign role to user', [
                'user_id' => $user->id,
                'role_name' => $userRole->name,
                'user_guard' => $user->guardName(),  // Fixed: use guardName() not getGuardName()
                'role_guard' => $userRole->guard_name
            ]);
            
            try {
                $user->assignRole($userRole);
                Log::debug('Role assigned successfully');
            } catch (\Exception $e) {
                Log::error('Error assigning role: '.$e->getMessage(), [
                    'exception' => $e,
                    'user_id' => $user->id,
                    'role_name' => $userRole ? $userRole->name : null
                ]);
                throw $e;
            }
        } else {
            Log::warning('Role not found for assignment');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user.
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Deactivated accounts (admin toggle) cannot log in
        if (! (bool) $user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated.',
            ], 401);
        }

        // Ensure tenant_id is set (should be from registration)
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Send email verification link.
     *
     * @unauthenticated
     */
    public function sendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    /**
     * Verify email with token.
     *
     * @unauthenticated
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired verification token.',
            ], 400);
        }

        if ($user->verifyEmail($request->token)) {
            return response()->json([
                'message' => 'Email verified successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired verification token.',
        ], 400);
    }

    /**
     * Send password reset link.
     *
     * @unauthenticated
     */
    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a secure token
        $token = \Illuminate\Support\Str::random(60);
        $user->sendPasswordResetNotification($token);

        return response()->json([
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    /**
     * Reset password with token.
     *
     * @unauthenticated
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)
            ->where('password_reset_token', $request->token)
            ->first();

        if (!$user || !$user->password_reset_sent_at || $user->password_reset_sent_at->lt(now()->subMinutes(60))) {
            return response()->json([
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_token' => null,
            'password_reset_sent_at' => null,
        ]);

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. Please log in again.',
        ]);
    }

    /**
     * Resend email verification.
     */
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }
}