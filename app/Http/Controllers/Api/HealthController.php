<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * @group Health Checks
 *
 * APIs for monitoring system health and readiness.
 * These endpoints are designed for load balancer probes and monitoring systems.
 */
class HealthController extends Controller
{
    /**
     * Basic health check - returns 200 if the application is running.
     *
     * @unauthenticated
     * @response 200 {"status": "ok", "timestamp": "2024-01-15T10:30:00.000000Z"}
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ]);
    }

    /**
     * Liveness probe - returns 200 if the process is alive.
     * Used by Kubernetes/docker to determine if container should be restarted.
     *
     * @unauthenticated
     * @response 200 {"status": "alive", "timestamp": "2024-01-15T10:30:00.000000Z"}
     */
    public function live()
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe - returns 200 if the application is ready to serve traffic.
     * Checks database, cache, and other critical dependencies.
     *
     * @unauthenticated
     * @response 200 {"status": "ready", "checks": {"database": "ok", "cache": "ok", "redis": "ok"}}
     * @response 503 {"status": "not_ready", "checks": {"database": "failed", "cache": "ok", "redis": "ok"}}
     */
    public function ready()
    {
        $checks = [];
        $overallStatus = 'ready';

        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'failed: ' . $e->getMessage();
            $overallStatus = 'not_ready';
        }

        // Check cache
        try {
            Cache::store()->get('health_check');
            $checks['cache'] = 'ok';
        } catch (\Throwable $e) {
            $checks['cache'] = 'failed: ' . $e->getMessage();
            $overallStatus = 'not_ready';
        }

        // Check Redis
        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable $e) {
            $checks['redis'] = 'failed: ' . $e->getMessage();
            $overallStatus = 'not_ready';
        }

        $statusCode = $overallStatus === 'ready' ? 200 : 503;

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }
}
