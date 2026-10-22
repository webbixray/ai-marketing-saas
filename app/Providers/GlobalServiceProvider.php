<?php

namespace App\Providers;

use App\Services\Global\MultiRegionService;
use App\Services\Global\CdnService;
use App\Services\Global\AdvancedAnalyticsService;
use App\Services\Global\EnterpriseSsoService;
use App\Services\Global\DataGovernanceService;
use App\Services\Global\DisasterRecoveryService;
use App\Services\Global\CostOptimizationService;
use Illuminate\Support\ServiceProvider;

class GlobalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Multi-Region Service
        $this->app->singleton(MultiRegionService::class, function ($app) {
            return new MultiRegionService(config('global.multi_region', []));
        });

        // CDN Service
        $this->app->singleton(CdnService::class, function ($app) {
            return new CdnService(config('global.cdn', []));
        });

        // Advanced Analytics Service
        $this->app->singleton(AdvancedAnalyticsService::class, function ($app) {
            return new AdvancedAnalyticsService(config('global.analytics', []));
        });

        // Enterprise SSO Service
        $this->app->singleton(EnterpriseSsoService::class, function ($app) {
            return new EnterpriseSsoService(config('global.enterprise_sso', []));
        });

        // Data Governance Service
        $this->app->singleton(DataGovernanceService::class, function ($app) {
            return new DataGovernanceService(config('global.data_governance', []));
        });

        // Disaster Recovery Service
        $this->app->singleton(DisasterRecoveryService::class, function ($app) {
            return new DisasterRecoveryService(config('global.disaster_recovery', []));
        });

        // Cost Optimization Service
        $this->app->singleton(CostOptimizationService::class, function ($app) {
            return new CostOptimizationService(config('global.cost_optimization', []));
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/global.php' => config_path('global.php'),
        ], 'global-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
