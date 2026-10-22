<?php

namespace App\Services\Intelligence\Analytics;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\ConversionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AttributionModeling
{
    protected Client $client;
    protected array $config;

    public function __construct(Client $client, array $config = [])
    {
        $this->client = $client;
        $this->config = array_merge([
            'lookback_window_days' => 30,
            'min_touchpoints' => 1,
            'max_touchpoints' => 50,
            'decay_half_life_days' => 7,
        ], $config);
    }

    /**
     * Calculate attribution for all conversions in a period.
     */
    public function calculateAttribution(string $model = 'data_driven', Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays($this->config['lookback_window_days']);
        $endDate = $endDate ?? now();

        $conversions = $this->getConversions($startDate, $endDate);
        $touchpoints = $this->getTouchpoints($conversions, $startDate, $endDate);

        return match ($model) {
            'last_click' => $this->lastClickAttribution($conversions, $touchpoints),
            'first_click' => $this->firstClickAttribution($conversions, $touchpoints),
            'linear' => $this->linearAttribution($conversions, $touchpoints),
            'time_decay' => $this->timeDecayAttribution($conversions, $touchpoints),
            'position_based' => $this->positionBasedAttribution($conversions, $touchpoints),
            'data_driven' => $this->dataDrivenAttribution($conversions, $touchpoints),
            'markov_chain' => $this->markovChainAttribution($conversions, $touchpoints),
            default => $this->dataDrivenAttribution($conversions, $touchpoints),
        };
    }

    /**
     * Get conversions with their user journeys.
     */
    protected function getConversions(Carbon $start, Carbon $end): Collection
    {
        return ConversionEvent::where('client_id', $this->client->id)
            ->whereBetween('converted_at', [$start, $end])
            ->where('status', 'completed')
            ->with(['touchpoints' => function ($query) use ($start, $end) {
                $query->whereBetween('occurred_at', [$start->copy()->subDays($this->config['lookback_window_days']), $end])
                    ->orderBy('occurred_at');
            }])
            ->get();
    }

