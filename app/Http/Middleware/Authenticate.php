<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  string[]  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        return $next($request);
    }

    protected function redirectTo($request)
    {
        // For API requests that expect JSON, return null to trigger 401 response
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return parent::redirectTo($request);
    }
}
