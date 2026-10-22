<?php

namespace App\Agents\AI;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Services\AI\LLMService;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ChatAnalyticsAgent extends BaseAgent
{
    protected LLMService $llm;

    public function __construct(LLMService $llm)
    {
        $this->llm = $llm;
    }

    public function getId(): string
    {
        return 'analytics.chat';
    }

    public function getName(): string
    {
        return 'Chat Analytics Agent';
    }

    public function getDescription(): string
    {
        return 'Natural language interface for querying analytics, generating reports, and getting insights';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::ATTRIBUTION_CALCULATE,
            AgentCapability::FORECAST_PREDICT,
            AgentCapability::ANOMALY_DETECT,
            AgentCapability::RECOMMENDATION_GENERATE,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'llm_provider' => [
                'type' => 'string',
                'required' => false,
                'default' => 'openai',
            ],
            'llm_model' => [
                'type' => 'string',
                'required' => false,
                'default' => 'gpt-4-turbo',
            ],
            'max_query_rows' => [
                'type' => 'integer',
                'required' => false,
                'default' => 100,
            ],
            'include_visualizations' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'auto_generate_charts' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
        ];
    }

    protected function performExecution(AgentContext $context): AgentResult
    {
        $payload = $context->payload;
        $tenant = $context->tenant;

        $question = $payload['question'] ?? '';
        $clientId = $payload['client_id'] ?? null;
        $campaignId = $payload['campaign_id'] ?? null;
        $dateRange = $payload['date_range'] ?? 'last_30_days';
        $contextData = $payload['context'] ?? [];

        if (empty($question)) {
            return AgentResult::failure('Question is required');
        }

        // Load context
        $client = $clientId ? \App\Models\Client::find($clientId) : null;
        $campaign = $payload['campaign_id'] ? Campaign::find($payload['campaign_id']) : null;

        // Classify question intent
        $intent = $this->classifyIntent($question);

        // Execute based on intent
        $answer = match ($intent) {
            'metrics' => $this->answerMetricsQuestion($question, $tenant, $client, $campaign, $dateRange),
            'comparison' => $this->answerComparisonQuestion($question, $tenant, $client, $campaign, $dateRange),
            'trend' => $this->answerTrendQuestion($question, $tenant, $client, $campaign, $dateRange),
            'anomaly' => $this->answerAnomalyQuestion($question, $tenant, $client, $campaign, $dateRange),
            'forecast' => $this->answerForecastQuestion($question, $tenant, $client, $campaign, $dateRange),
            'attribution' => $this->answerAttributionQuestion($question, $tenant, $client, $campaign, $dateRange),
            'recommendation' => $this->answerRecommendationQuestion($question, $tenant, $client, $campaign, $dateRange),
            'explanation' => $this->answerExplanationQuestion($question, $tenant, $client, $campaign, $dateRange),
            default => $this->answerGeneralQuestion($question, $tenant, $client, $campaign, $dateRange),
        };

        // Generate follow-up suggestions
        $followUps = $this->generateFollowUpQuestions($question, $intent, $answer);

        // Generate visualization if enabled
        $visualization = null;
        if ($this->config['auto_generate_charts'] && in_array($intent, ['metrics', 'trend', 'comparison', 'forecast'])) {
            $visualization = $this->generateVisualization($intent, $answer, $dateRange);
        }

        return AgentResult::success([
            'answer' => $answer,
            'intent' => $intent,
            'confidence' => $answer['confidence'] ?? 0.8,
            'data' => $answer['data'] ?? null,
            'visualization' => $visualization,
            'follow_up_questions' => $followUps,
            'sql_query' => $answer['sql'] ?? null,
        ], [
            'intent' => $intent,
            'question' => $question,
        ])->withNextAction('ask_followup', [
            'suggested_questions' => $followUps,
        ]);
    }

    /**
     * Classify the intent of the user's question.
     */
    protected function classifyIntent(string $question): string
    {
        $questionLower = strtolower($question);

        $patterns = [
            'metrics' => ['how many', 'what is', 'show me', 'total', 'count', 'spend', 'revenue', 'roas', 'cpa', 'ctr', 'impressions', 'clicks', 'conversions'],
            'comparison' => ['compare', 'versus', 'vs', 'difference', 'better', 'worse', 'which', 'between'],
            'trend' => ['trend', 'over time', 'growth', 'decline', 'change', 'month over month', 'week over week', 'year over year', 'historical'],
            'anomaly' => ['anomaly', 'unusual', 'strange', 'spike', 'drop', 'sudden', 'outlier', 'problem', 'issue', 'wrong'],
            'forecast' => ['predict', 'forecast', 'future', 'next month', 'next quarter', 'will', 'projection', 'estimate'],
            'attribution' => ['attribution', 'which channel', 'where did', 'source', 'touchpoint', 'journey', 'contribution'],
            'recommendation' => ['recommend', 'suggest', 'should i', 'what should', 'optimize', 'improve', 'action'],
            'explanation' => ['why', 'how', 'explain', 'reason', 'cause', 'because'],
        ];

        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($questionLower, $keyword)) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * Answer metrics questions.
     */
    protected function answerMetricsQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        $dates = $this->parseDateRange($dateRange);
        
        $query = DB::table('campaign_performance_daily')
            ->where('date', '>=', $dates['start'])
            ->where('date', '<=', $dates['end']);

        if ($client) {
            $query->join('campaigns', 'campaign_performance_daily.campaign_id', '=', 'campaigns.id')
                  ->where('campaigns.client_id', $client->id);
        }

        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }

        $metrics = $query->selectRaw('
            SUM(impressions) as total_impressions,
            SUM(clicks) as total_clicks,
            SUM(conversions) as total_conversions,
            SUM(spend) as total_spend,
            SUM(conversion_value) as total_revenue,
            AVG(ctr) as avg_ctr,
            AVG(cpa) as avg_cpa,
            AVG(roas) as avg_roas
        ')->first();

        $ctr = $metrics->total_impressions > 0 ? round(($metrics->total_clicks / $metrics->total_impressions) * 100, 2) : 0;
        $cvr = $metrics->total_clicks > 0 ? round(($metrics->total_conversions / $metrics->total_clicks) * 100, 2) : 0;
        $cpa = $metrics->total_conversions > 0 ? round($metrics->total_spend / $metrics->total_conversions, 2) : 0;
        $roas = $metrics->total_spend > 0 ? round($metrics->total_revenue / $metrics->total_spend, 2) : 0;

        $answer = "Here are your key metrics for {$dateRange}:\n\n";
        $answer .= "📊 **Overview**\n";
        $answer .= "- **Total Spend**: \${$metrics->total_spend}\n";
        $answer .= "- **Total Revenue**: \${$metrics->total_revenue}\n";
        $answer .= "- **ROAS**: {$roas}x\n";
        $answer .= "- **Total Conversions**: {$metrics->total_conversions}\n";
        $answer .= "- **CPA**: \${$cpa}\n\n";
        $answer .= "📈 **Engagement**\n";
        $answer .= "- **Impressions**: {$metrics->total_impressions}\n";
        $answer .= "- **Clicks**: {$metrics->total_clicks}\n";
        $answer .= "- **CTR**: {$ctr}%\n";
        $answer .= "- **CVR**: {$cvr}%\n";

        // Add comparison to previous period
        $prevDates = $this->getPreviousPeriod($dateRange);
        $prevMetrics = DB::table('campaign_performance_daily')
            ->where('date', '>=', $prevDates['start'])
            ->where('date', '<=', $prevDates['end'])
            ->when($client, fn($q) => $q->join('campaigns', 'campaign_performance_daily.campaign_id', '=', 'campaigns.id')
                ->where('campaigns.client_id', $client->id))
            ->when($campaign, fn($q) => $q->where('campaign_id', $campaign->id))
            ->selectRaw('SUM(spend) as spend, SUM(conversion_value) as revenue, SUM(conversions) as conversions')
            ->first();

        if ($prevMetrics && ($prevMetrics->spend ?? 0) > 0) {
            $spendChange = (($metrics->total_spend - $prevMetrics->spend) / $prevMetrics->spend) * 100;
            $revenueChange = (($metrics->total_revenue - $prevMetrics->revenue) / $prevMetrics->revenue) * 100;
            
            $answer .= "\n📊 **vs Previous Period**\n";
            $answer .= "- Spend: " . ($spendChange >= 0 ? '+' : '') . round($spendChange, 1) . "%\n";
            $answer .= "- Revenue: " . ($revenueChange >= 0 ? '+' : '') . round($revenueChange, 1) . "%\n";
        }

        return [
            'type' => 'metrics',
            'text' => $answer,
            'data' => $metrics,
            'confidence' => 0.95,
        ];
    }

    /**
     * Answer comparison questions.
     */
    protected function answerComparisonQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        // Extract entities to compare from question
        // This is simplified - in production use NER
        
        $dates = $this->parseDateRange($dateRange);
        
        // Get all campaigns for client
        $campaigns = Campaign::where('tenant_id', $tenant->id)
            ->when($client, fn($q) => $q->where('client_id', $client->id))
            ->where('status', 'active')
            ->get();

        if ($campaigns->count() < 2) {
            return [
                'type' => 'comparison',
                'text' => "Need at least 2 campaigns to compare. You have {$campaigns->count()} active campaign(s).",
                'data' => [],
                'confidence' => 0.5,
            ];
        }

        $metrics = $campaigns->map(function ($c) use ($dateRange) {
            $dates = $this->parseDateRange($dateRange);
            $data = DB::table('campaign_performance_daily')
                ->where('campaign_id', $c->id)
                ->whereBetween('date', [$dates['start'], $dates['end']])
                ->selectRaw('SUM(spend) as spend, SUM(conversion_value) as revenue, SUM(conversions) as conversions, SUM(clicks) as clicks, SUM(impressions) as impressions')
                ->first();

            return [
                'campaign' => $c->name,
                'spend' => $c->spend ?? 0,
                'revenue' => $c->conversion_value ?? 0,
                'conversions' => $c->conversions ?? 0,
                'roas' => $c->roas ?? 0,
                'cpa' => $c->cpa ?? 0,
                'ctr' => $c->ctr ?? 0,
            ];
        })->sortByDesc('roas')->values();

        $answer = "📊 **Campaign Comparison** ({$dateRange})\n\n";
        
        foreach ($metrics as $i => $m) {
            $medal = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => ($i+1) . '.' };
            $answer .= "{$medal} **{$m['campaign']}**\n";
            $answer .= "   ROAS: {$m['roas']}x | CPA: \${$m['cpa']} | Spend: \${$m['spend']} | Revenue: \${$m['revenue']}\n\n";
        }

        // Add insights
        $best = $metrics->first();
        $worst = $metrics->last();
        $answer .= "💡 **Insights**:\n";
        $answer .= "- **Top performer**: {$best['campaign']} (ROAS: {$best['roas']}x)\n";
        $answer .= "- **Needs attention**: {$worst['campaign']} (ROAS: {$worst['roas']}x)\n";
        $answer .= "- **Gap**: " . round(($best['roas'] - $worst['roas']) / max(0.01, $worst['roas']) * 100, 0) . "% difference in ROAS\n";

        return [
            'type' => 'comparison',
            'text' => $answer,
            'data' => $metrics,
            'confidence' => 0.9,
        ];
    }

    /**
     * Answer trend questions.
     */
    protected function answerTrendQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        $dates = $this->parseDateRange($dateRange);
        
        $query = DB::table('campaign_performance_daily')
            ->whereBetween('date', [$dates['start'], $dates['end']]);

        if ($client) {
            $query->join('campaigns', 'campaign_performance_daily.campaign_id', '=', 'campaigns.id')
                  ->where('campaigns.client_id', $client->id);
        }

        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }

        $dailyData = $query->selectRaw('
            date,
            SUM(spend) as spend,
            SUM(conversion_value) as revenue,
            SUM(conversions) as conversions,
            SUM(clicks) as clicks,
            SUM(impressions) as impressions
        ')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        if ($dailyData->isEmpty()) {
            return [
                'type' => 'trend',
                'text' => "No data available for the selected period.",
                'data' => [],
                'confidence' => 0.3,
            ];
        }

        // Calculate trends
        $firstWeek = $dailyData->take(7)->avg('revenue') ?? 0;
        $lastWeek = $dailyData->slice(-7)->avg('revenue') ?? 0;
        $trend = $lastWeek - $firstWeek;
        $trendPct = $firstWeek > 0 ? round(($trend / $firstWeek) * 100, 1) : 0;

        $trendDirection = $trend > 0 ? '📈 Increasing' : ($trend < 0 ? '📉 Decreasing' : '➡️ Stable');

        $answer = "📈 **Performance Trend** ({$dateRange})\n\n";
        $answer .= "**Overall Trend**: {$trendDirection} ({$trendPct}%)\n\n";
        
        // Weekly breakdown
        $weeks = $dailyData->chunk(7)->map(function ($week) {
            return [
                'week_start' => $week->first()->date,
                'revenue' => $week->sum('revenue'),
                'spend' => $week->sum('spend'),
                'roas' => $week->sum('spend') > 0 ? round($week->sum('revenue') / $week->sum('spend'), 2) : 0,
            ];
        });

        $answer .= "**Weekly Breakdown**:\n";
        foreach ($weeks as $i => $week) {
            $answer .= "- Week " . ($i+1) . " ({$week['week_start']}): Revenue \${$week['revenue']} | Spend \${$week['spend']} | ROAS {$week['roas']}x\n";
        }

        // Calculate growth rate
        if ($weeks->count() >= 2) {
            $firstWeekRev = $weeks->first()['revenue'];
            $lastWeekRev = $weeks->last()['revenue'];
            $weeklyGrowth = $firstWeekRev > 0 ? round((($lastWeekRev - $firstWeekRev) / $firstWeekRev) * 100, 1) : 0;
            $answer .= "\n**Weekly Growth Rate**: {$weeklyGrowth}%\n";
        }

        return [
            'type' => 'trend',
            'text' => $answer,
            'data' => $dailyData,
            'confidence' => 0.9,
        ];
    }

    /**
     * Answer anomaly questions.
     */
    protected function answerAnomalyQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        // Use the anomaly detection service
        $anomalyService = new \App\Services\Intelligence\Analytics\AnomalyDetection($client ?? Client::firstWhere('tenant_id', $tenant->id), []);
        $result = $anomalyService->detectAllAnomalies();

        if ($result['total_anomalies'] === 0) {
            return [
                'type' => 'anomaly',
                'text' => "✅ No anomalies detected in the current period. All metrics are within normal ranges.",
                'data' => $result,
                'confidence' => 0.9,
            ];
        }

        $answer = "🚨 **Anomalies Detected**: {$result['total_anomalies']} issues found\n\n";
        
        $bySeverity = $result['by_severity'];
        $answer .= "**Severity Breakdown**:\n";
        foreach (['critical', 'high', 'medium', 'low'] as $sev) {
            if (($bySeverity[$sev] ?? 0) > 0) {
                $emoji = match($sev) { 'critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', default => '🟢' };
                $answer .= "- {$emoji} {$sev}: {$bySeverity[$sev]}\n";
            }
        }

        $answer .= "\n**Top Issues**:\n";
        foreach (array_slice($result['anomalies'], 0, 5) as $anomaly) {
            $emoji = match($anomaly['severity']) { 'critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', default => '🟢' };
            $answer .= "{$emoji} **{$anomaly['message']}** ({$anomaly['entity_type']} #{$anomaly['entity_id']})\n";
        }

        return [
            'type' => 'anomaly',
            'text' => $answer,
            'data' => $result,
            'confidence' => 0.85,
        ];
    }

    /**
     * Answer forecast questions.
     */
    protected function answerForecastQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        $forecasting = new \App\Services\Intelligence\Analytics\Forecasting($client ?? Client::firstWhere('tenant_id', $tenant->id));
        
        if ($campaign) {
            $result = $forecasting->forecastCampaign($campaign->id, 30);
        } else {
            $result = $forecasting->forecastAllCampaigns(30);
        }

        if (isset($result['error'])) {
            return [
                'type' => 'forecast',
                'text' => "Unable to generate forecast: {$result['error']}",
                'data' => $result,
                'confidence' => 0.3,
            ];
        }

        $answer = "🔮 **30-Day Forecast**\n\n";

        if (isset($result['aggregate'])) {
            $agg = $result['aggregate'];
            $answer .= "**Aggregate Forecast (All Campaigns)**:\n";
            $answer .= "- **Projected Spend**: \${$agg['spend'][0]['yhat'] ?? 'N/A'}\n";
            $answer .= "- **Projected Revenue**: \${$agg['revenue'][0]['yhat'] ?? 'N/A'}\n";
            $answer .= "- **Projected ROAS**: {$agg['roas'][0]['yhat'] ?? 'N/A'}x\n";
            $answer .= "- **Projected Conversions**: {$agg['conversions'][0]['yhat'] ?? 'N/A'}\n\n";
        }

        if (isset($result['individual'])) {
            $answer .= "**By Campaign**:\n";
            foreach ($result['individual'] as $cid => $forecast) {
                $campaign = Campaign::find($cid);
                if ($campaign) {
                    $lastForecast = end($forecast['forecasts']['revenue'] ?? []);
                    $answer .= "- {$campaign->name}: Projected Rev \${$lastForecast['yhat'] ?? 'N/A'} | ROAS {$forecast['forecasts']['roas'][count($forecast['forecasts']['roas'])-1]['yhat'] ?? 'N/A'}x\n";
                }
            }
        }

        $answer .= "\n⚠️ *Forecasts are estimates based on historical trends. Actual results may vary.*";

        return [
            'type' => 'forecast',
            'text' => $answer,
            'data' => $result,
            'confidence' => 0.75,
        ];
    }

    /**
     * Answer attribution questions.
     */
    protected function answerAttributionQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        $attribution = new \App\Services\Intelligence\Analytics\AttributionModeling($client ?? Client::firstWhere('tenant_id', $tenant->id));
        $result = $attribution->compareModels(
            $this->parseDateRange($dateRange)['start'],
            $this->parseDateRange($dateRange)['end']
        );

        $answer = "🎯 **Attribution Analysis** ({$dateRange})\n\n";
        
        $bestModel = $result['recommended_model'] ?? 'data_driven';
        $answer .= "**Recommended Model**: {$bestModel}\n\n";

        if (isset($result['models'][$bestModel]['by_channel'])) {
            $answer .= "**Attribution by Channel** ({$bestModel}):\n";
            foreach ($result['models'][$bestModel]['by_channel'] as $channel) {
                $answer .= "- {$channel['channel']}: {$channel['total_credit']}% credit ({$channel['conversions']} conversions)\n";
            }
        }

        if (isset($result['correlations'])) {
            $answer .= "\n**Model Correlations**:\n";
            foreach ($result['correlations'] as $pair => $corr) {
                $answer .= "- {$pair}: " . ($corr !== null ? round($corr * 100, 1) . '%' : 'N/A') . "\n";
            }
        }

        return [
            'type' => 'attribution',
            'text' => $answer,
            'data' => $result,
            'confidence' => 0.8,
        ];
    }

    /**
     * Answer recommendation questions.
     */
    protected function answerRecommendationQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        if (!$client) {
            return [
                'type' => 'recommendation',
                'text' => "Please specify a client to get personalized recommendations.",
                'data' => [],
                'confidence' => 0.5,
            ];
        }

        $engine = new \App\Services\Intelligence\RecommendationEngine($client);
        $recommendations = $engine->generateAllRecommendations();

        $answer = "💡 **Personalized Recommendations** for {$client->name}\n\n";

        $totalRecs = 0;
        foreach ($recommendations as $category => $recs) {
            if (empty($recs)) continue;
            
            $answer .= "**" . ucfirst($category) . "** (" . count($recs) . " recommendations)\n";
            foreach (array_slice($recs, 0, 2) as $rec) {
                $priorityEmoji = match($rec['priority'] ?? 'medium') { 'critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', default => '🟢' };
                $answer .= "{$priorityEmoji} **{$rec['title']}**: {$rec['description']}\n";
                $answer .= "   → Action: {$rec['action']}\n";
                $answer .= "   → Impact: {$rec['estimated_impact']} (Confidence: {$rec['confidence'] * 100}%)\n\n";
            }
            $totalRecs += count($recs);
        }

        $answer .= "\n**Total**: {$totalRecs} recommendations across " . count(array_filter($recommendations)) . " categories";

        return [
            'type' => 'recommendation',
            'text' => $answer,
            'data' => $recommendations,
            'confidence' => 0.85,
        ];
    }

    /**
     * Answer explanation questions.
     */
    protected function answerExplanationQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        $answer = "🤔 **Explanation**\n\n";

        if (str_contains(strtolower($question), 'why') && str_contains(strtolower($question), 'roas')) {
            $answer .= "ROAS (Return on Ad Spend) measures revenue generated per dollar spent on advertising.\n\n";
            $answer .= "**Formula**: Revenue ÷ Ad Spend\n\n";
            $answer .= "If your ROAS dropped, common causes:\n";
            $answer .= "1. **Creative fatigue** - Ads losing effectiveness over time\n";
            $answer .= "2. **Audience saturation** - Reaching same people too often\n";
            $answer .= "3. **Seasonality** - Natural demand fluctuations\n";
            $answer .= "4. **Competition** - New competitors bidding up costs\n";
            $answer .= "5. **Tracking issues** - Pixel/CAPI problems underreporting conversions\n";
            $answer .= "6. **Landing page changes** - Conversion rate dropped\n\n";
            $answer .= "💡 **Quick check**: Compare ROAS by campaign, creative, and audience to isolate the issue.";
        } elseif (str_contains(strtolower($question), 'why') && str_contains(strtolower($question), 'cpa')) {
            $answer .= "CPA (Cost Per Acquisition) measures cost to acquire one customer.\n\n";
            $answer .= "**Formula**: Ad Spend ÷ Conversions\n\n";
            $answer .= "If CPA increased:\n";
            $answer .= "1. **Higher CPMs** - Platform costs increased\n";
            $answer .= "2. **Lower conversion rate** - Creative or landing page issues\n";
            $answer .= "3. **Audience mismatch** - Targeting wrong people\n";
            $answer .= "4. **Funnel friction** - Checkout/lead form problems";
        } else {
            $answer .= "I can explain marketing metrics, campaign performance, or strategy concepts. Try asking:\n";
            $answer .= "- \"Why did my ROAS drop last week?\"\n";
            $answer .= "- \"Why is my CPA so high?\"\n";
            $answer .= "- \"How does attribution work?\"\n";
            $answer .= "- \"What's the difference between ROAS and ROI?\"";
        }

        return [
            'type' => 'explanation',
            'text' => $answer,
            'data' => [],
            'confidence' => 0.8,
        ];
    }

    /**
     * Answer general questions.
     */
    protected function answerGeneralQuestion(string $question, Tenant $tenant, ?Client $client, ?Campaign $campaign, string $dateRange): array
    {
        // Use LLM for general questions
        $prompt = "
You are a marketing analytics expert. Answer the user's question about their marketing data.

Question: {$question}

Context:
- Tenant: {$tenant->name}
- Client: " . ($client?->name ?? 'Not specified') . "
- Campaign: " . ($campaign?->name ?? 'Not specified') . "
- Date Range: {$dateRange}

Provide a helpful, concise answer. If you need more data, explain what you'd need.
";

        $result = $this->llm->complete($prompt, [
            'provider' => $this->config['llm_provider'] ?? 'openai',
            'model' => $this->config['llm_model'] ?? 'gpt-4-turbo',
            'temperature' => 0.3,
            'system_prompt' => 'You are a marketing analytics expert. Provide clear, actionable answers.',
        ]);

        return [
            'type' => 'general',
            'text' => $result['content'] ?? 'I need more information to answer that question.',
            'data' => [],
            'confidence' => 0.6,
        ];
    }

    /**
     * Parse date range string.
     */
    protected function parseDateRange(string $range): array
    {
        return match ($range) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'yesterday' => ['start' => now()->subDay()->startOfDay(), 'end' => now()->subDay()->endOfDay()],
            'last_7_days' => ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()],
            'last_30_days' => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
            'last_90_days' => ['start' => now()->subDays(90)->startOfDay(), 'end' => now()->endOfDay()],
            'this_month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'last_month' => ['start' => now()->subMonth()->startOfMonth(), 'end' => now()->subMonth()->endOfMonth()],
            'this_quarter' => ['start' => now()->startOfQuarter(), 'end' => now()->endOfQuarter()],
            'last_quarter' => ['start' => now()->subQuarter()->startOfQuarter(), 'end' => now()->subQuarter()->endOfQuarter()],
            default => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
        };
    }

    /**
     * Get previous period for comparison.
     */
    protected function getPreviousPeriod(string $range): array
    {
        $current = $this->parseDateRange($range);
        $days = $current['start']->diffInDays($current['end']) + 1;
        return [
            'start' => $current['start']->copy()->subDays($days),
            'end' => $current['start']->copy()->subDay(),
        ];
    }

    /**
     * Generate follow-up questions.
     */
    protected function generateFollowUpQuestions(string $question, string $intent, array $answer): array
    {
        $followUps = [
            'metrics' => [
                'How does this compare to last month?',
                'Which campaign drove the most revenue?',
                'What\'s our CTR by platform?',
            ],
            'comparison' => [
                'Why is the top campaign performing better?',
                'Should we pause the worst performer?',
                'Show me creative differences between top and bottom',
            ],
            'trend' => [
                'What caused the spike in week 2?',
                'Forecast next month\'s performance',
                'Break down by campaign',
            ],
            'anomaly' => [
                'Show me the root cause of the spike',
                'How do I fix the creative fatigue?',
                'Set up alerts for future anomalies',
            ],
            'forecast' => [
                'What assumptions drive this forecast?',
                'What if we increase budget by 20%?',
                'Show confidence intervals',
            ],
            'attribution' => [
                'Show me the customer journey for top converters',
                'Compare first-click vs last-click',
                'Which channels assist conversions?',
            ],
            'recommendation' => [
                'Show me the implementation plan for the top recommendation',
                'What\'s the risk of pausing the low ROAS campaign?',
                'Prioritize by impact vs effort',
            ],
            'explanation' => [
                'How do I fix this?',
                'Show me the data behind this',
                'What should I monitor?',
            ],
            'general' => [
                'Show me key metrics for this period',
                'What are my top 3 opportunities?',
                'Set up a weekly report',
            ],
        ];

        return $followUps[$intent] ?? $followUps['general'];
    }

    /**
     * Generate visualization spec.
     */
    protected function generateVisualization(string $intent, array $answer, string $dateRange): ?array
    {
        $data = $answer['data'] ?? [];
        
        if (empty($data)) return null;

        return match ($intent) {
            'metrics' => [
                'type' => 'kpi_cards',
                'title' => 'Key Metrics',
                'data' => [
                    ['label' => 'Spend', 'value' => '$' . number_format($data->total_spend ?? 0, 2)],
                    ['label' => 'Revenue', 'value' => '$' . number_format($data->total_revenue ?? 0, 2)],
                    ['label' => 'ROAS', 'value' => ($data->avg_roas ?? 0) . 'x'],
                    ['label' => 'Conversions', 'value' => $data->total_conversions ?? 0],
                    ['label' => 'CPA', 'value' => '$' . number_format($data->avg_cpa ?? 0, 2)],
                    ['label' => 'CTR', 'value' => round((($data->total_clicks ?? 0) / max(1, $data->total_impressions ?? 1)) * 100, 2) . '%'],
                ],
            ],
            'trend' => [
                'type' => 'line_chart',
                'title' => 'Revenue Trend',
                'x_key' => 'date',
                'y_keys' => ['revenue', 'spend'],
                'data' => $data,
            ],
            'comparison' => [
                'type' => 'bar_chart',
                'title' => 'Campaign Comparison',
                'x_key' => 'campaign',
                'y_keys' => ['roas', 'cpa', 'spend'],
                'data' => $data,
            ],
            'forecast' => [
                'type' => 'line_chart_with_confidence',
                'title' => '30-Day Forecast',
                'x_key' => 'ds',
                'y_keys' => ['yhat', 'yhat_lower', 'yhat_upper'],
                'data' => $data['individual'] ?? $data['aggregate'] ?? [],
            ],
            default => null,
        };
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'max_query_rows' => 100,
            'include_visualizations' => true,
            'auto_generate_charts' => true,
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
