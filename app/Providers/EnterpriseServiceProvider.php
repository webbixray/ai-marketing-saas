<?php

namespace App\Providers;

use App\Services\Enterprise\SsoService;
use App\Services\Enterprise\ScimService;
use App\Services\Enterprise\WhiteLabelService;
use App\Services\Enterprise\AuditComplianceService;
use App\Services\Enterprise\AdvancedRbacService;
use App\Services\Enterprise\MarketplaceService;
use Illuminate\Support\ServiceProvider;

class EnterpriseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SSO Service
        $this->app->singleton(SsoService::class, function ($app) {
            return new SsoService(config('enterprise.sso', []));
        });

        // SCIM Service
        $this->app->singleton(ScimService::class, function ($app) {
            // SCIM service needs tenant, so we'll bind it as a factory
            return null; // Will be resolved per-request
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

        // Register SCIM service factory
        $this->app->bind(ScimService::class, function ($app) {
            return new ScimService(
                auth()->user()->tenant ?? app('tenant'),
                config('enterprise.scim', [])
            );
        });
    }
}
