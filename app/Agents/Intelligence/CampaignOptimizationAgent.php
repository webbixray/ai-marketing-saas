<?php

namespace App\Agents\Intelligence;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CampaignOptimizationAgent extends BaseAgent
{
    public function getId(): string
    {
        return 'campaign.optimization';
    }

    public function getName(): string
    {
        return 'Campaign Optimization Agent';
    }

    public function getDescription(): string
    {
        return 'Analyzes campaign performance and automatically applies optimizations for budget pacing, bid adjustments, and audience refinement';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CAMPAIGN_OPTIMIZE,
            AgentCapability::CAMPAIGN_ANALYZE,
            AgentCapability::CAMPAIGN_BUDGET_PACE,
            AgentCapability::CAMPAIGN_AUDIENCE_EXPAND,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'optimization_rules' => [
                'type' => 'array',
                'required' => true,
                'description' => 'Rules for when to apply optimizations',
            ],
            'budget_pacing_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'bid_adjustment_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'audience_refinement_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'max_daily_budget_change_pct' => [
                'type' => 'integer',
                'required' => false,
                'default' => 20,
            ],
            'min_performance_threshold' => [
                'type' => 'number',
                'required' => false,
                'default' => 0.5,
            ],
        ];
    }

    protected function performExecution(AgentContext $context): AgentResult
    {
        $tenant = $context->tenant;
        $results = [
            'campaigns_analyzed' => 0,
            'optimizations_applied' => 0,
            'budget_adjustments' => 0,
            'bid_adjustments' => 0,
            'audience_changes' => 0,
            'errors' => [],
        ];

        $campaigns = Campaign::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('approval_status', 'approved')
            ->with(['client'])
            ->get();

        foreach ($campaigns as $campaign) {
            try {
                $results['campaigns_analyzed']++;
                $analysis = $this->analyzeCampaign($campaign);
                $optimizations = $this->applyOptimizations($campaign, $analysis);
                
                $results['optimizations_applied'] += $optimizations['count'];
                $results['budget_adjustments'] += $optimizations['budget'];
                $results['bid_adjustments'] += $optimizations['bids'];
                $results['audience_changes'] += $optimizations['audience'];

            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ];
                Log::error("Campaign optimization failed", [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->storeOptimizationInsights($tenant, $results);

        return AgentResult::success($results, [
            'campaigns_processed' => $results['campaigns_analyzed'],
            'optimizations_applied' => $results['optimizations_applied'],
        ])->withNextAction('optimization_report_generated', [
            'tenant_id' => $tenant->id,
            'summary' => $results,
        ]);
    }

    protected function analyzeCampaign(Campaign $campaign): array
    {
        $analysis = [
            'campaign_id' => $campaign->id,
            'current_roas' => $campaign->roas ?? 0,
            'current_cpa' => $campaign->cpa ?? 0,
            'current_ctr' => $campaign->ctr ?? 0,
            'budget_utilization' => $campaign->budget > 0 ? ($campaign->spent / $campaign->budget) * 100 : 0,
            'days_running' => $campaign->start_date ? $campaign->start_date->diffInDays(now()) : 0,
            'trend' => 'stable',
            'recommendations' => [],
        ];

        $recentData = DB::table('campaign_performance_daily')
            ->where('campaign_id', $campaign->id)
            ->where('date', '>=', now()->subDays(7))
            ->orderBy('date', 'desc')
            ->get();

        if ($recentData->count() >= 3) {
            $firstHalf = $recentData->slice(0, (int)($recentData->count() / 2))->avg('roas') ?? 0;
            $secondHalf = $recentData->slice((int)($recentData->count() / 2))->avg('roas') ?? 0;
            
            if ($secondHalf > $firstHalf * 1.1) {
                $analysis['trend'] = 'improving';
            } elseif ($secondHalf < $firstHalf * 0.9) {
                $analysis['trend'] = 'declining';
            }
        }

        $analysis['recommendations'] = $this->generateRecommendations($campaign, $analysis);

        return $analysis;
    }

    protected function generateRecommendations(Campaign $campaign, array $analysis): array
    {
        $recommendations = [];

        if ($analysis['budget_utilization'] > 90 && $analysis['days_running'] < 30) {
            $recommendations[] = [
                'type' => 'budget_increase',
                'priority' => 'high',
                'reason' => 'Budget pacing too fast - risk of early exhaustion',
                'action' => 'Increase daily budget or reduce bid',
            ];
        } elseif ($analysis['budget_utilization'] < 50 && $analysis['days_running'] > 14) {
            $recommendations[] = [
                'type' => 'budget_decrease',
                'priority' => 'medium',
                'reason' => 'Budget underutilized - opportunity to scale or reallocate',
                'action' => 'Decrease daily budget or increase bid to spend more',
            ];
        }

        if ($analysis['current_roas'] < ($this->config['min_performance_threshold'] ?? 0.5)) {
            $recommendations[] = [
                'type' => 'bid_decrease',
                'priority' => 'high',
                'reason' => 'ROAS below threshold',
                'action' => 'Reduce bids to improve efficiency',
            ];
        } elseif ($analysis['current_roas'] > 3.0 && $analysis['budget_utilization'] < 80) {
            $recommendations[] = [
                'type' => 'bid_increase',
                'priority' => 'medium',
                'reason' => 'Strong ROAS with budget headroom',
                'action' => 'Increase bids to capture more volume',
            ];
        }

        if ($analysis['current_ctr'] < 0.5) {
            $recommendations[] = [
                'type' => 'creative_refresh',
                'priority' => 'high',
                'reason' => 'Low CTR indicates creative fatigue',
                'action' => 'Test new creative variants',
            ];
        }

        if ($analysis['trend'] === 'improving' && $analysis['current_roas'] > 2.0) {
            $recommendations[] = [
                'type' => 'audience_expand',
                'priority' => 'low',
                'reason' => 'Strong performance with growth potential',
                'action' => 'Expand to lookalike audiences',
            ];
        }

        return $recommendations;
    }

    protected function applyOptimizations(Campaign $campaign, array $analysis): array
    {
        $applied = ['count' => 0, 'budget' => 0, 'bids' => 0, 'audience' => 0];

        foreach ($analysis['recommendations'] as $rec) {
            try {
                switch ($rec['type']) {
                    case 'budget_increase':
                        if ($this->config['budget_pacing_enabled']) {
                            $this->adjustBudget($campaign, 'increase');
                            $applied['budget']++;
                        }
                        break;
                    case 'budget_decrease':
                        if ($this->config['budget_pacing_enabled']) {
                            $this->adjustBudget($campaign, 'decrease');
                            $applied['budget']++;
                        }
                        break;
                    case 'bid_increase':
                        if ($this->config['bid_adjustment_enabled']) {
                            $this->adjustBid($campaign, 'increase');
                            $applied['bids']++;
                        }
                        break;
                    case 'bid_decrease':
                        if ($this->config['bid_adjustment_enabled']) {
                            $this->adjustBid($campaign, 'decrease');
                            $applied['bids']++;
                        }
                        break;
                    case 'audience_expand':
                        if ($this->config['audience_refinement_enabled']) {
                            $this->expandAudience($campaign);
                            $applied['audience']++;
                        }
                        break;
                    case 'creative_refresh':
                        $applied['count']++;
                        break;
                }

                $applied['count']++;

                Log::info("Campaign optimization applied", [
                    'campaign_id' => $campaign->id,
                    'type' => $rec['type'],
                    'reason' => $rec['reason'],
                ]);

            } catch (\Throwable $e) {
                Log::error("Optimization failed", [
                    'campaign_id' => $campaign->id,
                    'type' => $rec['type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $applied;
    }

    protected function adjustBudget(Campaign $campaign, string $direction): void
    {
        $maxChange = ($this->config['max_daily_budget_change_pct'] ?? 20) / 100;
        $currentBudget = $campaign->daily_budget ?? ($campaign->budget / 30);
        $changeAmount = $currentBudget * $maxChange;

        $newBudget = $direction === 'increase' 
            ? $currentBudget + $changeAmount 
            : max($currentBudget - $changeAmount, $currentBudget * 0.5);

        $campaign->update(['daily_budget' => round($newBudget, 2)]);
    }

    protected function adjustBid(Campaign $campaign, string $direction): void
    {
        $maxChange = 0.15;
        $currentBid = $campaign->bid_amount ?? 1.00;
        $changeAmount = $currentBid * $maxChange;

        $newBid = $direction === 'increase' 
            ? $currentBid + $changeAmount 
            : max($currentBid - $changeAmount, 0.01);

        $campaign->update(['bid_amount' => round($newBid, 2)]);
    }

    protected function expandAudience(Campaign $campaign): void
    {
        Log::info("Audience expansion triggered", [
            'campaign_id' => $campaign->id,
            'current_audience' => $campaign->audience_targeting,
        ]);
    }

    protected function storeOptimizationInsights($tenant, array $results): void
    {
        DB::table('optimization_insights')->insert([
            'tenant_id' => $tenant->id,
            'insights' => json_encode($results),
            'created_at' => now(),
        ]);
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'budget_pacing_enabled' => true,
            'bid_adjustment_enabled' => true,
            'audience_refinement_enabled' => true,
            'max_daily_budget_change_pct' => 20,
            'min_performance_threshold' => 0.5,
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
