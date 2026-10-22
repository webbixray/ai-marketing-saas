<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Determine tenant from subdomain (e.g., tenant1.app.test) or header X-Tenant-ID
        $tenant = null;

        // 1. Check header
        if ($request->header('X-Tenant-ID')) {
            $tenantId = (int) $request->header('X-Tenant-ID');
            $tenant = Tenant::find($tenantId);
        }

        // 2. Check subdomain (optional)
        if (!$tenant) {
            $host = $request->getHost();
            // Extract subdomain: assuming format tenant.domain.com
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $subdomain = $parts[0];
                $tenant = Tenant::where('domain', $subdomain)->first();
            }
        }

        if ($tenant) {
            // Set tenant on auth user if logged in
            if (Auth::check()) {
                $user = Auth::user();
                if ($user->tenant_id !== $tenant->id) {
                    $user->tenant_id = $tenant->id;
                    $user->save();
                }
            } else {
                // Store in session for guest users (will be applied after login via scope)
                session(['tenant_id' => $tenant->id]);
            }
        }

        return $next($request);
    }
}
