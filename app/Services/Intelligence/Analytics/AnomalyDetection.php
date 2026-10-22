<?php

namespace App\Services\Intelligence\Analytics;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\CreativeAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AnomalyDetection
{
    protected Client $client;
    protected array $config;

    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = array_merge([
            'contamination' => 0.05, // Expected anomaly ratio
            'n_estimators' => 100, // Number of trees in isolation forest
            'max_samples' => 'auto', // Subsample size
            'max_features' => 1.0, // Feature subsampling
            'lookback_days' => 30,
            'min_data_points' => 20,
            'anomaly_threshold_percentile' => 95,
        ], $config);
    }

    /**
     * Detect anomalies across all campaigns for a client.
     */
    public function detectAllAnomalies(): array
    {
        $campaigns = Campaign::where('tenant_id', $this->client->tenant_id)
            ->where('status', 'active')
            ->get();

        $allAnomalies = [];

        foreach ($campaigns as $campaign) {
            $anomalies = $this->detectCampaignAnomalies($campaign->id);
            if (!empty($anomalies['anomalies'])) {
                $allAnomalies = array_merge($allAnomalies, $anomalies['anomalies']);
            }
        }

        // Also check creative-level anomalies
        $creativeAnomalies = $this->detectCreativeAnomalies();
        $allAnomalies = array_merge($allAnomalies, $creativeAnomalies);

        // Sort by severity and recency
        usort($allAnomalies, function ($a, $b) {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $sevDiff = ($severityOrder[$b['severity']] ?? 0) - ($severityOrder[$a['severity']] ?? 0);
            if ($sevDiff !== 0) return $sevDiff;
            return strtotime($b['detected_at']) - strtotime($a['detected_at']);
        });

        return [
            'client_id' => $this->client->id,
            'total_anomalies' => count($allAnomalies),
            'by_severity' => $this->countBySeverity($allAnomalies),
            'anomalies' => $allAnomalies,
            'detected_at' => now()->toISOString(),
        ];
    }

    /**
     * Detect anomalies for a specific campaign.
     */
    public function detectCampaignAnomalies(int $campaignId): array
    {
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->client->tenant_id)
            ->firstOrFail();

        $history = $this->getCampaignHistory($campaignId);
        
        if (count($history) < $this->config['min_data_points']) {
            return ['anomalies' => [], 'message' => 'Insufficient data'];
        }

        $anomalies = [];
        $lookback = $this->config['lookback_days'];

        // Prepare feature matrix
        $features = $this->prepareFeatures($history);
        
        // Run Isolation Forest
        $anomalyScores = $this->isolationForest($features);
        
        // Identify anomalies
        $threshold = $this->calculateThreshold($anomalyScores);
        
        foreach ($history as $index => $point) {
            if ($anomalyScores[$index] > $threshold) {
                $anomalies[] = $this->createAnomalyRecord(
                    $campaign,
                    $point,
                    $anomalyScores[$index],
                    'campaign_metric'
                );
            }
        }

        // Also check for specific metric anomalies
        $metricAnomalies = $this->detectMetricAnomalies($history);
        $anomalies = array_merge($anomalies, $metricAnomalies);

        return [
            'campaign_id' => $campaignId,
            'anomalies' => $anomalies,
            'total_checked' => count($history),
            'anomaly_rate' => count($history) > 0 ? count($anomalies) / count($history) : 0,
        ];
    }

    /**
     * Detect anomalies in creative assets.
     */
    public function detectCreativeAnomalies(): array
    {
        $creatives = CreativeAsset::where('tenant_id', $this->client->tenant_id)
            ->where('status', 'active')
            ->get();

        $anomalies = [];

        foreach ($creatives as $creative) {
            // Check fatigue
            $fatigue = $creative->isFatigued();
            if ($fatigue['is_fatigued']) {
                $anomalies[] = [
                    'type' => 'creative_fatigue',
                    'entity_type' => 'creative',
                    'entity_id' => $creative->id,
                    'entity_name' => $creative->name,
                    'severity' => $fatigue['severity'],
                    'message' => $fatigue['message'],
                    'details' => $fatigue,
                    'detected_at' => now()->toISOString(),
                ];
            }

            // Check performance drop
            $perfDrop = $this->checkCreativePerformanceDrop($creative);
            if ($perfDrop) {
                $anomalies[] = $perfDrop;
            }

            // Check for zero impressions (broken creative)
            if ($this->hasZeroImpressions($creative)) {
                $anomalies[] = [
                    'type' => 'creative_delivery_failure',
                    'entity_type' => 'creative',
                    'entity_id' => $creative->id,
                    'entity_name' => $creative->name,
                    'severity' => 'critical',
                    'message' => "Creative \"{$creative->name}\" has zero impressions in last 7 days",
                    'detected_at' => now()->toISOString(),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Prepare feature matrix for Isolation Forest.
     */
    protected function prepareFeatures(Collection $history): array
    {
        $features = [];
        
        foreach ($history as $point) {
            $features[] = [
                'day_of_week' => $point->date->dayOfWeek,
                'day_of_month' => $point->date->day,
                'week_of_year' => $point->date->weekOfYear,
                'spend' => $point->spend ?? 0,
                'impressions' => $point->impressions ?? 0,
                'clicks' => $point->clicks ?? 0,
                'conversions' => $point->conversions ?? 0,
                'ctr' => ($point->impressions ?? 0) > 0 ? (($point->clicks ?? 0) / ($point->impressions ?? 0)) : 0,
                'cvr' => ($point->clicks ?? 0) > 0 ? (($point->conversions ?? 0) / ($point->clicks ?? 0)) : 0,
                'cpc' => ($point->clicks ?? 0) > 0 ? (($point->spend ?? 0) / ($point->clicks ?? 0)) : 0,
                'cpa' => ($point->conversions ?? 0) > 0 ? (($point->spend ?? 0) / ($point->conversions ?? 0)) : 0,
                'roas' => ($point->spend ?? 0) > 0 ? (($point->conversion_value ?? 0) / ($point->spend ?? 0)) : 0,
            ];
        }

        // Normalize features
        return $this->normalizeFeatures($features);
    }

    /**
     * Normalize features to zero mean, unit variance.
     */
    protected function normalizeFeatures(array $features): array
    {
        if (empty($features)) return [];

        $numFeatures = count($features[0]);
        $numSamples = count($features);

        // Calculate mean and std for each feature
        $means = [];
        $stds = [];

        for ($f = 0; $f < $numFeatures; $f++) {
            $values = array_column($features, array_keys($features[0])[$f]);
            $means[$f] = array_sum($values) / $numSamples;
            
            $variance = array_sum(array_map(fn($v) => ($v - $means[$f]) ** 2, $values)) / $numSamples;
            $stds[$f] = sqrt(max($variance, 1e-8));
        }

        // Normalize
        $normalized = [];
        foreach ($features as $sample) {
            $normalizedSample = [];
            $keys = array_keys($sample);
            foreach ($keys as $f => $key) {
                $normalizedSample[$key] = ($sample[$key] - $means[$f]) / $stds[$f];
            }
            $normalized[] = $normalizedSample;
        }

        return $normalized;
    }

    /**
     * Simplified Isolation Forest implementation.
     */
    protected function isolationForest(array $features): array
    {
        $nSamples = count($features);
        if ($nSamples < 10) {
            return array_fill(0, $nSamples, 0.5);
        }

        $nEstimators = min($this->config['n_estimators'], 100);
        $maxSamples = $this->config['max_samples'] === 'auto' 
            ? min(256, $nSamples) 
            : min($this->config['max_samples'], $nSamples);

        $anomalyScores = array_fill(0, $nSamples, 0);

        for ($t = 0; $t < $nEstimators; $t++) {
            // Bootstrap sample
            $sampleIndices = $this->bootstrapSample($nSamples, $maxSamples);
            $sampleData = array_intersect_key($features, array_flip($sampleIndices));
            
            // Build random tree
            $tree = $this->buildIsolationTree($sampleData, 0, floor(log($maxSamples, 2)));
            
            // Score all points
            for ($i = 0; $i < $nSamples; $i++) {
                $pathLength = $this->traverseTree($tree, $features[$i], 0);
                // Normalize path length
                $c = $this->averagePathLength($maxSamples);
                $score = pow(2, -$pathLength / $c);
                $anomalyScores[$i] += $score;
            }
        }

        // Average across trees
        return array_map(fn($s) => $s / $nEstimators, $anomalyScores);
    }

    /**
     * Build a single isolation tree.
     */
    protected function buildIsolationTree(array $data, int $depth, int $maxDepth): array
    {
        if (count($data) <= 1 || $depth >= $maxDepth) {
            return ['type' => 'leaf', 'size' => count($data)];
        }

        // Random feature
        $features = array_keys($data[0]);
        $feature = $features[array_rand($features)];

        // Random split value
        $values = array_column($data, $feature);
        $min = min($values);
        $max = max($values);
        
        if ($min === $max) {
            return ['type' => 'leaf', 'size' => count($data)];
        }

        $split = $min + mt_rand() / mt_getrandmax() * ($max - $min);

        // Split data
        $left = [];
        $right = [];
        foreach ($data as $row) {
            if ($row[$feature] < $split) {
                $left[] = $row;
            } else {
                $right[] = $row;
            }
        }

        return [
            'type' => 'node',
            'feature' => $feature,
            'split' => $split,
            'left' => $this->buildIsolationTree($left, $depth + 1, $maxDepth),
            'right' => $this->buildIsolationTree($right, $depth + 1, $maxDepth),
        ];
    }

    /**
     * Traverse isolation tree to get path length.
     */
    protected function traverseTree(array $tree, array $point, int $depth): int
    {
        if ($tree['type'] === 'leaf') {
            return $depth + $this->averagePathLength($tree['size']);
        }

        if ($point[$tree['feature']] < $tree['split']) {
            return $this->traverseTree($tree['left'], $point, $depth + 1);
        } else {
            return $this->traverseTree($tree['right'], $point, $depth + 1);
        }
    }

    /**
     * Average path length for unsuccessful search in BST.
     */
    protected function averagePathLength(int $n): float
    {
        if ($n <= 1) return 0;
        return 2 * (log($n - 1) + 0.5772156649) - 2 * ($n - 1) / $n;
    }

    /**
     * Bootstrap sample indices.
     */
    protected function bootstrapSample(int $n, int $sampleSize): array
    {
        $indices = [];
        for ($i = 0; $i < $sampleSize; $i++) {
            $indices[] = mt_rand(0, $n - 1);
        }
        return $indices;
    }

    /**
     * Calculate anomaly threshold from scores.
     */
    protected function calculateThreshold(array $scores): float
    {
        sort($scores);
        $index = floor(count($scores) * (1 - $this->config['contamination']));
        return $scores[max(0, $index - 1)] ?? 0.5;
    }

    /**
     * Create anomaly record.
     */
    protected function createAnomalyRecord(Campaign $campaign, object $point, float $score, string $type): array
    {
        $severity = $this->calculateSeverity($score);
        
        return [
            'type' => $type,
            'entity_type' => 'campaign',
            'entity_id' => $campaign->id,
            'entity_name' => $campaign->name,
            'severity' => $severity,
            'score' => round($score, 4),
            'message' => $this->generateAnomalyMessage($point, $type),
            'details' => [
                'date' => $point->date->format('Y-m-d'),
                'metrics' => [
                    'spend' => $point->spend ?? 0,
                    'impressions' => $point->impressions ?? 0,
                    'clicks' => $point->clicks ?? 0,
                    'conversions' => $point->conversions ?? 0,
                    'ctr' => $point->ctr ?? 0,
                    'cpa' => $point->cpa ?? 0,
                    'roas' => $point->roas ?? 0,
                ],
            ],
            'detected_at' => now()->toISOString(),
        ];
    }

    /**
     * Detect specific metric anomalies using statistical thresholds.
     */
    protected function detectMetricAnomalies(Collection $history): array
    {
        $anomalies = [];
        $metrics = ['spend', 'impressions', 'clicks', 'conversions', 'ctr', 'cpa', 'roas', 'cpc'];

        foreach ($metrics as $metric) {
            $values = $history->pluck($metric)->filter()->values()->toArray();
            if (count($values) < 10) continue;

            $mean = array_sum($values) / count($values);
            $std = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values));
            
            if ($std == 0) continue;

            // Check last 3 days for anomalies
            $recent = array_slice($values, -3);
            foreach ($recent as $index => $value) {
                $zScore = abs($value - $mean) / $std;
                
                if ($zScore > 3) { // 3 sigma rule
                    $anomalies[] = [
                        'type' => 'metric_anomaly',
                        'entity_type' => 'campaign_metric',
                        'metric' => $metric,
                        'severity' => $zScore > 4 ? 'critical' : 'high',
                        'z_score' => round($zScore, 2),
                        'message' => "{$metric} anomaly detected: {$value} (expected ~{$mean} ± {$std})",
                        'details' => [
                            'value' => $value,
                            'expected_mean' => $mean,
                            'std_dev' => $std,
                            'z_score' => $zScore,
                        ],
                        'detected_at' => now()->toISOString(),
                    ];
                }
            }
        }

        return $anomalies;
    }

    /**
     * Check for creative performance drops.
     */
    protected function checkCreativePerformanceDrop(CreativeAsset $creative): ?array
    {
        $data = DB::table('creative_performance_daily')
            ->where('creative_id', $creative->id)
            ->where('date', '>=', now()->subDays(14))
            ->orderBy('date', 'asc')
            ->get();

        if ($data->count() < 7) return null;

        $ctrValues = $data->pluck('ctr')->filter()->values()->toArray();
        if (count($ctrValues) < 7) return null;

        // Compare last 3 days to previous 7
        $recentCtr = array_sum(array_slice($ctrValues, -3)) / 3;
        $previousCtr = array_sum(array_slice($ctrValues, -10, 7)) / 7;

        if ($previousCtr > 0) {
            $decline = ($previousCtr - $recentCtr) / $previousCtr;
            if ($decline > 0.4) { // 40% decline
                return [
                    'type' => 'creative_performance_drop',
                    'entity_type' => 'creative',
                    'entity_id' => $creative->id,
                    'entity_name' => $creative->name,
                    'severity' => $decline > 0.6 ? 'critical' : 'high',
                    'message' => "Creative \"{$creative->name}\" CTR dropped {$decline * 100}% in last 3 days",
                    'details' => [
                        'recent_ctr' => round($recentCtr, 4),
                        'previous_ctr' => round($previousCtr, 4),
                        'decline_pct' => round($decline * 100, 1),
                    ],
                    'detected_at' => now()->toISOString(),
                ];
            }

            return null;
        }

    /**
     * Check for zero impressions.
     */
    protected function hasZeroImpressions(CreativeAsset $creative): bool
    {
        $recent = DB::table('creative_performance_daily')
            ->where('creative_id', $creative->id)
            ->where('date', '>=', now()->subDays(7))
            ->sum('impressions');

        return $recent == 0;
    }

    /**
     * Get campaign history.
     */
    protected function getCampaignHistory(int $campaignId): Collection
    {
        return DB::table('campaign_performance_daily')
            ->where('campaign_id', $campaignId)
            ->where('date', '>=', now()->subDays($this->config['lookback_days']))
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Get campaign history for feature preparation.
     */
    protected function getCampaignHistoryArray(int $campaignId): array
    {
        return $this->getCampaignHistory($campaignId)->toArray();
    }

    /**
     * Calculate severity from anomaly score.
     */
    protected function calculateSeverity(float $score): string
    {
        // Score is 0-1, higher = more anomalous
        if ($score > 0.8) return 'critical';
        if ($score > 0.6) return 'high';
        if ($score > 0.4) return 'medium';
        return 'low';
    }

    /**
     * Generate human-readable anomaly message.
     */
    protected function generateAnomalyMessage(object $point, string $type): string
    {
        $date = $point->date->format('M d');
        
        return match ($type) {
            'campaign_metric' => "Anomalous campaign metrics detected on {$date}",
            'metric_anomaly' => "Metric anomaly detected on {$date}",
            default => "Anomaly detected on {$date}",
        };
    }

    /**
     * Count anomalies by severity.
     */
    protected function countBySeverity(array $anomalies): array
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'low';
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }
}
