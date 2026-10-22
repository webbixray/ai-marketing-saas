<?php

namespace App\Services\Intelligence\Analytics;

use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class Forecasting
{
    protected Client $client;
    protected array $config;

    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = array_merge([
            'forecast_horizon_days' => 30,
            'seasonality_period_days' => 7, // Weekly seasonality
            'yearly_seasonality' => true,
            'weekly_seasonality' => true,
            'daily_seasonality' => false,
            'changepoint_prior_scale' => 0.05,
            'seasonality_prior_scale' => 10.0,
            'interval_width' => 0.8, // 80% prediction interval
            'min_data_points' => 30,
        ], $config);
    }

    /**
     * Generate forecast for a campaign or all campaigns.
     */
    public function forecast(?int $campaignId = null, int $horizonDays = null): array
    {
        $horizon = $horizonDays ?? $this->config['forecast_horizon_days'];

        if ($campaignId) {
            return $this->forecastCampaign($campaignId, $horizon);
        }

        return $this->forecastAllCampaigns($horizon);
    }

    /**
     * Forecast a single campaign's metrics.
     */
    public function forecastCampaign(int $campaignId, int $horizonDays): array
    {
        $campaign = Campaign::where('id', $campaignId)
            ->where('tenant_id', $this->client->tenant_id)
            ->firstOrFail();

        // Get historical data
        $history = $this->getHistoricalData($campaignId);
        
        if (count($history) < $this->config['min_data_points']) {
            return [
                'error' => 'Insufficient historical data',
                'required' => $this->config['min_data_points'],
                'available' => count($history),
            ];
        }

        // Prepare time series
        $timeSeries = $this->prepareTimeSeries($history);
        
        // Generate forecasts for each metric
        $forecasts = [];
        foreach (['spend', 'impressions', 'clicks', 'conversions', 'revenue', 'roas', 'cpa'] as $metric) {
            $metricHistory = array_column($timeSeries, $metric, 'ds');
            if (array_filter($metricHistory)) {
                $forecasts[$metric] = $this->prophetForecast($metricHistory, $horizonDays);
            }
        }

        // Calculate derived metrics
        $forecasts['cpa'] = $this->calculateCPA($forecasts['spend'] ?? [], $forecasts['conversions'] ?? []);
        $forecasts['roas'] = $this->calculateROAS($forecasts['revenue'] ?? [], $forecasts['spend'] ?? []);

        return [
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign->name,
            'forecast_horizon_days' => $horizonDays,
            'historical_points' => count($history),
            'forecasts' => $forecasts,
            'model_info' => [
                'type' => 'prophet_like',
                'seasonality' => [
                    'yearly' => $this->config['yearly_seasonality'],
                    'weekly' => $this->config['weekly_seasonality'],
                    'daily' => $this->config['daily_seasonality'],
                ],
                'interval_width' => $this->config['interval_width'],
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Forecast all active campaigns.
     */
    public function forecastAllCampaigns(int $horizonDays): array
    {
        $campaigns = Campaign::where('tenant_id', $this->client->tenant_id)
            ->where('status', 'active')
            ->get();

        $allForecasts = [];
        $aggregate = ['spend' => [], 'revenue' => [], 'conversions' => [], 'roas' => []];

        foreach ($campaigns as $campaign) {
            $forecast = $this->forecastCampaign($campaign->id, $horizonDays);
            
            if (isset($forecast['error'])) {
                continue;
            }

            $allForecasts[$campaign->id] = $forecast;

            // Aggregate
            foreach ($forecast['forecasts'] as $metric => $forecast) {
                if (isset($aggregate[$metric])) {
                    foreach ($forecast as $point) {
                        $aggregate[$metric][] = $point['yhat'] ?? 0;
                    }
                }
            }
        }

        // Sum aggregate forecasts
        $totalForecasts = [];
        foreach ($aggregate as $metric => $values) {
            $totalForecasts[$metric] = $this->sumArrayColumns($values);
        }

        // Calculate aggregate derived metrics
        $totalForecasts['cpa'] = $this->calculateCPA($totalForecasts['spend'] ?? [], $totalForecasts['conversions'] ?? []);
        $totalForecasts['roas'] = $this->calculateROAS($totalForecasts['revenue'] ?? [], $totalForecasts['spend'] ?? []);

        return [
            'client_id' => $this->client->id,
            'forecast_horizon_days' => $horizonDays,
            'campaigns_forecasted' => count($allForecasts),
            'individual' => $allForecasts,
            'aggregate' => $totalForecasts,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get historical daily data for a campaign.
     */
    protected function getHistoricalData(int $campaignId): Collection
    {
        return DB::table('campaign_performance_daily')
            ->where('campaign_id', $campaignId)
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Prepare time series data for Prophet-like modeling.
     */
    protected function prepareTimeSeries(Collection $history): array
    {
        $timeSeries = [];
        
        foreach ($history as $point) {
            $timeSeries[] = [
                'ds' => $point->date->format('Y-m-d'),
                'spend' => $point->spend ?? 0,
                'impressions' => $point->impressions ?? 0,
                'clicks' => $point->clicks ?? 0,
                'conversions' => $point->conversions ?? 0,
                'revenue' => $point->conversion_value ?? 0,
                'roas' => ($point->spend ?? 0) > 0 ? (($point->conversion_value ?? 0) / ($point->spend ?? 0)) : 0,
                'cpa' => ($point->conversions ?? 0) > 0 ? (($point->spend ?? 0) / ($point->conversions ?? 0)) : 0,
            ];
        }

        return $timeSeries;
    }

    /**
     * Prophet-like forecasting implementation.
     */
    protected function prophetForecast(array $history, int $horizon): array
    {
        $values = array_filter($history, fn($v) => $v !== null && $v !== '');
        
        if (count($values) < 10) {
            return [];
        }

        // Convert to indexed array
        $y = array_values($values);
        $n = count($y);

        // Simple trend detection using linear regression
        $trend = $this->fitTrend($y);
        
        // Seasonal decomposition
        $seasonal = $this->extractSeasonality($y, $this->config['seasonality_period_days']);
        
        // Generate forecast
        $forecast = [];
        $lastDate = Carbon::now()->startOfDay();

        for ($h = 1; $h <= $horizon; $h++) {
            $date = $lastDate->copy()->addDays($h);
            $t = count($y) + $h;
            
            // Trend component
            $trendValue = $trend['intercept'] + $trend['slope'] * $t;
            
            // Seasonal component
            $seasonalIndex = ($t - 1) % $this->config['seasonality_period_days'];
            $seasonalValue = $seasonal[$seasonalIndex] ?? 0;
            
            // Combined forecast
            $yhat = max(0, $trendValue + $seasonalValue);
            
            // Prediction interval (simplified)
            $residualStd = $this->calculateResidualStd($y, $trend, $seasonal);
            $intervalWidth = $this->config['interval_width'];
            $zScore = $this->getZScore((1 + $intervalWidth) / 2);
            $margin = $zScore * $residualStd;

            $forecast[] = [
                'ds' => $date->format('Y-m-d'),
                'yhat' => round($yhat, 4),
                'yhat_lower' => round(max(0, $yhat - $margin), 4),
                'yhat_upper' => round($yhat + $margin, 4),
            ];
        }

        return $forecast;
    }

    /**
     * Fit linear trend using least squares.
     */
    protected function fitTrend(array $y): array
    {
        $n = count($y);
        $x = range(1, $n);
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < count($y); $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * array_sum($y)) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = (array_sum($y) - $slope * $sumX) / $n;

        return ['slope' => $slope, 'intercept' => $intercept];
    }

    /**
     * Extract seasonal component using moving average.
     */
    protected function extractSeasonality(array $y, int $period): array
    {
        $n = count($y);
        if ($n < $period * 2) {
            return array_fill(0, $period, 0);
        }

        // Calculate moving average
        $ma = [];
        $halfPeriod = floor($period / 2);
        
        for ($i = $halfPeriod; $i < $n - $halfPeriod; $i++) {
            $sum = 0;
            for ($j = -$halfPeriod; $j <= $halfPeriod; $j++) {
                $sum += $y[$i + $j];
            }
            $ma[$i] = $sum / $period;
        }

        // Calculate seasonal indices
        $seasonalIndices = [];
        for ($i = $halfPeriod; $i < $n - $halfPeriod; $i++) {
            if ($ma[$i] > 0) {
                $seasonalIndices[($i - $halfPeriod) % $period][] = $y[$i] / $ma[$i];
            }
        }

        // Average seasonal indices
        $seasonal = array_fill(0, $period, 1);
        for ($i = 0; $i < $period; $i++) {
            if (!empty($seasonalIndices[$i])) {
                $seasonal[$i] = array_sum($seasonalIndices[$i]) / count($seasonalIndices[$i]);
            }
        }

        // Normalize to average 1
        $avg = array_sum($seasonal) / $period;
        for ($i = 0; $i < $period; $i++) {
            $seasonal[$i] = $seasonal[$i] / $avg;
        }

        return $seasonal;
    }

    /**
     * Calculate residual standard deviation.
     */
    protected function calculateResidualStd(array $y, array $trend, array $seasonal): float
    {
        $period = $this->config['seasonality_period_days'];
        $residuals = [];

        foreach ($y as $i => $actual) {
            $t = $i + 1;
            $trendValue = $trend['intercept'] + $trend['slope'] * $t;
            $seasonalIndex = $i % $period;
            $seasonalValue = $seasonal[$seasonalIndex] ?? 1;
            $predicted = $trendValue * $seasonalValue;
            
            if ($predicted > 0) {
                $residuals[] = ($actual - $predicted) / $predicted;
            }
        }

        if (empty($residuals)) return 0.1;

        $mean = array_sum($residuals) / count($residuals);
        $variance = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $residuals)) / count($residuals);
        
        return sqrt($variance);
    }

    /**
     * Calculate CPA forecast from spend and conversions.
     */
    protected function calculateCPA(array $spendForecast, array $conversionsForecast): array
    {
        $cpa = [];
        $count = min(count($spendForecast), count($conversionsForecast));

        for ($i = 0; $i < $count; $i++) {
            $spend = $spendForecast[$i]['yhat'] ?? 0;
            $conversions = $conversionsForecast[$i]['yhat'] ?? 0;
            
            $cpa[] = [
                'ds' => $spendForecast[$i]['ds'] ?? '',
                'yhat' => $conversions > 0 ? round($spend / $conversions, 2) : 0,
                'yhat_lower' => 0,
                'yhat_upper' => 0,
            ];
        }

        return $cpa;
    }

    /**
     * Calculate ROAS forecast from revenue and spend.
     */
    protected function calculateROAS(array $revenueForecast, array $spendForecast): array
    {
        $roas = [];
        $count = min(count($revenueForecast), count($spendForecast));

        for ($i = 0; $i < $count; $i++) {
            $revenue = $revenueForecast[$i]['yhat'] ?? 0;
            $spend = $spendForecast[$i]['yhat'] ?? 0;
            
            $roas[] = [
                'ds' => $revenueForecast[$i]['ds'] ?? '',
                'yhat' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                'yhat_lower' => 0,
                'yhat_upper' => 0,
            ];
        }

        return $roas;
    }

    /**
     * Sum forecast columns across campaigns.
     */
    protected function sumArrayColumns(array $arrays): array
    {
        if (empty($arrays)) return [];

        $count = count($arrays[0]);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $sum = 0;
            $date = $arrays[0][$i]['ds'] ?? '';
            $lowerSum = 0;
            $upperSum = 0;

            foreach ($arrays as $arr) {
                $sum += $arr[$i]['yhat'] ?? 0;
                $lowerSum += $arr[$i]['yhat_lower'] ?? 0;
                $upperSum += $arr[$i]['yhat_upper'] ?? 0;
            }

            $result[] = [
                'ds' => $date,
                'yhat' => round($sum, 4),
                'yhat_lower' => round($lowerSum, 4),
                'yhat_upper' => round($upperSum, 4),
            ];
        }

        return $result;
    }

    /**
     * Detect changepoints in time series.
     */
    public function detectChangepoints(int $campaignId): array
    {
        $history = $this->getHistoricalData($campaignId);
        if (count($history) < 20) {
            return ['error' => 'Insufficient data'];
        }

        $metrics = ['spend', 'conversions', 'roas', 'cpa'];
        $changepoints = [];

        foreach ($metrics as $metric) {
            $values = $history->pluck($metric)->filter()->values()->toArray();
            if (count($values) < 10) continue;

            $points = $this->detectChangepointsInSeries($values);
            
            foreach ($points as $point) {
                $changepoints[] = [
                    'metric' => $metric,
                    'date' => $history[$point]['date']->format('Y-m-d'),
                    'index' => $point,
                    'change_magnitude' => $this->calculateChangeMagnitude($values, $point),
                    'direction' => $this->getChangeDirection($values, $point),
                ];
            }
        }

        return [
            'campaign_id' => $campaignId,
            'changepoints' => $changepoints,
            'detected_at' => now()->toISOString(),
        ];
    }

    /**
     * Detect changepoints using PELT algorithm (simplified).
     */
    protected function detectChangepointsInSeries(array $values): array
    {
        $n = count($values);
        if ($n < 10) return [];

        $penalty = 3 * log($n); // BIC penalty
        $changepoints = [];

        // Simple mean shift detection
        for ($i = 5; $i < $n - 5; $i++) {
            $leftMean = array_sum(array_slice($values, max(0, $i - 5), 5)) / 5;
            $rightMean = array_sum(array_slice($values, $i, 5)) / 5;
            
            $change = abs($rightMean - $leftMean) / max(1, abs($leftMean));
            
            if ($change > 0.3) { // 30% change threshold
                $changepoints[] = $i;
            }
        }

        // Merge nearby changepoints
        $merged = [];
        foreach ($changepoints as $cp) {
            if (empty($merged) || $cp - end($merged) > 5) {
                $merged[] = $cp;
            }
        }

        return $merged;
    }

    protected function calculateChangeMagnitude(array $values, int $point): float
    {
        $left = array_slice($values, max(0, $point - 5), 5);
        $right = array_slice($values, $point, 5);
        
        $leftMean = array_sum($left) / count($left);
        $rightMean = array_sum($right) / count($right);
        
        return $leftMean > 0 ? abs($rightMean - $leftMean) / $leftMean : 0;
    }

    protected function getChangeDirection(array $values, int $point): string
    {
        $left = array_slice($values, max(0, $point - 5), 5);
        $right = array_slice($values, $point, 5);
        
        $leftMean = array_sum($left) / count($left);
        $rightMean = array_sum($right) / count($right);
        
        return $rightMean > $leftMean ? 'increase' : 'decrease';
    }
}
