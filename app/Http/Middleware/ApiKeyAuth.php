<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return $this->unauthorized('API key required. Provide via Authorization: Bearer <key> or X-API-Key header.');
        }

        // Find key by prefix (first 12 chars)
        $prefix = substr($apiKey, 0, 12);
        $key = ApiKey::active()->where('key_prefix', $prefix)->first();

        if (!$key || !Hash::check($apiKey, $key->key_hash)) {
            return $this->unauthorized('Invalid API key.');
        }

        // Check IP whitelist
        if (!$key->isValidForIp($request->ip())) {
            return $this->forbidden('IP address not allowed for this API key.');
        }

        // Check scopes
        if (!empty($scopes)) {
            $hasScope = false;
            foreach ($scopes as $scope) {
                if ($key->hasScope($scope)) {
                    $hasScope = true;
                    break;
                }
            }
            if (!$hasScope) {
                return $this->forbidden('Insufficient scopes. Required: ' . implode(', ', $scopes));
            }
        }

        // Record usage
        $key->recordUsage($request->ip());

        // Set authenticated user (the key creator or a system user)
        if ($key->user_id) {
            Auth::setUser($key->creator);
        }

        // Attach key to request for later use
        $request->merge(['api_key' => $key]);

        return $next($request);
    }

    protected function extractApiKey(Request $request): ?string
    {
        // Check Authorization header (Bearer token)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check X-API-Key header
        if ($request->header('X-API-Key')) {
            return $request->header('X-API-Key');
        }

        // Check query parameter (less secure, for testing only)
        if ($request->query('api_key')) {
            return $request->query('api_key');
        }

        return null;
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => 'unauthorized',
            'message' => $message,
        ], 401);
    }

    protected function forbidden(string $message): Response
    {
        return response()->json([
            'error' => 'forbidden',
            'message' => $message,
        ], 403);
    }
}