    /**
     * Get all touchpoints for conversions.
     */
    protected function getTouchpoints(Collection $conversions, Carbon $start, Carbon $end): Collection
    {
        $conversionIds = $conversions->pluck('id');

        return DB::table('touchpoints')
            ->whereIn('conversion_event_id', $conversionIds)
            ->whereBetween('occurred_at', [$start->copy()->subDays($this->config['lookback_window_days']), $end])
            ->orderBy('conversion_event_id')
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * Last Click Attribution - 100% to last touchpoint.
     */
    protected function lastClickAttribution(Collection $conversions, Collection $touchpoints): array
    {
        $results = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            $lastTouch = $journeyTouchpoints->last();

            if ($lastTouch) {
                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $lastTouch->id,
                    'channel' => $lastTouch->channel,
                    'campaign_id' => $lastTouch->campaign_id,
                    'credit' => 1.0,
                    'model' => 'last_click',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * First Click Attribution - 100% to first touchpoint.
     */
    protected function firstClickAttribution(Collection $conversions, Collection $touchpoints): array
    {
        $results = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            $firstTouch = $journeyTouchpoints->first();

            if ($firstTouch) {
                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $firstTouch->id,
                    'channel' => $firstTouch->channel,
                    'campaign_id' => $firstTouch->campaign_id,
                    'credit' => 1.0,
                    'model' => 'first_click',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Linear Attribution - Equal credit to all touchpoints.
     */
    protected function linearAttribution(Collection $conversions, Collection $touchpoints): array
    {
        $results = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            $count = $journeyTouchpoints->count();

            if ($count > 0) {
                $credit = 1.0 / $count;

                foreach ($journeyTouchpoints as $touchpoint) {
                    $results[] = [
                        'conversion_id' => $conversion->id,
                        'touchpoint_id' => $touchpoint->id,
                        'channel' => $touchpoint->channel,
                        'campaign_id' => $touchpoint->campaign_id,
                        'credit' => $credit,
                        'model' => 'linear',
                    ];
                }
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Time Decay Attribution - Exponential decay based on time to conversion.
     */
    protected function timeDecayAttribution(Collection $conversions, Collection $touchpoints): array
    {
        $halfLife = $this->config['decay_half_life_days'];
        $results = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);

            if ($journeyTouchpoints->isEmpty()) continue;

            $conversionTime = Carbon::parse($conversion->converted_at);
            $weights = [];

            foreach ($journeyTouchpoints as $tp) {
                $daysBefore = Carbon::parse($tp->occurred_at)->diffInDays($conversionTime, false);
                // Exponential decay: weight = 0.5^(days/half_life)
                $weight = pow(0.5, max(0, $daysBefore) / $halfLife);
                $weights[$tp->id] = $weight;
            }

            $totalWeight = array_sum($weights);

            foreach ($journeyTouchpoints as $tp) {
                $credit = $weights[$tp->id] / $totalWeight;
                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $tp->id,
                    'channel' => $tp->channel,
                    'campaign_id' => $tp->campaign_id,
                    'credit' => $credit,
                    'model' => 'time_decay',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Position-Based (U-Shaped) Attribution - 40% first, 40% last, 20% middle.
     */
    protected function positionBasedAttribution(Collection $conversions, Collection $touchpoints): array
    {
        $results = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            $count = $journeyTouchpoints->count();

            if ($count === 0) continue;

            $touchpointsArray = $journeyTouchpoints->values()->all();

            foreach ($touchpointsArray as $index => $tp) {
                $credit = match (true) {
                    $count === 1 => 1.0,
                    $index === 0 => 0.4,
                    $index === $count - 1 => 0.4,
                    default => 0.2 / ($count - 2),
                };

                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $tp->id,
                    'channel' => $tp->channel,
                    'campaign_id' => $tp->campaign_id,
                    'credit' => $credit,
                    'model' => 'position_based',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Data-Driven Attribution using Shapley Values.
     */
    protected function dataDrivenAttribution(Collection $conversions, Collection $touchpoints): array
    {
        // Build channel sequences for converting and non-converting paths
        $convertingPaths = $this->buildSequences($conversions, $touchpoints, true);
        $nonConvertingPaths = $this->getNonConvertingPaths();

        // Calculate channel contribution using Shapley value approximation
        $channelValues = $this->calculateShapleyValues($convertingPaths, $nonConvertingPaths);

        $results = [];
        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            
            foreach ($journeyTouchpoints as $tp) {
                $channelValue = $channelValues[$tp->channel] ?? 0;
                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $tp->id,
                    'channel' => $tp->channel,
                    'campaign_id' => $tp->campaign_id,
                    'credit' => $channelValue,
                    'model' => 'data_driven',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Markov Chain Attribution - probabilistic state transitions.
     */
    protected function markovChainAttribution(Collection $conversions, Collection $touchpoints): array
    {
        // Build transition matrix
        $channels = $touchpoints->pluck('channel')->unique()->values()->toArray();
        $channelIndex = array_flip($channels);
        $n = count($channels);

        if ($n === 0) return ['channels' => [], 'attribution' => []];

        // Build transition matrix from converting paths
        $transitions = array_fill(0, $n, array_fill(0, $n, 0));
        $startCounts = array_fill(0, $n, 0);
        $conversionCounts = array_fill(0, $n, 0);

        $convertingPaths = $this->buildSequences($conversions, $touchpoints, true);

        foreach ($convertingPaths as $path) {
            $indices = array_map(fn($c) => $channelIndex[$c] ?? -1, $path);
            $indices = array_filter($indices, fn($i) => $i >= 0);

            if (empty($indices)) continue;

            $startCounts[$indices[0]]++;
            $conversionCounts[$indices[count($indices) - 1]]++;

            for ($i = 0; $i < count($indices) - 1; $i++) {
                $transitions[$indices[$i]][$indices[$i + 1]]++;
            }
        }

        // Add non-converting paths
        $nonConvertingPaths = $this->getNonConvertingPaths();
        foreach ($nonConvertingPaths as $path) {
            $indices = array_map(fn($c) => $channelIndex[$c] ?? -1, $path);
            $indices = array_filter($indices, fn($i) => $i >= 0);

            for ($i = 0; $i < count($indices) - 1; $i++) {
                $transitions[$indices[$i]][$indices[$i + 1]]++;
            }
        }

        // Normalize to probabilities
        for ($i = 0; $i < $n; $i++) {
            $rowSum = array_sum($transitions[$i]);
            if ($rowSum > 0) {
                for ($j = 0; $j < $n; $j++) {
                    $transitions[$i][$j] /= $rowSum;
                }
            }
        }

        // Calculate removal effect for each channel
        $removalEffects = [];
        for ($c = 0; $c < $n; $c++) {
            $removalEffects[$channels[$c]] = $this->calculateRemovalEffect($transitions, $c, $startCounts, $conversionCounts);
        }

        // Normalize removal effects to get attribution
        $totalEffect = array_sum($removalEffects);
        $attribution = [];
        foreach ($removalEffects as $channel => $effect) {
            $attribution[$channel] = $totalEffect > 0 ? $effect / $totalEffect : 0;
        }

        // Apply to conversions
        $results = [];
        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            foreach ($journeyTouchpoints as $tp) {
                $credit = $attribution[$tp->channel] ?? 0;
                $results[] = [
                    'conversion_id' => $conversion->id,
                    'touchpoint_id' => $tp->id,
                    'channel' => $tp->channel,
                    'campaign_id' => $tp->campaign_id,
                    'credit' => $credit,
                    'model' => 'markov_chain',
                ];
            }
        }

        return $this->aggregateByChannel($results);
    }

    /**
     * Build channel sequences from conversions and touchpoints.
     */
    protected function buildSequences(Collection $conversions, Collection $touchpoints, bool $converting): array
    {
        $paths = [];

        foreach ($conversions as $conversion) {
            $journeyTouchpoints = $touchpoints->where('conversion_event_id', $conversion->id);
            $path = $journeyTouchpoints->pluck('channel')->toArray();
            
            if (!empty($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Get non-converting paths (for data-driven models).
     */
    protected function getNonConvertingPaths(): array
    {
        // In a real implementation, this would query non-converting user journeys
        // For now, return empty array - would need session/user tracking data
        return [];
    }

    /**
     * Calculate Shapley values for channels.
     */
    protected function calculateShapleyValues(array $convertingPaths, array $nonConvertingPaths): array
    {
        $channels = collect($convertingPaths)->flatten()->unique()->values()->toArray();
        $shapleyValues = [];

        foreach ($channels as $channel) {
            $marginalContribution = 0;
            $subsets = $this->getSubsets($channels, $channel);

            foreach ($subsets as $subset) {
                $withChannel = $this->calculateConversionRate([...$subset, $channel], $convertingPaths, $nonConvertingPaths);
                $withoutChannel = $this->calculateConversionRate($subset, $convertingPaths, $nonConvertingPaths);
                $marginalContribution += ($withChannel - $withoutChannel);
            }

            $shapleyValues[$channel] = $marginalContribution / max(1, count($subsets));
        }

        // Normalize
        $total = array_sum($shapleyValues);
        if ($total > 0) {
            foreach ($shapleyValues as $k => $v) {
                $shapleyValues[$k] = $v / $total;
            }
        }

        return $shapleyValues;
    }

    /**
     * Get all subsets of channels excluding the target.
     */
    protected function getSubsets(array $channels, string $target): array
    {
        $otherChannels = array_filter($channels, fn($c) => $c !== $target);
        $subsets = [[]];

        foreach ($otherChannels as $channel) {
            $newSubsets = [];
            foreach ($subsets as $subset) {
                $newSubsets[] = [...$subset, $channel];
            }
            $subsets = array_merge($subsets, $newSubsets);
        }

        return $subsets;
    }

    /**
     * Calculate conversion rate for a set of channels.
     */
    protected function calculateConversionRate(array $channels, array $convertingPaths, array $nonConvertingPaths): float
    {
        $converting = 0;
        $total = 0;

        foreach ($convertingPaths as $path) {
            $hasChannel = count(array_intersect($path, $channels)) > 0;
            if ($hasChannel) $total++;
            if ($hasChannel && end($path)) $converting++; // Simplified
        }

        foreach ($nonConvertingPaths as $path) {
            $hasChannel = count(array_intersect($path, $channels)) > 0;
            if ($hasChannel) $total++;
        }

        return $total > 0 ? $converting / $total : 0;
    }

    /**
     * Calculate removal effect for Markov chain.
     */
    protected function calculateRemovalEffect(array $transitions, int $removedChannel, array $startCounts, array $conversionCounts): float
    {
        $n = count($transitions);
        
        // Create modified transition matrix with removed channel
        $modifiedTransitions = $transitions;
        for ($i = 0; $i < $n; $i++) {
            $modifiedTransitions[$i][$removedChannel] = 0;
            $modifiedTransitions[$removedChannel][$i] = 0;
        }
        $modifiedTransitions[$removedChannel][$removedChannel] = 1; // Absorbing state

        // Calculate total conversions with and without channel
        $originalConversions = $this->simulateConversions($transitions, $startCounts, $conversionCounts);
        $modifiedConversions = $this->simulateConversions($modifiedTransitions, $startCounts, $conversionCounts);

        return $originalConversions - $modifiedConversions;
    }

    /**
     * Simulate conversions through Markov chain.
     */
    protected function simulateConversions(array $transitions, array $startCounts, array $conversionCounts): float
    {
        $n = count($transitions);
        $state = $startCounts;
        $totalConversions = 0;

        // Simulate for a reasonable number of steps
        for ($step = 0; $step < 20; $step++) {
            $newState = array_fill(0, $n, 0);
            $stepConversions = 0;

            for ($i = 0; $i < $n; $i++) {
                if ($state[$i] <= 0) continue;
                
                for ($j = 0; $j < $n; $j++) {
                    if ($transitions[$i][$j] > 0) {
                        $newState[$j] += $state[$i] * $transitions[$i][$j];
                    }
                }
            }

            // Count conversions from states that represent conversion
            for ($i = 0; $i < $n; $i++) {
                if ($conversionCounts[$i] > 0) {
                    $stepConversions += $newState[$i];
                }
            }

            $totalConversions += $stepConversions;
            $state = $newState;

            if (array_sum($state) < 0.001) break; // Converged
        }

        return $totalConversions;
    }

    /**
     * Aggregate touchpoint-level results by channel.
     */
    protected function aggregateByChannel(array $touchpointResults): array
    {
        $byChannel = [];
        $byCampaign = [];

        foreach ($touchpointResults as $result) {
            $channel = $result['channel'];
            $campaignId = $result['campaign_id'];

            // By channel
            if (!isset($byChannel[$channel])) {
                $byChannel[$channel] = [
                    'channel' => $channel,
                    'total_credit' => 0,
                    'conversions' => 0,
                    'touchpoints' => 0,
                ];
            }
            $byChannel[$channel]['total_credit'] += $result['credit'];
            $byChannel[$channel]['touchpoints']++;

            // By campaign
            if ($campaignId) {
                if (!isset($byCampaign[$campaignId])) {
                    $byCampaign[$campaignId] = [
                        'campaign_id' => $campaignId,
                        'total_credit' => 0,
                        'conversions' => 0,
                    ];
                }
                $byCampaign[$campaignId]['total_credit'] += $result['credit'];
            }
        }

        // Count unique conversions per channel
        $conversionsByChannel = [];
        foreach ($touchpointResults as $result) {
            $conversionsByChannel[$result['channel']][$result['conversion_id']] = true;
        }
        foreach ($byChannel as $channel => &$data) {
            $data['conversions'] = count($conversionsByChannel[$channel] ?? []);
        }

        // Sort by credit descending
        uasort($byChannel, fn($a, $b) => $b['total_credit'] <=> $a['total_credit']);
        uasort($byCampaign, fn($a, $b) => $b['total_credit'] <=> $a['total_credit']);

        return [
            'model' => $touchpointResults[0]['model'] ?? 'unknown',
            'total_conversions' => count(array_unique(array_column($touchpointResults, 'conversion_id'))),
            'by_channel' => array_values($byChannel),
            'by_campaign' => array_values($byCampaign),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Compare multiple attribution models.
     */
    public function compareModels(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $models = ['last_click', 'first_click', 'linear', 'time_decay', 'position_based', 'data_driven', 'markov_chain'];
        $comparison = [];

        foreach ($models as $model) {
            $comparison[$model] = $this->calculateAttribution($model, $startDate, $endDate);
        }

        // Calculate correlation between models
        $correlations = $this->calculateModelCorrelations($comparison);

        return [
            'models' => $comparison,
            'correlations' => $correlations,
            'recommended_model' => $this->recommendModel($comparison),
        ];
    }

    /**
     * Calculate correlation between attribution models.
     */
    protected function calculateModelCorrelations(array $models): array
    {
        $correlations = [];
        $modelNames = array_keys($models);

        foreach ($modelNames as $i => $modelA) {
            foreach ($modelNames as $j => $modelB) {
                if ($i >= $j) continue;

                $channelCreditsA = collect($models[$modelA]['by_channel'] ?? [])->pluck('total_credit', 'channel');
                $channelCreditsB = collect($models[$modelB]['by_channel'] ?? [])->pluck('total_credit', 'channel');

                $commonChannels = $channelCreditsA->keys()->intersect($channelCreditsB->keys());

                if ($commonChannels->count() < 2) {
                    $correlations["{$modelA}_vs_{$modelB}"] = null;
                    continue;
                }

                $valuesA = $channelCreditsA->intersectByKeys($channelCreditsB->intersectByKeys($commonChannels))->values();
                $valuesB = $channelCreditsB->intersectByKeys($channelCreditsA->intersectByKeys($commonChannels))->values();

                $correlation = $this->pearsonCorrelation($valuesA->toArray(), $valuesB->toArray());
                $correlations["{$modelA}_vs_{$modelB}"] = round($correlation, 4);
            }
        }

        return $correlations;
    }

    /**
     * Calculate Pearson correlation coefficient.
     */
    protected function pearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) return 0;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));

        return $denominator !== 0 ? $numerator / $denominator : 0;
    }

    /**
     * Recommend best model based on data characteristics.
     */
    protected function recommendModel(array $comparison): string
    {
        // Simple heuristic: if models highly correlated, use simpler model
        // If diverging, use data_driven or markov_chain
        $correlations = $this->calculateModelCorrelations($comparison);
        
        $avgCorrelation = collect($correlations)->filter()->avg() ?? 0;
        
        if ($avgCorrelation > 0.9) {
            return 'linear'; // Models agree, use simple
        } elseif ($avgCorrelation > 0.7) {
            return 'position_based'; // Moderate agreement
        }
        
        return 'data_driven'; // Models disagree, use advanced
    }
}
