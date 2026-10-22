<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        error_log("[CUSTOM MIDDLEWARE] HANDLE CALLED. Guards: " . json_encode($guards) . " Path: " . $request->path() . " Method: " . $request->method());
        Log::debug('HANDLE CALLED', ['guards' => $guards, 'path' => $request->path(), 'method' => $request->method()]);
        Log::debug('AUTHENTICATE MIDDLEWARE: Authorization header:', ['header' => $request->header('Authorization')]);
        Log::debug('[UNIQUE MIDDLEWARE LOG] Avatar middleware called');
        $this->authenticate($request, $guards);
        Log::debug('AVATAR MIDDLEWARE AUTHENTICATED USER', ['user_id' => optional($request->user())->id ?? null]);
        return $next($request);
    }

    protected function redirectTo($request)
    {
        Log::debug('CUSTOM REDIRECTTO CALLED', [
            'expectsJson' => $request->expectsJson(),
            'isApi' => $request->is('api/*'),
            'path' => $request->path()
        ]);

        // For API requests that expect JSON, return null to trigger 401 response
        if ($request->expectsJson() || $request->is('api/*')) {
            Log::debug('RETURNING NULL FOR API REQUEST');
            return null;
        }

        Log::debug('CALLING PARENT REDIRECTTO');
        return parent::redirectTo($request);
    }

}
