<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel's authentication layer if configured.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // Debug: Check if the file exists
            if (!file_exists(base_path('routes/api.php'))) {
                throw new \Exception("API routes file not found at: " . base_path('routes/api.php'));
            }
            
            $this->mapApiRoutes();
            $this->mapWebRoutes();
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Global API rate limiter - 100 requests per minute per IP/user
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        // Stricter rate limiter for authentication endpoints - 10 requests per minute
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Rate limiter for health checks - more generous
        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(1000)->by($request->ip());
        });

        // Rate limiter for billing endpoints - moderate
        RateLimiter::for('billing', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for reporting endpoints - moderate
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        // API v1 (current version)
        Route::prefix('api/v1')
             ->middleware('api')
             ->group(base_path('routes/api.php'));

        // API v2 placeholder for future
        // Route::prefix('api/v2')
        //      ->middleware('api')
        //      ->group(base_path('routes/api_v2.php'));
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
             ->group(base_path('routes/web.php'));
    }
}
