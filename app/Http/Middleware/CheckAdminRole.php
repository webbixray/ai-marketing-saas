<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Allow only users with the admin role.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json(['message' => 'Forbidden. Admin role required.'], 403);
            }

            return redirect()->route('login')->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }
}
