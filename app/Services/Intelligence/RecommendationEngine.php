<?php

namespace App\Services\Intelligence;

use App\Models\Client;
use App\Models\Campaign;
use App\Models\CreativeAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Generate all recommendations for a client.
     */
    public function generateAllRecommendations(): array
    {
        return [
            'campaign' => $this->getCampaignRecommendations(),
            'budget' => $this->getBudgetRecommendations(),
            'creative' => $this->getCreativeRecommendations(),
            'audience' => $this->getAudienceRecommendations(),
            'platform' => $this->getPlatformRecommendations(),
            'automation' => $this->getAutomationRecommendations(),
        ];
    }

    /**
     * Campaign-level recommendations.
     */
    protected function getCampaignRecommendations(): array
    {
        $campaigns = $this->client->campaigns()->where('status', 'active')->get();
        $recommendations = [];

        foreach ($campaigns as $campaign) {
            $roas = $campaign->roas ?? 0;
            $cpa = $campaign->cpa ?? 0;
            $ctr = $campaign->ctr ?? 0;
            $utilization = $campaign->budget > 0 ? ($campaign->spent / $campaign->budget) * 100 : 0;

            // Underperforming campaign
            if ($roas < 1.0) {
                $recommendations[] = [
                    'type' => 'campaign_pause',
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'priority' => 'critical',
                    'title' => 'Pause Underperforming Campaign',
                    'description' => "Campaign \"{$campaign->name}\" has ROAS of {$roas} - below break-even",
                    'action' => 'Pause campaign and reallocate budget to better performers',
                    'estimated_impact' => 'Save ' . number_format($campaign->budget - $campaign->spent, 2) . ' in wasted spend',
                    'confidence' => 0.9,
                ];
            }

            // High ROAS with low spend - scale opportunity
            if ($roas > 3.0 && $utilization < 50) {
                $recommendations[] = [
                    'type' => 'campaign_scale',
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'priority' => 'high',
                    'title' => 'Scale High-Performing Campaign',
                    'description' => "Campaign \"{$campaign->name}\" has ROAS of {$roas} with only {$utilization}% budget used",
                    'action' => 'Increase daily budget by 20-30% and expand audiences',
                    'estimated_impact' => 'Potential ' . number_format(($campaign->budget * 0.3) * ($roas - 1), 2) . ' additional revenue',
                    'confidence' => 0.8,
                ];
            }

            // Low CTR - creative fatigue
            if ($ctr < 0.5) {
                $recommendations[] = [
                    'type' => 'creative_refresh',
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'priority' => 'high',
                    'title' => 'Refresh Creative Assets',
                    'description' => "Campaign \"{$campaign->name}\" has CTR of {$ctr}% - below platform average",
                    'action' => 'Test new creative variants (headlines, images, CTAs)',
                    'estimated_impact' => 'Potential 20-50% CTR improvement',
                    'confidence' => 0.75,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Budget allocation recommendations.
     */
    protected function getBudgetRecommendations(): array
    {
        $recommendations = [];
        $totalBudget = $this->client->monthly_budget ?? 0;
        $totalSpent = $this->client->campaigns()->where('status', 'active')->sum('spent');
        $utilization = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;

        // Over budget
        if ($utilization > 95) {
            $recommendations[] = [
                'type' => 'budget_increase',
                'priority' => 'critical',
                'title' => 'Budget Exhaustion Risk',
                'description' => "Client has used {$utilization}% of monthly budget",
                'action' => 'Request budget increase or pause low-ROI campaigns',
                'estimated_impact' => 'Prevent campaign shutdown and lost revenue',
                'confidence' => 0.95,
            ];
        }

        // Under budget - reallocation opportunity
        if ($utilization < 60) {
            $topCampaigns = $this->client->campaigns()
                ->where('status', 'active')
                ->where('roas', '>', 2.0)
                ->orderByDesc('roas')
                ->limit(3)
                ->get();

            if ($topCampaigns->count() > 0) {
                $recommendations[] = [
                    'type' => 'budget_reallocation',
                    'priority' => 'high',
                    'title' => 'Reallocate Underutilized Budget',
                    'description' => "Only {$utilization}% of budget spent. Top campaigns have capacity for more spend.",
                    'action' => 'Shift budget from low-ROI to high-ROI campaigns',
                    'campaigns' => $topCampaigns->pluck('name')->toArray(),
                    'estimated_impact' => 'Up to ' . number_format(($totalBudget - $totalSpent) * 2.5, 2) . ' additional revenue',
                    'confidence' => 0.8,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Creative recommendations.
     */
    protected function getCreativeRecommendations(): array
    {
        $recommendations = [];
        
        $creatives = CreativeAsset::where('client_id', $this->client->id)
            ->where('status', 'active')
            ->get();

        foreach ($creatives as $creative) {
            $fatigue = $creative->isFatigued();
            
            if ($fatigue['is_fatigued']) {
                $recommendations[] = [
                    'type' => 'creative_replace',
                    'creative_id' => $creative->id,
                    'creative_name' => $creative->name,
                    'priority' => $fatigue['severity'] === 'high' ? 'critical' : 'high',
                    'title' => 'Replace Fatigued Creative',
                    'description' => $fatigue['message'],
                    'action' => 'Pause creative and launch new variants from recommendations',
                    'variants' => $creative->generateVariantRecommendations(),
                    'confidence' => 0.85,
                ];
            }
        }

        // Check for creative testing opportunities
        $highPerformers = $creatives->filter(fn($c) => $c->performance_score > 70 && !$c->isFatigued()['is_fatigued']);
        
        if ($highPerformers->count() > 0) {
            $recommendations[] = [
                'type' => 'creative_testing',
                'priority' => 'medium',
                'title' => 'Launch Creative Tests for High Performers',
                'description' => $highPerformers->count() . ' creatives performing well - opportunity to test variants',
                'action' => 'Launch A/B tests on headlines, images, and CTAs',
                'creatives' => $highPerformers->pluck('name')->toArray(),
                'confidence' => 0.7,
            ];
        }

        return $recommendations;
    }

    /**
     * Audience recommendations.
     */
    protected function getAudienceRecommendations(): array
    {
        $recommendations = [];
        
        $campaigns = $this->client->campaigns()->where('status', 'active')->get();
        $platforms = $campaigns->pluck('platform')->unique()->toArray();
        $allPlatforms = ['meta', 'google', 'linkedin', 'tiktok'];
        $missingPlatforms = array_diff($allPlatforms, $platforms);

        // Platform expansion
        if (!empty($missingPlatforms)) {
            $recommendations[] = [
                'type' => 'platform_expansion',
                'priority' => 'medium',
                'title' => 'Expand to Untapped Platforms',
                'description' => 'Client is not advertising on: ' . implode(', ', $missingPlatforms),
                'action' => 'Launch pilot campaigns on ' . implode(', ', $missingPlatforms),
                'platforms' => $missingPlatforms,
                'estimated_impact' => 'Access new audiences, potential 20-40% reach increase',
                'confidence' => 0.65,
            ];
        }

        // Lookalike expansion
        $highROASCampaigns = $campaigns->filter(fn($c) => ($c->roas ?? 0) > 3.0);
        
        if ($highROASCampaigns->count() > 0) {
            $recommendations[] = [
                'type' => 'lookalike_expansion',
                'priority' => 'medium',
                'title' => 'Create Lookalike Audiences',
                'description' => $highROASCampaigns->count() . ' campaigns with ROAS > 3.0 - strong signal for lookalikes',
                'action' => 'Create 1%, 3%, 5% lookalikes from converters',
                'campaigns' => $highROASCampaigns->pluck('name')->toArray(),
                'confidence' => 0.7,
            ];
        }

        return $recommendations;
    }

    /**
     * Platform-specific recommendations.
     */
    protected function getPlatformRecommendations(): array
    {
        $recommendations = [];
        
        $campaigns = $this->client->campaigns()->where('status', 'active')->get();
        
        foreach (['meta', 'google', 'linkedin', 'tiktok'] as $platform) {
            $platformCampaigns = $campaigns->where('platform', $platform);
            
            if ($platformCampaigns->isEmpty()) continue;
            
            $avgRoas = $platformCampaigns->avg('roas') ?? 0;
            $totalSpend = $platformCampaigns->sum('spent');
            
            if ($platform === 'meta' && $avgRoas < 1.5) {
                $recommendations[] = [
                    'type' => 'platform_optimization',
                    'platform' => 'meta',
                    'priority' => 'high',
                    'title' => 'Optimize Meta Campaigns',
                    'description' => "Meta ROAS of {$avgRoas} below target",
                    'action' => 'Consolidate ad sets, test Advantage+ shopping, refresh creatives weekly',
                    'confidence' => 0.75,
                ];
            }
            
            if ($platform === 'google' && $avgRoas < 2.0) {
                $recommendations[] = [
                    'type' => 'platform_optimization',
                    'platform' => 'google',
                    'priority' => 'high',
                    'title' => 'Optimize Google Ads',
                    'description' => "Google ROAS of {$avgRoas} below target",
                    'action' => 'Review keyword quality scores, test Performance Max, add negative keywords',
                    'confidence' => 0.75,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Automation recommendations.
     */
    protected function getAutomationRecommendations(): array
    {
        $recommendations = [];
        
        // Check for missing automation rules
        $existingRules = DB::table('automation_rules')
            ->where('tenant_id', $this->client->tenant_id)
            ->where('is_active', true)
            ->count();

        if ($existingRules < 5) {
            $recommendations[] = [
                'type' => 'automation_setup',
                'priority' => 'medium',
                'title' => 'Set Up Automation Rules',
                'description' => "Only {$existingRules} active automation rules - missing opportunities for auto-optimization",
                'action' => 'Create rules for: budget pacing, bid adjustments, creative fatigue alerts, performance thresholds',
                'suggested_rules' => [
                    'Pause campaign if ROAS < 0.5 for 3 days',
                    'Increase budget 20% if ROAS > 4.0 and utilization < 80%',
                    'Alert on creative frequency > 3.0',
                    'Auto-pause campaigns at 95% budget utilization',
                ],
                'confidence' => 0.8,
            ];
        }

        return $recommendations;
    }
}
