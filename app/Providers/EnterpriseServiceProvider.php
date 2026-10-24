<?php

namespace App\Providers;

use App\Services\Enterprise\SsoService;
use App\Services\Enterprise\ScimService;
use App\Services\Enterprise\WhiteLabelService;
use App\Services\Enterprise\AuditComplianceService;
use App\Services\Enterprise\AdvancedRbacService;
use App\Services\Enterprise\MarketplaceService;
use App\Models\Tenant;
use Illuminate\Support\ServiceProvider;

class EnterpriseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SSO Service
        $this->app->singleton(SsoService::class, function ($app) {
            return new SsoService(config('enterprise.sso', []));
        });

        // White Label Service
        $this->app->singleton(WhiteLabelService::class, function ($app) {
            return new WhiteLabelService(config('enterprise.white_label', []));
        });

        // Audit Compliance Service
        $this->app->singleton(AuditComplianceService::class, function ($app) {
            return new AuditComplianceService(config('enterprise.audit_compliance', []));
        });

        // Advanced RBAC Service
        $this->app->singleton(AdvancedRbacService::class, function ($app) {
            return new AdvancedRbacService(config('enterprise.advanced_rbac', []));
        });

        // Marketplace Service
        $this->app->singleton(MarketplaceService::class, function ($app) {
            return new MarketplaceService(config('enterprise.marketplace', []));
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/enterprise.php' => config_path('enterprise.php'),
        ], 'enterprise-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register SCIM service factory.
        // The ScimAuth middleware binds the authenticated tenant (Tenant::class
        // and 'tenant' aliases) for SCIM requests. Fallback: the authenticated
        // user's own tenant. Previously this factory called app('tenant')
        // unconditionally — that alias was never bound, so every SCIM request
        // 500'd with BindingResolutionException.
        $this->app->bind(ScimService::class, function ($app) {
            $tenant = null;

            if ($app->bound(Tenant::class)) {
                $tenant = $app->make(Tenant::class);
            } elseif ($app->bound('tenant')) {
                $tenant = $app->make('tenant');
            } elseif (auth()->user()?->tenant_id) {
                $tenant = Tenant::withoutGlobalScopes()->find(auth()->user()->tenant_id);
            }

            if (! $tenant) {
                throw new \RuntimeException('SCIM tenant could not be resolved');
            }

            return new ScimService($tenant, config('enterprise.scim', []));
        });
    }
}
