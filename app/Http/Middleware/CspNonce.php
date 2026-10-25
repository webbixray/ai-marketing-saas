<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CspNonce
{
    /**
     * Handle an incoming request.
     *
     * Generates a cryptographically secure nonce for CSP and shares it with views.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(32));

        // Store nonce on request for use in SecurityHeaders middleware
        $request->attributes->set('csp_nonce', $nonce);

        // Share with all views for inline script/style tags
        view()->share('cspNonce', $nonce);

        return $next($request);
    }
}