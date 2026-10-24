<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    /**
     * Render the performance monitoring dashboard (admin only).
     */
    public function index()
    {
        $stats = [
            'memory_usage' => memory_get_usage(),
            'peak_memory_usage' => memory_get_peak_usage(),
            'database_queries' => count(DB::getQueryLog()),
            'cache_keys' => $this->safeCacheKeys(),
            'timestamp' => now(),
        ];

        return view('admin.performance', $stats);
    }

    /**
     * JSON system status (admin only).
     */
    public function apiStatus()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'uptime' => isset($_SERVER['REQUEST_TIME']) ? time() - $_SERVER['REQUEST_TIME'] : null,
        ]);
    }

    /**
     * Cache keys, guarded for stores that do not support listing (e.g. array).
     *
     * ArrayStore has no keys() method at all, so calling it raises an
     * \Error rather than a BadMethodCallException.
     *
     * @return array<int, string>
     */
    protected function safeCacheKeys(): array
    {
        try {
            if (! method_exists(Cache::getStore(), 'keys')) {
                return [];
            }

            return Cache::keys();
        } catch (\Throwable) {
            return [];
        }
    }
}
