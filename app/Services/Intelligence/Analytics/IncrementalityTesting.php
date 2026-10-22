<?php

namespace App\Services\Intelligence\Analytics;

use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class IncrementalityTesting
{
    protected Client $client;
    protected array $config;

    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = array_merge([
            'min_sample_size' => 100,
            'confidence_level' => 0.95,
            'minimum_detectable_effect' => 0.05, // 5% relative lift
            'test_duration_days' => 14,
        ], $config);
    }

    /**
     * Create a new incrementality test (Geo-based or User-based).
     */
    public function createTest(array $params): array
    {
        $test = DB::table('incrementality_tests')->insertGetId([
            'client_id' => $this->client->id,
            'name' => $params['name'],
            'description' => $params['description'] ?? '',
            'test_type' => $params['test_type'] ?? 'geo', // geo, user, time
            'treatment_campaigns' => json_encode($params['treatment_campaigns'] ?? []),
            'control_campaigns' => json_encode($params['control_campaigns'] ?? []),
            'geo_regions_treatment' => json_encode($params['geo_regions_treatment'] ?? []),
            'geo_regions_control' => json_encode($params['geo_regions_control'] ?? []),
            'user_split_ratio' => $params['user_split_ratio'] ?? 0.5,
            'start_date' => $params['start_date'] ?? now(),
            'end_date' => $params['end_date'] ?? now()->addDays($this->config['test_duration_days']),
            'primary_metric' => $params['primary_metric'] ?? 'conversions', // conversions, revenue, roas
            'status' => 'draft',
            'config' => json_encode([
                'min_sample_size' => $this->config['min_sample_size'],
                'confidence_level' => $this->config['confidence_level'],
                'minimum_detectable_effect' => $this->config['minimum_detectable_effect'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getTest($test);
    }

    /**
     * Start a test.
     */
    public function startTest(int $testId): bool
    {
        $test = $this->getTest($testId);
        if (!$test) return false;

        // Validate test configuration
        $validation = $this->validateTestConfig($test);
        if (!$validation['valid']) {
            return false;
        }

        // Create experiment assignments
        $this->createExperimentAssignments($test);

        DB::table('incrementality_tests')
            ->where('id', $testId)
            ->update([
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        return true;
    }

    /**
     * Validate test configuration before starting.
     */
    protected function validateTestConfig(object $test): array
    {
        $treatment = json_decode($test->treatment_campaigns, true);
        $control = json_decode($test->control_campaigns, true);

        $errors = [];

        if (empty($treatment)) {
            $errors[] = 'At least one treatment campaign required';
        }

        if (empty($control) && $test->test_type !== 'time') {
            $errors[] = 'At least one control campaign required for geo/user tests';
        }

        // Check for overlapping campaigns
        $overlap = array_intersect($treatment, $control);
        if (!empty($overlap)) {
            $errors[] = 'Campaigns cannot be in both treatment and control groups';
        }

        // Check minimum sample size
        if ($test->test_type === 'geo') {
            $geoTreatment = json_decode($test->geo_regions_treatment, true);
            $geoControl = json_decode($test->geo_regions_control, true);
            
            if (empty($geoTreatment) || empty($geoControl)) {
                $errors[] = 'Geo regions required for geo-based tests';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Create experiment assignments for campaigns/regions.
     */
    protected function createExperimentAssignments(object $test): void
    {
        $assignments = [];

        $treatment = json_decode($test->treatment_campaigns, true);
        $control = json_decode($test->control_campaigns, true);

        if ($test->test_type === 'geo') {
            $geoTreatment = json_decode($test->geo_regions_treatment, true);
            $geoControl = json_decode($test->geo_regions_control, true);

            foreach ($geoTreatment as $region) {
                $assignments[] = [
                    'test_id' => $test->id,
                    'assignment_type' => 'geo',
                    'assignment_value' => $region,
                    'group' => 'treatment',
                    'campaign_ids' => json_encode($treatment),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($geoControl as $region) {
                $assignments[] = [
                    'test_id' => $test->id,
                    'assignment_type' => 'geo',
                    'assignment_value' => $region,
                    'group' => 'control',
                    'campaign_ids' => json_encode($control),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        } elseif ($test->test_type === 'user') {
            // User-based split - handled at ad platform level
            $assignments[] = [
                'test_id' => $test->id,
                'assignment_type' => 'user',
                'assignment_value' => 'split_' . $test->user_split_ratio,
                'group' => 'treatment',
                'campaign_ids' => json_encode($treatment),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $assignments[] = [
                'test_id' => $test->id,
                'assignment_type' => 'user',
                'assignment_value' => 'split_' . (1 - $test->user_split_ratio),
                'group' => 'control',
                'campaign_ids' => json_encode($control),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        } elseif ($test->test_type === 'time') {
            // Time-based (before/after)
            $assignments[] = [
                'test_id' => $test->id,
                'assignment_type' => 'time',
                'assignment_value' => 'pre_period',
                'group' => 'control',
                'campaign_ids' => json_encode(array_merge($treatment, $control)),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $assignments[] = [
                'test_id' => $test->id,
                'assignment_type' => 'time',
                'assignment_value' => 'post_period',
                'group' => 'treatment',
                'campaign_ids' => json_encode($treatment),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($assignments)) {
            DB::table('incrementality_assignments')->insert($assignments);
        }
    }

    /**
     * Get test by ID.
     */
    public function getTest(int $testId): ?object
    {
        return DB::table('incrementality_tests')
            ->where('id', $testId)
            ->where('client_id', $this->client->id)
            ->first();
    }

    /**
     * Get all tests for client.
     */
    public function getTests(): Collection
    {
        return DB::table('incrementality_tests')
            ->where('client_id', $this->client->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Analyze test results (call periodically during test).
     */
    public function analyzeTest(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['error' => 'Test not found'];
        }

        $assignments = DB::table('incrementality_assignments')
            ->where('test_id', $testId)
            ->get();

        if ($assignments->isEmpty()) {
            return ['error' => 'No assignments found'];
        }

        $results = $this->calculateResults($test, $assignments);

        // Update test with latest results
        DB::table('incrementality_tests')
            ->where('id', $testId)
            ->update([
                'results' => json_encode($results),
                'last_analyzed_at' => now(),
                'updated_at' => now(),
            ]);

        // Check if test should be concluded
        $conclusion = $this->checkConclusion($test, $results);
        if ($conclusion['should_conclude']) {
            $this->concludeTest($test->id, $conclusion);
        }

        return $results;
    }

    /**
     * Calculate test results using difference-in-differences or t-test.
     */
    protected function calculateResults(object $test, Collection $assignments): array
    {
        $treatmentCampaigns = json_decode($test->treatment_campaigns, true);
        $controlCampaigns = json_decode($test->control_campaigns, true);

        // Get metrics for treatment group
        $treatmentMetrics = $this->getCampaignMetrics($treatmentCampaigns, $test->start_date, $test->end_date ?? now());
        $controlMetrics = $this->getCampaignMetrics($controlCampaigns, $test->start_date, $test->end_date ?? now());

        // Calculate lift
        $primaryMetric = $test->primary_metric;

        $treatmentValue = $treatmentMetrics[$primaryMetric] ?? 0;
        $controlValue = $controlMetrics[$primaryMetric] ?? 0;

        $absoluteLift = $treatmentValue - $controlValue;
        $relativeLift = $controlValue > 0 ? $absoluteLift / $controlValue : 0;

        // Statistical significance (t-test)
        $significance = $this->calculateSignificance($treatmentCampaigns, $controlCampaigns, $primaryMetric);

        return [
            'test_id' => $test->id,
            'metric' => $primaryMetric,
            'treatment' => [
                'campaigns' => $treatmentCampaigns,
                'value' => round($treatmentValue, 4),
                'campaigns_count' => count($treatmentCampaigns),
            ],
            'control' => [
                'campaigns' => $controlCampaigns,
                'value' => round($controlValue, 4),
                'campaigns_count' => count($controlCampaigns),
            ],
            'absolute_lift' => round($absoluteLift, 4),
            'relative_lift' => round($relativeLift * 100, 2),
            'significance' => $significance,
            'recommendation' => $this->getRecommendation($relativeLift, $significance),
            'analyzed_at' => now()->toISOString(),
        ];
    }

    /**
     * Get campaign metrics for a period.
     */
    protected function getCampaignMetrics(array $campaignIds, Carbon $startDate, Carbon $endDate): array
    {
        if (empty($campaignIds)) {
            return ['conversions' => 0, 'revenue' => 0, 'roas' => 0, 'spend' => 0];
        }

        $metrics = DB::table('campaigns')
            ->whereIn('id', $campaignIds)
            ->where('tenant_id', $this->client->tenant_id)
            ->selectRaw('
                SUM(conversions) as conversions,
                SUM(spent) as spend,
                SUM(conversion_value) as revenue
            ')
            ->first();

        $conversions = $metrics->conversions ?? 0;
        $spend = $metrics->spend ?? 0;
        $revenue = $metrics->revenue ?? 0;

        return [
            'conversions' => $conversions,
            'spend' => $spend,
            'revenue' => $revenue,
            'roas' => $spend > 0 ? $revenue / $spend : 0,
            'cpa' => $conversions > 0 ? $spend / $conversions : 0,
        ];
    }

    /**
     * Calculate statistical significance using t-test.
     */
    protected function calculateSignificance(array $treatmentIds, array $controlIds, string $metric): array
    {
        // Get daily metrics for each campaign
        $treatmentData = $this->getDailyMetrics($treatmentIds, $metric);
        $controlData = $this->getDailyMetrics($controlIds, $metric);

        if (count($treatmentData) < 2 || count($controlData) < 2) {
            return ['significant' => false, 'p_value' => 1.0, 'message' => 'Insufficient data'];
        }

        // Two-sample t-test
        $tStat = $this->tTest($treatmentData, $controlData);
        $df = count($treatmentData) + count($controlData) - 2;
        $pValue = $this->calculatePValue($tStat, $df);

        return [
            'significant' => $pValue < (1 - 0.95), // 95% confidence
            'p_value' => round($pValue, 6),
            't_statistic' => round($tStat, 4),
            'degrees_of_freedom' => $df,
            'confidence_level' => 0.95,
        ];
    }

    /**
     * Get daily metric values for campaigns.
     */
    protected function getDailyMetrics(array $campaignIds, string $metric): array
    {
        if (empty($campaignIds)) return [];

        // This would query daily performance data
        // For now, return simulated data
        return [];
    }

    /**
     * Perform two-sample t-test.
     */
    protected function tTest(array $sample1, array $sample2): float
    {
        $n1 = count($sample1);
        $n2 = count($sample2);

        if ($n1 < 2 || $n2 < 2) return 0;

        $mean1 = array_sum($sample1) / $n1;
        $mean2 = array_sum($sample2) / $n2;

        $var1 = array_sum(array_map(fn($x) => ($x - $mean1) ** 2, $sample1)) / ($n1 - 1);
        $var2 = array_sum(array_map(fn($x) => ($x - $mean2) ** 2, $sample2)) / ($n2 - 1);

        $pooledVar = (($n1 - 1) * $var1 + ($n2 - 1) * $var2) / ($n1 + $n2 - 2);
        $se = sqrt($pooledVar * (1/$n1 + 1/$n2));

        return $se > 0 ? ($mean1 - $mean2) / $se : 0;
    }

    /**
     * Calculate p-value from t-statistic.
     */
    protected function calculatePValue(float $tStat, int $df): float
    {
        // Approximation for two-tailed t-test
        $t = abs($tStat);
        $x = $df / ($t * $t + $df);
        
        // Incomplete beta function approximation
        $p = $this->betaIncomplete($x, $df / 2, 0.5);
        
        return 2 * min($p, 1 - $p); // Two-tailed
    }

    /**
     * Incomplete beta function approximation.
     */
    protected function betaIncomplete(float $x, float $a, float $b): float
    {
        // Simplified approximation
        if ($x <= 0) return 0;
        if ($x >= 1) return 1;
        
        $bt = exp($this->logBeta($a, $b) + $a * log($x) + $b * log(1 - $x));
        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $bt * $this->betaContinuedFraction($x, $a, $b) / $a;
        }
        return 1 - $bt * $this->betaContinuedFraction(1 - $x, $b, $a) / $b;
    }

    protected function betaContinuedFraction(float $x, float $a, float $b): float
    {
        // Simplified continued fraction
        $m = 100;
        $eps = 3e-7;
        $fpmin = 1e-30;
        
        $c = 1;
        $d = 1 - (($a + $b) * $x) / ($a + 1);
        if (abs($d) < $fpmin) $d = $fpmin;
        $d = 1 / $d;
        $h = $d;
        
        for ($m = 1; $m <= 100; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($a + $m2 - 1) * ($a + $m2));
            $d = 1 + $aa * $d;
            if (abs($d) < $fpmin) $d = $fpmin;
            $c = 1 + $aa / $c;
            if (abs($c) < $fpmin) $c = $fpmin;
            $d = 1 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1) < $eps) break;
        }
        
        return $h;
    }

    protected function logBeta(float $a, float $b): float
    {
        return log($this->gamma($a)) + log($this->gamma($b)) - log($this->gamma($a + $b));
    }

    protected function gamma(float $z): float
    {
        // Lanczos approximation
        $g = 7;
        $p = [
            0.99999999999980993,
            676.5203681218851,
            -1259.1392167224028,
            771.32342877765313,
            -176.61502916214059,
            12.507343278686905,
            -0.13857109526572012,
            9.9843695780195716e-6,
            1.5056327351493116e-7,
        ];
        
        if ($z < 0.5) {
            return M_PI / (sin(M_PI * $z) * $this->gamma(1 - $z));
        }
        
        $z -= 1;
        $x = $p[0];
        for ($i = 1; $i < 9; $i++) {
            $x += $p[$i] / ($z + $i);
        }
        
        $t = $z + 7.5;
        return sqrt(2 * M_PI) * pow($t, $z + 0.5) * exp(-$t) * $x;
    }

    /**
     * Check if test should be concluded.
     */
    protected function checkConclusion(object $test, array $results): array
    {
        $significance = $results['significance'] ?? ['significant' => false];
        $relativeLift = $results['relative_lift'] ?? 0;
        $minEffect = $this->config['minimum_detectable_effect'] * 100;

        // Conclude if:
        // 1. Test duration exceeded
        $durationExceeded = now()->gt(Carbon::parse($test->end_date ?? now()->addDays($this->config['test_duration_days'])));
        
        // 2. Statistically significant with meaningful lift
        $conclusive = $significance['significant'] && abs($relativeLift) >= $minEffect;
        
        // 3. Futility - very unlikely to reach significance
        $futility = false; // Would need power analysis

        return [
            'should_conclude' => $durationExceeded || $conclusive,
            'reason' => $durationExceeded ? 'duration_exceeded' : ($conclusive ? 'significant_result' : 'ongoing'),
            'winner' => $relativeLift > 0 ? 'treatment' : 'control',
        ];
    }

    /**
     * Get recommendation based on results.
     */
    protected function getRecommendation(float $relativeLift, array $significance): string
    {
        if ($significance['significant']) {
            return $relativeLift > 0 
                ? 'Scale treatment: statistically significant positive lift detected'
                : 'Stop treatment: statistically significant negative impact detected';
        }
        
        return 'Continue test: no statistically significant difference detected yet';
    }

    /**
     * Conclude test and save final results.
     */
    protected function concludeTest(int $testId, array $conclusion): void
    {
        DB::table('incrementality_tests')
            ->where('id', $testId)
            ->update([
                'status' => 'concluded',
                'concluded_at' => now(),
                'winner' => $conclusion['winner'],
                'conclusion_reason' => $conclusion['reason'],
                'updated_at' => now(),
            ]);
    }

    /**
     * Get test results with full details.
     */
    public function getTestResults(int $testId): ?array
    {
        $test = $this->getTest($testId);
        if (!$test) return null;

        return [
            'test' => $test,
            'results' => json_decode($test->results ?? '{}', true),
            'assignments' => DB::table('incrementality_assignments')
                ->where('test_id', $testId)
                ->get(),
        ];
    }
}
