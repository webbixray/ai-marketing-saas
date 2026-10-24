<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate SCIM provisioning requests.
 *
 * SCIM endpoints are machine-to-machine: the customer's identity provider
 * presents the tenant's shared bearer token (tenant_scim_config.bearer_token),
 * NOT a user's Sanctum token. A missing, unknown, or disabled token yields
 * a SCIM-format 401 so provisioning clients fail fast with a standard body.
 *
 * On success the owning Tenant is bound into the container so
 * ScimService (constructor-injected) operates on the right tenant.
 */
class ScimAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $config = $token
            ? DB::table('tenant_scim_config')
                ->where('bearer_token', $token)
                ->where('enabled', true)
                ->first()
            : null;

        if (! $config) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '401',
                'detail' => 'Invalid or missing SCIM bearer token.',
            ], 401, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($config->tenant_id);

        if (! $tenant) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '401',
                'detail' => 'SCIM configuration is orphaned.',
            ], 401);
        }

        // Bind the tenant for this request so ScimService gets the right one.
        // The provider factory reads the 'tenant' alias, so bind both.
        app()->instance(Tenant::class, $tenant);
        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
