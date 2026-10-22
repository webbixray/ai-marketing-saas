<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestAuth extends Middleware
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
        Log::debug('TEST AUTH MIDDLEWARE CALLED', [
            'guards' => $guards,
            'path' => $request->path(),
            'expectsJson' => $request->expectsJson()
        ]);

        $this->authenticate($request, $guards);

        Log::debug('TEST AUTH MIDDLEWARE PROCEEDING');

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo($request)
    {
        Log::debug('TEST REDIRECTTO CALLED', [
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
