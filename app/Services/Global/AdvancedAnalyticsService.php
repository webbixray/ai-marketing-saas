<?php

namespace App\Services\Global;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AdvancedAnalyticsService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'data_warehouse' => [
                'enabled' => true,
                'connection' => 'analytics',
                'sync_interval' => 300, // 5 minutes
                'batch_size' => 10000,
            ],
            'real_time' => [
                'enabled' => true,
                'buffer_size' => 1000,
                'flush_interval' => 10, // seconds
            ],
            'aggregations' => [
                'hourly' => ['retention_days' => 90],
                'daily' => ['retention_days' => 365],
                'weekly' => ['retention_days' => 1095], // 3 years
                'monthly' => ['retention_days' => 3650], // 10 years
            ],
            'dashboards' => [
                'cache_ttl' => 60,
                'max_widgets' => 50,
            ],
            'alerts' => [
                'enabled' => true,
                'channels' => ['email', 'slack', 'webhook', 'pagerduty'],
                'evaluation_interval' => 60, // seconds
            ],
            'ml_insights' => [
                'enabled' => true,
                'anomaly_detection' => true,
                'forecasting' => true,
                'segmentation' => true,
                'recommendations' => true,
                'model_refresh_interval' => 86400, // daily
            ],
            'export' => [
                'formats' => ['csv', 'json', 'xlsx', 'pdf'],
                'max_rows' => 1000000,
                'async_threshold' => 10000,
            ],
        ], []);
    }

    /**
     * Track event for analytics.
     */
    public function trackEvent(string $eventName, array $properties = [], array $context = []): void
    {
        $event = [
            'event_id' => \Illuminate\Support\Str::uuid()->toString(),
            'event_name' => $eventName,
            'tenant_id' => auth()->user()->tenant_id ?? $context['tenant_id'] ?? null,
            'user_id' => auth()->id() ?? $context['user_id'] ?? null,
            'session_id' => $context['session_id'] ?? request()->header('X-Session-ID'),
            'properties' => $properties,
            'context' => array_merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referrer' => request()->header('Referer'),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ], $context['context'] ?? []),
            'timestamp' => now()->toISOString(),
            'processed' => false,
        ];

        // Add to real-time buffer
        if ($this->config['real_time']['enabled']) {
            $this->addToRealtimeBuffer($event);
        }

        // Queue for warehouse sync
        $this->queueForWarehouse($event);
    }

    /**
     * Add event to real-time buffer.
     */
    protected function addToRealtimeBuffer(array $event): void
    {
        $key = 'analytics_realtime_buffer';
        $buffer = Cache::get($key, []);
        $buffer[] = $event;
        
        // Keep buffer size limited
        if (count($buffer) > $this->config['real_time']['buffer_size']) {
            $buffer = array_slice($buffer, -$this->config['real_time']['buffer_size']);
        }
        
        Cache::put($key, $buffer, 3600);
    }

    /**
     * Queue event for warehouse sync.
     */
    protected function queueForWarehouse(array $event): void
    {
        DB::table('analytics_events_queue')->insert([
            'event_id' => $event['event_id'],
            'event_name' => $event['event_name'],
            'tenant_id' => $event['tenant_id'],
            'user_id' => $event['user_id'],
            'session_id' => $event['session_id'],
            'properties' => json_encode($event['properties']),
            'context' => json_encode($event['context']),
            'timestamp' => $event['timestamp'],
            'created_at' => now(),
        ]);
    }

    /**
     * Process real-time buffer.
     */
    public function processRealtimeBuffer(): int
    {
        $key = 'analytics_realtime_buffer';
        $buffer = Cache::pull($key, []);
        
        if (empty($buffer)) {
            return 0;
        }

        // Process in batches
        $processed = 0;
        foreach (array_chunk($buffer, 100) as $chunk) {
            // Send to real-time analytics DB (ClickHouse, TimescaleDB, etc.)
            $this->sendToRealtimeDb($chunk);
            $processed += count($chunk);
        }

        return $processed;
    }

    /**
     * Send to real-time database.
     */
    protected function sendToRealtimeDb(array $events): void
    {
        // Would insert into ClickHouse, TimescaleDB, or similar
        // For now, just log
        Log::debug('Sending events to real-time DB', ['count' => count($events)]);
    }

    /**
     * Sync events to data warehouse.
     */
    public function syncToWarehouse(): int
    {
        if (!$this->config['data_warehouse']['enabled']) {
            return 0;
        }

        $batchSize = $this->config['data_warehouse']['batch_size'];
        
        $events = DB::table('analytics_events_queue')
            ->where('synced_at', null)
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $synced = 0;
        foreach ($events as $event) {
            try {
                $this->insertToWarehouse($event);
                
                DB::table('analytics_events_queue')
                    ->where('id', $event->id)
                    ->update(['synced_at' => now()]);
                
                $synced++;
            } catch (\Throwable $e) {
                Log::error('Failed to sync event to warehouse', [
                    'event_id' => $event->event_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    /**
     * Insert event to warehouse.
     */
    protected function insertToWarehouse(object $event): void
    {
        // Would insert into data warehouse (BigQuery, Snowflake, Redshift, etc.)
        Log::debug('Inserting to warehouse', ['event_id' => $event->event_id]);
    }

    /**
     * Get real-time metrics.
     */
    public function getRealtimeMetrics(string $tenantId, array $metrics = [], int $windowMinutes = 5): array
    {
        $since = now()->subMinutes($windowMinutes);
        
        $query = DB::table('analytics_events_queue')
            ->where('tenant_id', $tenantId)
            ->where('timestamp', '>=', $since->toISOString())
            ->whereNotNull('synced_at');

        if (!empty($metrics)) {
            $query->whereIn('event_name', $metrics);
        }

        $events = $query->get();

        return $this->aggregateEvents($events);
    }

    /**
     * Aggregate events into metrics.
     */
    protected function aggregateEvents(Collection $events): array
    {
        $metrics = [
            'total_events' => $events->count(),
            'unique_users' => $events->pluck('user_id')->filter()->unique()->count(),
            'unique_sessions' => $events->pluck('session_id')->filter()->unique()->count(),
            'by_event' => $events->groupBy('event_name')->map->count()->toArray(),
            'by_minute' => $events->groupBy(function ($e) {
                return Carbon::parse($e->timestamp)->format('Y-m-d H:i');
            })->map->count()->toArray(),
        ];

        return $metrics;
    }

    /**
     * Get dashboard data.
     */
    public function getDashboardData(string $tenantId, string $dashboardId, array $params = []): array
    {
        $cacheKey = "dashboard_{$tenantId}_{$dashboardId}_" . md5(json_encode($params));
        
        return Cache::remember($cacheKey, $this->config['dashboards']['cache_ttl'], function () use ($tenantId, $dashboardId, $params) {
            $dashboard = $this->getDashboardConfig($dashboardId);
            
            if (!$dashboard) {
                return ['error' => 'Dashboard not found'];
            }

            $data = [];
            foreach ($dashboard['widgets'] as $widget) {
                $data[$widget['id']] = $this->getWidgetData($tenantId, $widget, $params);
            }

            return [
                'dashboard' => $dashboard,
                'data' => $data,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get dashboard configuration.
     */
    protected function getDashboardConfig(string $dashboardId): ?array
    {
        $dashboards = [
            'executive' => [
                'id' => 'executive',
                'name' => 'Executive Overview',
                'widgets' => [
                    ['id' => 'revenue', 'type' => 'metric', 'metric' => 'revenue', 'period' => 'month'],
                    ['id' => 'users', 'type' => 'metric', 'metric' => 'active_users', 'period' => 'day'],
                    ['id' => 'conversion', 'type' => 'metric', 'metric' => 'conversion_rate', 'period' => 'month'],
                    ['id' => 'churn', 'type' => 'metric', 'metric' => 'churn_rate', 'period' => 'month'],
                    ['id' => 'revenue_trend', 'type' => 'line_chart', 'metric' => 'revenue', 'period' => 'month', 'interval' => 'day'],
                    ['id' => 'user_growth', 'type' => 'line_chart', 'metric' => 'users', 'period' => 'month', 'interval' => 'day'],
                    ['id' => 'funnel', 'type' => 'funnel', 'steps' => ['visit', 'signup', 'activation', 'purchase']],
                    ['id' => 'top_campaigns', 'type' => 'table', 'metric' => 'campaign_performance', 'limit' => 10],
                ],
            ],
            'marketing' => [
                'id' => 'marketing',
                'name' => 'Marketing Analytics',
                'widgets' => [
                    ['id' => 'campaign_roi', 'type' => 'metric', 'metric' => 'campaign_roi'],
                    ['id' => 'cac', 'type' => 'metric', 'metric' => 'customer_acquisition_cost'],
                    ['id' => 'ltv', 'type' => 'metric', 'metric' => 'lifetime_value'],
                    ['id' => 'attribution', 'type' => 'pie_chart', 'metric' => 'attribution_channels'],
                    ['id' => 'campaign_performance', 'type' => 'bar_chart', 'metric' => 'campaign_performance'],
                    ['id' => 'channel_mix', 'type' => 'pie_chart', 'metric' => 'channel_mix'],
                    ['id' => 'ab_test_results', 'type' => 'table', 'metric' => 'ab_test_results'],
                ],
            ],
            'operational' => [
                'id' => 'operational',
                'name' => 'Operational Metrics',
                'widgets' => [
                    ['id' => 'api_latency', 'type' => 'line_chart', 'metric' => 'api_latency_p95', 'period' => 'hour', 'interval' => 'minute'],
                    ['id' => 'error_rate', 'type' => 'line_chart', 'metric' => 'error_rate', 'period' => 'hour', 'interval' => 'minute'],
                    ['id' => 'throughput', 'type' => 'line_chart', 'metric' => 'requests_per_second', 'period' => 'hour', 'interval' => 'minute'],
                    ['id' => 'queue_depth', 'type' => 'metric', 'metric' => 'queue_depth'],
                    ['id' => 'cache_hit_rate', 'type' => 'metric', 'metric' => 'cache_hit_rate'],
                    ['id' => 'db_connections', 'type' => 'line_chart', 'metric' => 'db_connections', 'period' => 'hour', 'interval' => 'minute'],
                ],
            ],
        ];

        return $dashboards[$dashboardId] ?? null;
    }

    /**
     * Get widget data.
     */
    protected function getWidgetData(string $tenantId, array $widget, array $params): array
    {
        $metric = $widget['metric'] ?? '';
        $period = $params['period'] ?? $widget['period'] ?? 'day';
        $interval = $widget['interval'] ?? 'hour';

        return match ($widget['type']) {
            'metric' => $this->getMetricValue($tenantId, $metric, $period),
            'line_chart' => $this->getTimeSeriesData($tenantId, $metric, $period, $interval),
            'bar_chart' => $this->getBarChartData($tenantId, $metric, $period),
            'pie_chart' => $this->getPieChartData($tenantId, $metric, $period),
            'funnel' => $this->getFunnelData($tenantId, $widget['steps'] ?? []),
            'table' => $this->getTableData($tenantId, $metric, $params),
            default => ['error' => 'Unknown widget type'],
        };
    }

    /**
     * Get single metric value.
     */
    protected function getMetricValue(string $tenantId, string $metric, string $period): array
    {
        // Would query pre-aggregated tables
        return [
            'value' => rand(1000, 100000),
            'change' => rand(-20, 20),
            'period' => $period,
            'unit' => $this->getMetricUnit($metric),
        ];
    }

    /**
     * Get time series data.
     */
    protected function getTimeSeriesData(string $tenantId, string $metric, string $period, string $interval): array
    {
        $points = $this->getPeriodPoints($period, $interval);
        $data = [];
        
        foreach ($points as $point) {
            $data[] = [
                'timestamp' => $point,
                'value' => rand(100, 10000),
            ];
        }

        return [
            'data' => $data,
            'metric' => $metric,
            'period' => $period,
            'interval' => $interval,
        ];
    }

    /**
     * Get bar chart data.
     */
    protected function getBarChartData(string $tenantId, string $metric, string $period): array
    {
        $categories = ['Organic', 'Paid', 'Email', 'Social', 'Referral', 'Direct'];
        $data = [];
        
        foreach ($categories as $cat) {
            $data[] = [
                'label' => $cat,
                'value' => rand(100, 10000),
            ];
        }

        return ['data' => $data, 'metric' => $metric];
    }

    /**
     * Get pie chart data.
     */
    protected function getPieChartData(string $tenantId, string $metric, string $period): array
    {
        return $this->getBarChartData($tenantId, $metric, $period);
    }

    /**
     * Get funnel data.
     */
    protected function getFunnelData(string $tenantId, array $steps): array
    {
        $data = [];
        $previous = 10000;
        
        foreach ($steps as $i => $step) {
            $current = $i === 0 ? $previous : (int)($previous * (rand(20, 80) / 100));
            $data[] = [
                'step' => $step,
                'count' => $current,
                'conversion_rate' => $i > 0 ? round(($current / $previous) * 100, 1) : 100,
            ];
            $previous = $current;
        }

        return ['data' => $data];
    }

    /**
     * Get table data.
     */
    protected function getTableData(string $tenantId, string $metric, array $params): array
    {
        $limit = $params['limit'] ?? 10;
        $rows = [];
        
        for ($i = 0; $i < $limit; $i++) {
            $rows[] = [
                'name' => "Item " . ($i + 1),
                'value' => rand(100, 10000),
                'change' => rand(-50, 50),
            ];
        }

        return ['data' => $rows, 'columns' => ['name', 'value', 'change']];
    }

    /**
     * Get period points for time series.
     */
    protected function getPeriodPoints(string $period, string $interval): array
    {
        $end = now();
        $start = match ($period) {
            'hour' => $end->copy()->subHour(),
            'day' => $end->copy()->subDay(),
            'week' => $end->copy()->subWeek(),
            'month' => $end->copy()->subMonth(),
            'quarter' => $end->copy()->subQuarter(),
            'year' => $end->copy()->subYear(),
            default => $end->copy()->subDay(),
        };

        $step = match ($interval) {
            'minute' => 1,
            'hour' => 60,
            'day' => 1440,
            'week' => 10080,
            default => 60,
        };

        $points = [];
        $current = $start->copy();
        
        while ($current <= $end) {
            $points[] = $current->toISOString();
            $current->addMinutes($step);
        }

        return $points;
    }

    /**
     * Get metric unit.
     */
    protected function getMetricUnit(string $metric): string
    {
        $units = [
            'revenue' => '$',
            'users' => 'users',
            'conversion_rate' => '%',
            'churn_rate' => '%',
            'api_latency_p95' => 'ms',
            'error_rate' => '%',
            'requests_per_second' => 'req/s',
            'queue_depth' => 'jobs',
            'cache_hit_rate' => '%',
            'db_connections' => 'connections',
            'campaign_roi' => '%',
            'customer_acquisition_cost' => '$',
            'lifetime_value' => '$',
        ];

        return $units[$metric] ?? '';
    }

    /**
     * Create custom dashboard.
     */
    public function createDashboard(string $tenantId, array $config): array
    {
        $dashboardId = Str::slug($config['name']) . '-' . Str::random(6);
        
        $dashboard = [
            'id' => $dashboardId,
            'tenant_id' => $tenantId,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'widgets' => $config['widgets'] ?? [],
            'layout' => $config['layout'] ?? [],
            'is_default' => $config['is_default'] ?? false,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('analytics_dashboards')->insert($dashboard);

        return $dashboard;
    }

    /**
     * Get tenant dashboards.
     */
    public function getTenantDashboards(string $tenantId): array
    {
        return DB::table('analytics_dashboards')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Update dashboard.
     */
    public function updateDashboard(string $dashboardId, array $config): array
    {
        DB::table('analytics_dashboards')
            ->where('id', $dashboardId)
            ->update(array_merge($config, ['updated_at' => now()]));

        // Clear cache
        Cache::flush(); // Would be more targeted in production

        return $this->getDashboardConfig($dashboardId) ?? [];
    }

    /**
     * Delete dashboard.
     */
    public function deleteDashboard(string $dashboardId): bool
    {
        DB::table('analytics_dashboards')->where('id', $dashboardId)->delete();
        return true;
    }

    /**
     * Run anomaly detection.
     */
    public function runAnomalyDetection(string $tenantId, string $metric, string $period = 'day'): array
    {
        if (!$this->config['ml_insights']['anomaly_detection']) {
            return ['enabled' => false];
        }

        // Get historical data
        $data = $this->getHistoricalData($tenantId, $metric, $period);
        
        if (count($data) < 10) {
            return ['error' => 'Insufficient data for anomaly detection'];
        }

        // Simple statistical anomaly detection (would use ML in production)
        $values = array_column($data, 'value');
        $mean = array_sum($values) / count($values);
        $stdDev = sqrt(array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / count($values));
        
        $threshold = $mean + (3 * $stdDev); // 3 sigma
        $anomalies = array_filter($data, fn($d) => $d['value'] > $threshold);

        return [
            'metric' => $metric,
            'period' => $period,
            'mean' => $mean,
            'std_dev' => $stdDev,
            'threshold' => $threshold,
            'anomalies_found' => count($anomalies),
            'anomalies' => array_values($anomalies),
        ];
    }

    /**
     * Run forecasting.
     */
    public function runForecasting(string $tenantId, string $metric, int $horizonDays = 30): array
    {
        if (!$this->config['ml_insights']['forecasting']) {
            return ['enabled' => false];
        }

        $data = $this->getHistoricalData($tenantId, $metric, '90_days');
        
        if (count($data) < 30) {
            return ['error' => 'Insufficient data for forecasting'];
        }

        // Simple linear regression (would use Prophet, ARIMA, or ML in production)
        $values = array_column($data, 'value');
        $n = count($values);
        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = array_sum(array_map(fn($a, $b) => $a * $b, $x, $values));
        $sumX2 = array_sum(array_map(fn($a) => $a * $a, $x));

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / (($n * $sumX2) - ($sumX * $sumX));
        $intercept = ($sumY - $slope * $sumX) / $n;

        $forecast = [];
        for ($i = 1; $i <= $horizonDays; $i++) {
            $predicted = $intercept + $slope * ($n + $i);
            $forecast[] = [
                'date' => now()->addDays($i)->format('Y-m-d'),
                'predicted' => max(0, round($predicted, 2)),
                'lower_bound' => max(0, round($predicted * 0.8, 2)),
                'upper_bound' => round($predicted * 1.2, 2),
            ];
        }

        return [
            'metric' => $metric,
            'model' => 'linear_regression',
            'slope' => $slope,
            'intercept' => $intercept,
            'forecast' => $forecast,
        ];
    }

    /**
     * Get historical data for ML.
     */
    protected function getHistoricalData(string $tenantId, string $metric, string $period): array
    {
        // Would query from aggregated tables
        // Returning mock data for now
        $days = match ($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            '90_days' => 90,
            default => 30,
        };

        $data = [];
        for ($i = $days; $i >= 1; $i--) {
            $data[] = [
                'date' => now()->subDays($i)->format('Y-m-d'),
                'value' => rand(1000, 10000),
            ];
        }

        return $data;
    }

    /**
     * Generate ML insights.
     */
    public function generateInsights(string $tenantId): array
    {
        if (!$this->config['ml_insights']['enabled']) {
            return [];
        }

        $insights = [];

        // Revenue insights
        $revenueAnomaly = $this->runAnomalyDetection($tenantId, 'revenue', 'day');
        if (!empty($revenueAnomaly['anomalies'])) {
            $insights[] = [
                'type' => 'anomaly',
                'severity' => 'high',
                'title' => 'Revenue Anomaly Detected',
                'description' => count($revenueAnomaly['anomalies']) . ' anomalous days detected in revenue',
                'metric' => 'revenue',
                'data' => $revenueAnomaly,
            ];
        }

        // User growth insights
        $userForecast = $this->runForecasting($tenantId, 'active_users', 30);
        if (!empty($userForecast['forecast'])) {
            $trend = $userForecast['slope'] > 0 ? 'growing' : 'declining';
            $insights[] = [
                'type' => 'forecast',
                'severity' => 'info',
                'title' => 'User Growth Trend',
                'description' => "Active users are {$trend} with a projected change of " . round($userForecast['slope'] * 30) . " users over 30 days",
                'metric' => 'active_users',
                'data' => $userForecast,
            ];
        }

        return $insights;
    }

    /**
     * Export analytics data.
     */
    public function exportData(string $tenantId, array $params): array
    {
        $format = $params['format'] ?? 'csv';
        $metrics = $params['metrics'] ?? [];
        $startDate = $params['start_date'] ?? now()->subMonth()->toISOString();
        $endDate = $params['end_date'] ?? now()->toISOString();
        $async = ($params['row_count'] ?? 0) > $this->config['export']['async_threshold'];

        if ($async) {
            // Queue export job
            $exportId = Str::uuid()->toString();
            
            DB::table('analytics_exports')->insert([
                'id' => $exportId,
                'tenant_id' => $tenantId,
                'format' => $format,
                'metrics' => json_encode($metrics),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'pending',
                'requested_by' => auth()->id(),
                'created_at' => now(),
            ]);

            // ExportJob::dispatch($exportId);

            return [
                'export_id' => $exportId,
                'status' => 'pending',
                'message' => 'Export queued. You will be notified when ready.',
            ];
        }

        // Synchronous export
        $data = $this->fetchExportData($tenantId, $metrics, $startDate, $endDate);
        
        return $this->formatExport($data, $format);
    }

    /**
     * Fetch export data.
     */
    protected function fetchExportData(string $tenantId, array $metrics, string $startDate, string $endDate): array
    {
        // Would query warehouse
        return [
            'headers' => ['timestamp', 'event_name', 'user_id', 'properties'],
            'rows' => [],
        ];
    }

    /**
     * Format export data.
     */
    protected function formatExport(array $data, string $format): array
    {
        return match ($format) {
            'csv' => $this->formatCsv($data),
            'json' => $this->formatJson($data),
            'xlsx' => $this->formatXlsx($data),
            default => ['error' => 'Unsupported format'],
        };
    }

    protected function formatCsv(array $data): array
    {
        $csv = implode(',', $data['headers']) . "\n";
        foreach ($data['rows'] as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }
        return ['content' => $csv, 'mime' => 'text/csv', 'extension' => 'csv'];
    }

    protected function formatJson(array $data): array
    {
        $json = [];
        foreach ($data['rows'] as $row) {
            $json[] = array_combine($data['headers'], $row);
        }
        return ['content' => json_encode($json, JSON_PRETTY_PRINT), 'mime' => 'application/json', 'extension' => 'json'];
    }

    protected function formatXlsx(array $data): array
    {
        // Would use PhpSpreadsheet
        return ['error' => 'XLSX export not implemented'];
    }
}
