<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Global multi-tenant scope.
 *
 * Resolution order for the current tenant:
 *  1. Authenticated user's tenant_id (covers web sessions and Sanctum tokens
 *     resolved by any guard)
 *  2. Bearer token user id (raw DB lookup; used where the guard has not
 *     resolved the user yet)
 *  3. Session tenant (guest web flows that pre-bind a tenant)
 *
 * Users without a tenant (platform administrators) are not scoped and see
 * all tenants.
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if the model doesn't have a tenant_id column
        if (! Schema::hasColumn($model->getTable(), 'tenant_id')) {
            return;
        }

        $tenantId = $this->resolveTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }

    /**
     * Determine the tenant id that should scope the current query.
     */
    protected function resolveTenantId(): ?int
    {
        // 1. Authenticated user (web session or any resolved Sanctum guard)
        try {
            if (Auth::hasUser()) {
                $tenantId = (int) (Auth::user()->tenant_id ?? 0);

                return $tenantId > 0 ? $tenantId : null;
            }
        } catch (\Throwable) {
            // Auth not resolvable (early boot) — fall through
        }

        // 2. Bearer token: Sanctum personal tokens are "id|token"
        $token = Request::bearerToken();
        if ($token) {
            $parts = explode('|', $token);
            if (count($parts) === 2 && ctype_digit($parts[0])) {
                // Raw lookup to avoid triggering the global scope on the User model
                $tenantId = DB::table('users')->where('id', (int) $parts[0])->value('tenant_id');

                return $tenantId !== null ? (int) $tenantId : null;
            }
        }

        // 3. Session fallback for guest web requests
        $sessionTenant = session('tenant_id');

        return $sessionTenant ? (int) $sessionTenant : null;
    }
}
