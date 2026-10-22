<?php

namespace App\Services\Global;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CostOptimizationService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'providers' => [
                'aws' => [
                    'enabled' => true,
                    'access_key' => env('COST_AWS_ACCESS_KEY'),
                    'secret_key' => env('COST_AWS_SECRET_KEY'),
                    'region' => env('COST_AWS_REGION', 'us-east-1'),
                    'account_id' => env('COST_AWS_ACCOUNT_ID'),
                    'cur_bucket' => env('COST_AWS_CUR_BUCKET'),
                    'cur_prefix' => env('COST_AWS_CUR_PREFIX'),
                ],
                'gcp' => [
                    'enabled' => false,
                    'project_id' => env('COST_GCP_PROJECT_ID'),
                    'credentials' => env('COST_GCP_CREDENTIALS'),
                    'billing_export_dataset' => env('COST_GCP_BILLING_DATASET'),
                ],
                'azure' => [
                    'enabled' => false,
                    'subscription_id' => env('COST_AZURE_SUBSCRIPTION_ID'),
                    'tenant_id' => env('COST_AZURE_TENANT_ID'),
                    'client_id' => env('COST_AZURE_CLIENT_ID'),
                    'client_secret' => env('COST_AZURE_CLIENT_SECRET'),
                ],
            ],
            'analysis' => [
                'enabled' => true,
                'schedule' => '0 6 * * *', // Daily at 6 AM
                'lookback_days' => 30,
                'anomaly_threshold' => 0.2, // 20% change
                'idle_threshold_days' => 7,
                'rightsizing_enabled' => true,
                'reserved_instances_enabled' => true,
                'savings_plans_enabled' => true,
                'spot_instances_enabled' => true,
            ],
            'budgets' => [
                'enabled' => true,
                'alert_thresholds' => [0.5, 0.75, 0.9, 1.0],
                'alert_channels' => ['email', 'slack'],
            ],
            'recommendations' => [
                'min_savings' => 10, // Minimum $10/month savings
                'confidence_threshold' => 0.7,
                'auto_apply' => false,
                'require_approval' => true,
            ],
            'reporting' => [
                'enabled' => true,
                'schedule' => '0 0 1 * *', // Monthly
                'formats' => ['pdf', 'csv', 'json'],
                'recipients' => [],
            ],
        ], []);
    }

    /**
     * Get cost overview for tenant.
     */
    public function getCostOverview(Tenant $tenant, string $period = 'month'): array
    {
        $periodStart = $this->getPeriodStart($period);
        $periodEnd = now();

        // Get costs from all providers
        $costs = [];
        foreach ($this->config['providers'] as $provider => $config) {
            if ($config['enabled']) {
                $costs[$provider] = $this->getProviderCosts($provider, $tenant, $periodStart, $periodEnd);
            }
        }

        // Aggregate
        $totalCost = array_sum(array_column($costs, 'total'));
        $byService = $this->aggregateByService($costs);
        $byRegion = $this->aggregateByRegion($costs);
        $dailyTrend = $this->getDailyTrend($costs, $periodStart, $periodEnd);

        return [
            'period' => $period,
            'period_start' => $periodStart->toISOString(),
            'period_end' => $periodEnd->toISOString(),
            'total_cost' => round($totalCost, 2),
            'currency' => 'USD',
            'by_provider' => $costs,
            'by_service' => $byService,
            'by_region' => $byRegion,
            'daily_trend' => $dailyTrend,
            'change_from_previous' => $this->calculatePeriodChange($tenant, $period),
        ];
    }

    /**
     * Get costs from specific provider.
     */
    protected function getProviderCosts(string $provider, Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return match ($provider) {
            'aws' => $this->getAwsCosts($tenant, $start, $end),
            'gcp' => $this->getGcpCosts($tenant, $start, $end),
            'azure' => $this->getAzureCosts($tenant, $start, $end),
            default => ['total' => 0, 'by_service' => [], 'by_region' => [], 'daily' => []],
        };
    }

    /**
     * Get AWS costs via Cost Explorer API.
     */
    protected function getAwsCosts(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        $config = $this->config['providers']['aws'];
        
        if (!$config['enabled'] || empty($config['access_key'])) {
            return $this->getMockCosts('aws');
        }

        // Would use AWS Cost Explorer API
        // Simplified mock for now
        return $this->getMockCosts('aws');
    }

    /**
     * Get GCP costs.
     */
    protected function getGcpCosts(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return $this->getMockCosts('gcp');
    }

    /**
     * Get Azure costs.
     */
    protected function getAzureCosts(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return $this->getMockCosts('azure');
    }

    /**
     * Generate mock cost data for development.
     */
    protected function getMockCosts(string $provider): array
    {
        $services = [
            'aws' => ['EC2', 'RDS', 'S3', 'Lambda', 'CloudFront', 'ELB', 'NAT Gateway', 'EBS'],
            'gcp' => ['Compute Engine', 'Cloud SQL', 'Cloud Storage', 'Cloud Functions', 'Cloud CDN', 'Load Balancing'],
            'azure' => ['Virtual Machines', 'SQL Database', 'Blob Storage', 'Functions', 'CDN', 'Load Balancer'],
        ];

        $byService = [];
        $total = 0;
        
        foreach ($services[$provider] as $service) {
            $cost = rand(100, 5000);
            $byService[$service] = $cost;
            $total += $cost;
        }

        $regions = ['us-east-1' => 0.5, 'us-west-2' => 0.3, 'eu-west-1' => 0.2];
        $byRegion = [];
        foreach ($regions as $region => $weight) {
            $byRegion[$region] = round($total * $weight, 2);
        }

        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $daily[$date] = round($total / 30 * (0.8 + rand(0, 40) / 100), 2);
        }

        return [
            'total' => $total,
            'by_service' => $byService,
            'by_region' => $byRegion,
            'daily' => $daily,
        ];
    }

    /**
     * Aggregate costs by service across providers.
     */
    protected function aggregateByService(array $costs): array
    {
        $aggregated = [];
        
        foreach ($costs as $provider => $data) {
            foreach ($data['by_service'] as $service => $cost) {
                $key = "{$provider}:{$service}";
                $aggregated[$key] = ($aggregated[$key] ?? 0) + $cost;
            }
        }

        arsort($aggregated);
        return $aggregated;
    }

    /**
     * Aggregate costs by region across providers.
     */
    protected function aggregateByRegion(array $costs): array
    {
        $aggregated = [];
        
        foreach ($costs as $provider => $data) {
            foreach ($data['by_region'] as $region => $cost) {
                $aggregated[$region] = ($aggregated[$region] ?? 0) + $cost;
            }
        }

        arsort($aggregated);
        return $aggregated;
    }

    /**
     * Get daily cost trend.
     */
    protected function getDailyTrend(array $costs, Carbon $start, Carbon $end): array
    {
        $trend = [];
        $current = $start->copy();
        
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $total = 0;
            
            foreach ($costs as $provider => $data) {
                $total += $data['daily'][$date] ?? 0;
            }
            
            $trend[$date] = round($total, 2);
            $current->addDay();
        }

        return $trend;
    }

    /**
     * Calculate change from previous period.
     */
    protected function calculatePeriodChange(Tenant $tenant, string $period): array
    {
        // Would compare with previous period
        return [
            'percentage' => rand(-15, 20),
            'amount' => rand(-500, 1000),
        ];
    }

    /**
     * Get period start date.
     */
    protected function getPeriodStart(string $period): Carbon
    {
        return match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    /**
     * Detect cost anomalies.
     */
    public function detectAnomalies(Tenant $tenant, string $period = 'month'): array
    {
        if (!$this->config['analysis']['enabled']) {
            return ['enabled' => false];
        }

        $overview = $this->getCostOverview($tenant, $period);
        $threshold = $this->config['analysis']['anomaly_threshold'];
        $anomalies = [];

        // Check service-level anomalies
        foreach ($overview['by_service'] as $service => $cost) {
            $previousCost = $this->getPreviousPeriodCost($tenant, $service, $period);
            
            if ($previousCost > 0) {
                $change = ($cost - $previousCost) / $previousCost;
                
                if (abs($change) > $threshold) {
                    $anomalies[] = [
                        'type' => 'service_cost',
                        'service' => $service,
                        'current_cost' => $cost,
                        'previous_cost' => $previousCost,
                        'change_percentage' => round($change * 100, 1),
                        'severity' => abs($change) > 0.5 ? 'high' : 'medium',
                        'direction' => $change > 0 ? 'increase' : 'decrease',
                        'detected_at' => now()->toISOString(),
                    ];
                }
            }
        }

        // Check daily spikes
        $daily = $overview['daily_trend'];
        $values = array_values($daily);
        $avg = array_sum($values) / count($values);
        $stdDev = sqrt(array_sum(array_map(fn($v) => pow($v - $avg, 2), $values)) / count($values));
        $spikeThreshold = $avg + (2 * $stdDev);

        foreach ($daily as $date => $cost) {
            if ($cost > $spikeThreshold) {
                $anomalies[] = [
                    'type' => 'daily_spike',
                    'date' => $date,
                    'cost' => $cost,
                    'expected' => round($avg, 2),
                    'deviation' => round(($cost - $avg) / $avg * 100, 1),
                    'severity' => 'medium',
                    'detected_at' => now()->toISOString(),
                ];
            }
        }

        return [
            'period' => $period,
            'anomalies_found' => count($anomalies),
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Get previous period cost for service.
     */
    protected function getPreviousPeriodCost(Tenant $tenant, string $service, string $period): float
    {
        // Would query historical data
        return rand(100, 5000);
    }

    /**
     * Generate rightsizing recommendations.
     */
    public function generateRightsizingRecommendations(Tenant $tenant): array
    {
        if (!$this->config['analysis']['rightsizing_enabled']) {
            return ['enabled' => false];
        }

        $recommendations = [];

        // Would analyze CloudWatch/Monitoring metrics for CPU, memory, network utilization
        // Mock recommendations for now
        $instances = [
            ['id' => 'i-12345', 'type' => 'm5.xlarge', 'cpu_avg' => 15, 'memory_avg' => 30, 'monthly_cost' => 150],
            ['id' => 'i-67890', 'type' => 'r5.2xlarge', 'cpu_avg' => 25, 'memory_avg' => 40, 'monthly_cost' => 500],
            ['id' => 'i-abcde', 'type' => 'c5.4xlarge', 'cpu_avg' => 10, 'memory_avg' => 20, 'monthly_cost' => 600],
        ];

        foreach ($instances as $instance) {
            if ($instance['cpu_avg'] < 30 && $instance['memory_avg'] < 50) {
                // Recommend downsizing
                $currentType = $instance['type'];
                $recommendedType = $this->getSmallerInstanceType($currentType);
                $savings = $this->calculateSavings($currentType, $recommendedType);

                if ($savings >= $this->config['recommendations']['min_savings']) {
                    $recommendations[] = [
                        'type' => 'rightsizing',
                        'resource_id' => $instance['id'],
                        'current_type' => $currentType,
                        'recommended_type' => $recommendedType,
                        'current_cost' => $instance['monthly_cost'],
                        'estimated_cost' => $instance['monthly_cost'] - $savings,
                        'monthly_savings' => $savings,
                        'annual_savings' => $savings * 12,
                        'cpu_utilization' => $instance['cpu_avg'],
                        'memory_utilization' => $instance['memory_avg'],
                        'confidence' => 0.85,
                        'risk' => 'low',
                        'action' => 'resize',
                    ];
                }
            }
        }

        return [
            'recommendations_count' => count($recommendations),
            'total_monthly_savings' => array_sum(array_column($recommendations, 'monthly_savings')),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get smaller instance type.
     */
    protected function getSmallerInstanceType(string $type): string
    {
        $downsizeMap = [
            'm5.xlarge' => 'm5.large',
            'm5.2xlarge' => 'm5.xlarge',
            'm5.4xlarge' => 'm5.2xlarge',
            'r5.xlarge' => 'r5.large',
            'r5.2xlarge' => 'r5.xlarge',
            'r5.4xlarge' => 'r5.2xlarge',
            'c5.xlarge' => 'c5.large',
            'c5.2xlarge' => 'c5.xlarge',
            'c5.4xlarge' => 'c5.2xlarge',
            't3.xlarge' => 't3.large',
            't3.2xlarge' => 't3.xlarge',
        ];

        return $downsizeMap[$type] ?? $type;
    }

    /**
     * Calculate savings from instance change.
     */
    protected function calculateSavings(string $current, string $recommended): float
    {
        $pricing = [
            'm5.large' => 75, 'm5.xlarge' => 150, 'm5.2xlarge' => 300, 'm5.4xlarge' => 600,
            'r5.large' => 100, 'r5.xlarge' => 200, 'r5.2xlarge' => 400, 'r5.4xlarge' => 800,
            'c5.large' => 65, 'c5.xlarge' => 130, 'c5.2xlarge' => 260, 'c5.4xlarge' => 520,
            't3.large' => 60, 't3.xlarge' => 120, 't3.2xlarge' => 240,
        ];

        return ($pricing[$current] ?? 0) - ($pricing[$recommended] ?? 0);
    }

    /**
     * Generate reserved instance recommendations.
     */
    public function generateRiRecommendations(Tenant $tenant): array
    {
        if (!$this->config['analysis']['reserved_instances_enabled']) {
            return ['enabled' => false];
        }

        // Would analyze usage patterns for steady-state workloads
        return [
            'recommendations_count' => 0,
            'total_monthly_savings' => 0,
            'recommendations' => [],
        ];
    }

    /**
     * Generate savings plans recommendations.
     */
    public function generateSavingsPlansRecommendations(Tenant $tenant): array
    {
        if (!$this->config['analysis']['savings_plans_enabled']) {
            return ['enabled' => false];
        }

        return [
            'recommendations_count' => 0,
            'total_monthly_savings' => 0,
            'recommendations' => [],
        ];
    }

    /**
     * Generate spot instance recommendations.
     */
    public function generateSpotRecommendations(Tenant $tenant): array
    {
        if (!$this->config['analysis']['spot_instances_enabled']) {
            return ['enabled' => false];
        }

        return [
            'recommendations_count' => 0,
            'total_monthly_savings' => 0,
            'recommendations' => [],
        ];
    }

    /**
     * Get all optimization recommendations.
     */
    public function getAllRecommendations(Tenant $tenant): array
    {
        $all = [];

        $rightsizing = $this->generateRightsizingRecommendations($tenant);
        if (!empty($rightsizing['recommendations'])) {
            $all = array_merge($all, $rightsizing['recommendations']);
        }

        $ri = $this->generateRiRecommendations($tenant);
        if (!empty($ri['recommendations'])) {
            $all = array_merge($all, $ri['recommendations']);
        }

        $sp = $this->generateSavingsPlansRecommendations($tenant);
        if (!empty($sp['recommendations'])) {
            $all = array_merge($all, $sp['recommendations']);
        }

        $spot = $this->generateSpotRecommendations($tenant);
        if (!empty($spot['recommendations'])) {
            $all = array_merge($all, $spot['recommendations']);
        }

        // Filter by confidence and savings
        $filtered = array_filter($all, function ($r) {
            return ($r['confidence'] ?? 1) >= $this->config['recommendations']['confidence_threshold']
                && ($r['monthly_savings'] ?? 0) >= $this->config['recommendations']['min_savings'];
        });

        // Sort by savings descending
        usort($filtered, fn($a, $b) => ($b['monthly_savings'] ?? 0) <=> ($a['monthly_savings'] ?? 0));

        return [
            'total_recommendations' => count($filtered),
            'total_monthly_savings' => array_sum(array_column($filtered, 'monthly_savings')),
            'total_annual_savings' => array_sum(array_column($filtered, 'annual_savings')),
            'recommendations' => array_values($filtered),
        ];
    }

    /**
     * Apply recommendation.
     */
    public function applyRecommendation(Tenant $tenant, string $recommendationId, array $options = []): array
    {
        // Would execute the actual infrastructure change
        // Requires approval if configured
        if ($this->config['recommendations']['require_approval'] && !($options['approved'] ?? false)) {
            return [
                'success' => false,
                'error' => 'Approval required',
                'requires_approval' => true,
            ];
        }

        return [
            'success' => true,
            'message' => 'Recommendation applied',
            'recommendation_id' => $recommendationId,
        ];
    }

    /**
     * Create budget.
     */
    public function createBudget(Tenant $tenant, array $budget): array
    {
        if (!$this->config['budgets']['enabled']) {
            return ['success' => false, 'error' => 'Budgets not enabled'];
        }

        $budgetId = Str::uuid()->toString();
        
        $budget = array_merge([
            'id' => $budgetId,
            'tenant_id' => $tenant->id,
            'name' => '',
            'amount' => 0,
            'currency' => 'USD',
            'period' => 'monthly', // daily, weekly, monthly, quarterly, yearly
            'start_date' => now()->startOfMonth()->toISOString(),
            'end_date' => null,
            'filters' => [], // provider, service, region, tags
            'alert_thresholds' => $this->config['budgets']['alert_thresholds'],
            'alert_channels' => $this->config['budgets']['alert_channels'],
            'enabled' => true,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $budget);

        DB::table('cost_budgets')->insert($budget);

        return $budget;
    }

    /**
     * Get budgets for tenant.
     */
    public function getBudgets(Tenant $tenant): array
    {
        return DB::table('cost_budgets')
            ->where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Check budget status.
     */
    public function checkBudgets(Tenant $tenant): array
    {
        $budgets = $this->getBudgets($tenant);
        $alerts = [];

        foreach ($budgets as $budget) {
            $spent = $this->getBudgetSpend($tenant, $budget);
            $percentage = $budget->amount > 0 ? $spent / $budget->amount : 0;

            foreach ($budget->alert_thresholds as $threshold) {
                if ($percentage >= $threshold) {
                    // Check if already alerted for this threshold
                    $alertKey = "budget_alert_{$budget->id}_{$threshold}";
                    if (!Cache::has($alertKey)) {
                        $alerts[] = [
                            'budget_id' => $budget->id,
                            'budget_name' => $budget->name,
                            'threshold' => $threshold,
                            'percentage' => round($percentage * 100, 1),
                            'spent' => $spent,
                            'limit' => $budget->amount,
                        ];
                        Cache::put($alertKey, true, 86400); // 24 hours
                    }
                }
            }
        }

        return [
            'budgets_checked' => count($budgets),
            'alerts_triggered' => count($alerts),
            'alerts' => $alerts,
        ];
    }

    /**
     * Get budget spend.
     */
    protected function getBudgetSpend(Tenant $tenant, object $budget): float
    {
        $filters = json_decode($budget->filters ?? '[]', true);
        $periodStart = Carbon::parse($budget->start_date);
        
        if ($budget->end_date) {
            $periodEnd = Carbon::parse($budget->end_date);
        } else {
            $periodEnd = match ($budget->period) {
                'daily' => $periodStart->copy()->endOfDay(),
                'weekly' => $periodStart->copy()->endOfWeek(),
                'monthly' => $periodStart->copy()->endOfMonth(),
                'quarterly' => $periodStart->copy()->endOfQuarter(),
                'yearly' => $periodStart->copy()->endOfYear(),
                default => $periodStart->copy()->endOfMonth(),
            };
        }

        $overview = $this->getCostOverview($tenant, $budget->period);
        
        // Apply filters
        $spent = $overview['total_cost'];
        
        // Would filter by provider, service, region, tags
        return $spent;
    }

    /**
     * Generate cost report.
     */
    public function generateCostReport(Tenant $tenant, string $period = 'month', array $options = []): array
    {
        $overview = $this->getCostOverview($tenant, $period);
        $anomalies = $this->detectAnomalies($tenant, $period);
        $recommendations = $this->getAllRecommendations($tenant);
        $budgets = $this->checkBudgets($tenant);

        $report = [
            'tenant_id' => $tenant->id,
            'period' => $period,
            'generated_at' => now()->toISOString(),
            'overview' => $overview,
            'anomalies' => $anomalies,
            'recommendations' => $recommendations,
            'budgets' => $budgets,
            'summary' => [
                'total_cost' => $overview['total_cost'],
                'anomalies_count' => $anomalies['anomalies_found'] ?? 0,
                'potential_monthly_savings' => $recommendations['total_monthly_savings'] ?? 0,
                'budget_alerts' => $budgets['alerts_triggered'] ?? 0,
            ],
        ];

        // Store report
        $reportId = Str::uuid()->toString();
        DB::table('cost_reports')->insert([
            'id' => $reportId,
            'tenant_id' => $tenant->id,
            'period' => $period,
            'data' => json_encode($report),
            'format' => $options['format'] ?? 'json',
            'created_at' => now(),
        ]);

        return array_merge($report, ['report_id' => $reportId]);
    }

    /**
     * Get cost allocation by team/project.
     */
    public function getCostAllocation(Tenant $tenant, string $period = 'month', string $dimension = 'tag'): array
    {
        // Would use cost allocation tags
        return [
            'dimension' => $dimension,
            'period' => $period,
            'allocation' => [],
            'unallocated' => 0,
        ];
    }

    /**
     * Get idle resources.
     */
    public function getIdleResources(Tenant $tenant): array
    {
        if (!$this->config['analysis']['enabled']) {
            return ['enabled' => false];
        }

        $thresholdDays = $this->config['analysis']['idle_threshold_days'];
        
        // Would check CloudWatch/Monitoring for idle resources
        return [
            'threshold_days' => $thresholdDays,
            'idle_resources' => [],
            'potential_monthly_savings' => 0,
        ];
    }
}
