<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\ConfigureSsoRequest;
use App\Http\Requests\Enterprise\SsoCallbackRequest;
use App\Services\Enterprise\SsoService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SsoController extends Controller
{
    protected SsoService $ssoService;

    public function __construct(SsoService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    /**
     * Get available SSO providers for the current tenant.
     */
    public function getProviders(): JsonResponse
    {
        $tenant = $this->getTenant();
        $providers = $this->ssoService->getAvailableProviders($tenant);

        return response()->json([
            'data' => $providers,
        ]);
    }

    /**
     * Configure SSO for the tenant.
     */
    public function configure(ConfigureSsoRequest $request): JsonResponse
    {
        $tenant = $this->getTenant();
        
        $config = $request->validated();
        $tenant->update([
            'sso_config' => array_merge($tenant->sso_config ?? [], $config),
        ]);

        return response()->json([
            'message' => 'SSO configuration updated',
            'data' => $this->ssoService->getAvailableProviders($tenant->fresh()),
        ]);
    }

    /**
     * Initiate SSO login for a provider.
     */
    public function initiateLogin(string $provider, Request $request): JsonResponse
    {
        $tenant = $this->getTenant();
        
        $options = $request->only(['redirect_uri', 'relay_state']);
        $result = $this->ssoService->initiateLogin($provider, $tenant, $options);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    /**
     * Handle SSO callback.
     */
    public function handleCallback(string $provider, SsoCallbackRequest $request): JsonResponse
    {
        $result = $this->ssoService->handleCallback($provider, $request->all());

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        // Find or create user
        $tenant = Tenant::first(); // In real implementation, determine tenant from callback
        $user = $this->ssoService->findOrCreateUser($tenant, $result['user'], $provider);

        if (!$user) {
            return response()->json(['error' => 'Unable to create or find user'], 500);
        }

        // Generate API token
        $token = $user->createToken('sso-login')->plainTextToken;

        return response()->json([
            'message' => 'SSO login successful',
            'user' => $user,
            'token' => $token,
            'redirect_url' => $result['relay_state'] ?? config('app.frontend_url', '/'),
        ]);
    }

    /**
     * Get SSO configuration for current tenant.
     */
    public function getConfig(): JsonResponse
    {
        $tenant = $this->getTenant();
        
        return response()->json([
            'data' => $tenant->sso_config ?? [],
        ]);
    }

    /**
     * Delete SSO configuration.
     */
    public function deleteConfig(): JsonResponse
    {
        $tenant = $this->getTenant();
        
        $tenant->update(['sso_config' => []]);

        return response()->json(['message' => 'SSO configuration deleted']);
    }

    /**
     * Get SSO audit logs.
     */
    public function getAuditLogs(): JsonResponse
    {
        $tenant = $this->getTenant();
        
        $logs = \App\Models\SsoAuditLog::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->paginate(request()->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * Get the current tenant.
     */
    protected function getTenant(): Tenant
    {
        return auth()->user()->tenant;
    }
}
