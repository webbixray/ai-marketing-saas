<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if the model doesn't have a tenant_id column
        if (!Schema::hasColumn($model->getTable(), 'tenant_id')) {
            return;
        }

        $tenantId = null;

        // Check if we have a bearer token in the request (Sanctum authentication)
        $token = Request::bearerToken();
        if ($token) {
            // Sanctum token format: "id|token"
            // We can extract the user ID from the token without triggering the guard's user method
            $parts = explode('|', $token);
            if (count($parts) === 2 && ctype_digit($parts[0])) {
                $userId = (int)$parts[0];
                // Get the tenant_id directly from the users table to avoid triggering the global scope on the User model
                $tenantId = DB::table('users')->where('id', $userId)->value('tenant_id');
            }
        } else {
            // Fallback to session for web requests
            $tenantId = session('tenant_id');
        }

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}