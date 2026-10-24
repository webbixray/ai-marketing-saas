<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class WebAuthController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
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

        // Assign default role 'user' with web guard
        $userRole = Role::where('name', 'user')
            ->where('guard_name', 'web')
            ->first();

        if ($userRole) {
            $user->assignRole($userRole);
        }

        // Log in the user
        Auth::login($user);

        return redirect()->route('admin.dashboard')->with('success', 'Registration successful.');
    }

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            // Deactivated accounts (admin toggle) cannot log in
            if (! (bool) Auth::user()->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'This account has been deactivated. Contact your administrator.',
                ])->onlyInput('email');
            }

            // Regenerate session to prevent session fixation
            $request->session()->regenerate();

            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
