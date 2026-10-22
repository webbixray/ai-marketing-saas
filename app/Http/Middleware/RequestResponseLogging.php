<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RequestResponseLogging
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $correlationId = $request->get('correlation_id') ?? $request->header('X-Correlation-ID') ?? Str::uuid()->toString();
        $requestId = $request->get('request_id') ?? $request->header('X-Request-ID') ?? Str::uuid()->toString();

        // Build request log data
        $requestData = [
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'content_type' => $request->header('content-type'),
            'content_length' => $request->header('content-length'),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query_params' => $request->query->all(),
            'body' => $this->sanitizeBody($request),
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'api_key_prefix' => $request->get('api_key')?->key_prefix,
            'timestamp' => Carbon::now()->toISOString(),
        ];

        // Log request
        Log::channel('audit')->info('HTTP Request', $requestData);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

        // Build response log data
        $responseData = [
            'correlation_id' => $correlationId,
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'status_text' => $this->getStatusText($response->getStatusCode()),
            'duration_ms' => $duration,
            'response_headers' => $this->sanitizeHeaders($response->headers->all()),
            'content_type' => $response->headers->get('content-type'),
            'content_length' => $response->headers->get('content-length'),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => Carbon::now()->toISOString(),
        ];

        // Log response
        $logLevel = $this->getLogLevel($response->getStatusCode());
        Log::channel('audit')->log($logLevel, 'HTTP Response', $responseData);

        // Add timing header
        $response->headers->set('X-Response-Time', $duration . 'ms');
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Sanitize headers to remove sensitive data.
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $sensitiveKeys = ['authorization', 'cookie', 'x-api-key', 'x-csrf-token', 'set-cookie'];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys)) {
                $sanitized[$key] = ['[REDACTED]'];
            } else {
                $sanitized[$key] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize request body to remove sensitive data.
     */
    protected function sanitizeBody(Request $request): array
    {
        $contentType = $request->header('content-type');
        $body = [];

        if (Str::contains($contentType, 'application/json')) {
            $body = json_decode($request->getContent(), true) ?? [];
        } elseif (Str::contains($contentType, 'application/x-www-form-urlencoded') || Str::contains($contentType, 'multipart/form-data')) {
            $body = $request->all();
        }

        return $this->sanitizeArray($body);
    }

    /**
     * Recursively sanitize array values.
     */
    protected function sanitizeArray(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'current_password', 'new_password',
            'token', 'api_key', 'secret', 'access_token', 'refresh_token',
            'credit_card', 'card_number', 'cvv', 'ssn', 'social_security',
            'authorization', 'bearer', 'x-api-key',
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitive) {
                if (Str::contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get HTTP status text.
     */
    protected function getStatusText(int $code): string
    {
        $texts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];
        return $texts[$code] ?? 'Unknown';
    }

    /**
     * Determine log level based on status code.
     */
    protected function getLogLevel(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        } elseif ($statusCode >= 400) {
            return 'warning';
        }
        return 'info';
    }
}
