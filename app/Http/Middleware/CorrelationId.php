<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';
    public const REQUEST_HEADER = 'X-Request-ID';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get or generate correlation ID
        $correlationId = $request->header(self::HEADER_NAME) 
            ?? $request->header(self::REQUEST_HEADER) 
            ?? Str::uuid()->toString();

        // Get or generate request ID
        $requestId = $request->header(self::REQUEST_HEADER) 
            ?? Str::uuid()->toString();

        // Store in request for use in controllers
        $request->merge([
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
        ]);

        // Add to Log context for all subsequent log calls in this request
        Log::withContext([
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);

        // Add correlation ID to response headers
        $response->headers->set(self::HEADER_NAME, $correlationId);
        $response->headers->set(self::REQUEST_HEADER, $requestId);

        return $response;
    }
}
