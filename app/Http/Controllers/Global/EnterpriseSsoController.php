<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\EnterpriseSsoService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnterpriseSsoController extends Controller
{
    protected EnterpriseSsoService $enterpriseSsoService;

    public function __construct(EnterpriseSsoService $enterpriseSsoService)
    {
        $this->enterpriseSsoService = $enterpriseSsoService;
    }

    public function getProviders(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        return response()->json([
            'data' => $this->enterpriseSsoService->getProviders($tenant),
        ]);
    }

    public function authenticateLdap(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:ldap,active_directory',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->enterpriseSsoService->authenticateLdap(
            $request->provider,
            $request->username,
            $request->password,
            $tenant
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 401);
        }

        // Find or create user
        $user = $this->enterpriseSsoService->findOrCreateUser($tenant, $result['user'], $request->provider);

        if (!$user) {
            return response()->json(['error' => 'Unable to create or find user'], 500);
        }

        $token = $user->createToken('enterprise-sso')->plainTextToken;

        return response()->json([
            'message' => 'Enterprise SSO login successful',
            'user' => $user,
            'token' => $token,
            'groups' => $result['groups'] ?? [],
        ]);
    }

    public function authenticateKerberos(Request $request): JsonResponse
    {
        $request->validate([
            'authorization' => 'required|string',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->enterpriseSsoService->authenticateKerberos(
            $request->header('Authorization') ?? $request->authorization,
            $tenant
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 401);
        }

        $token = $result['user']->createToken('kerberos')->plainTextToken;

        return response()->json([
            'message' => 'Kerberos authentication successful',
            'user' => $result['user'],
            'token' => $token,
            'principal' => $result['principal'],
        ]);
    }

    public function initiateSamlAuth(Request $request): JsonResponse
    {
        $request->validate([
            'relay_state' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->enterpriseSsoService->initiateSamlAuth($tenant, [
            'relay_state' => $request->relay_state,
        ]);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result);
    }

    public function handleSamlCallback(Request $request): JsonResponse
    {
        $result = $this->enterpriseSsoService->handleSamlCallback($request->all());

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        // Determine tenant from callback context
        $tenant = Tenant::first(); // In production, determine from relay state or session
        $user = $this->enterpriseSsoService->findOrCreateUser($tenant, $result['user'], 'saml');

        if (!$user) {
            return response()->json(['error' => 'Unable to create or find user'], 500);
        }

        $token = $user->createToken('saml-sso')->plainTextToken;

        return response()->json([
            'message' => 'SAML login successful',
            'user' => $user,
            'token' => $token,
            'redirect_url' => $result['relay_state'] ?? config('app.frontend_url', '/'),
        ]);
    }

    public function syncDirectory(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:ldap,active_directory',
        ]);

        $tenant = auth()->user()->tenant;
        $result = $this->enterpriseSsoService->syncDirectory($tenant, $request->provider);

        return response()->json($result);
    }
}
