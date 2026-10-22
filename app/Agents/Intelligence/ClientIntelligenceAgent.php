<?php

namespace App\Agents\Intelligence;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Models\Client;
use App\Models\Campaign;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClientIntelligenceAgent extends BaseAgent
{
    public function getId(): string
    {
        return 'client.intelligence';
    }

    public function getName(): string
    {
        return 'Client Intelligence Agent';
    }

    public function getDescription(): string
    {
        return 'Monitors client health, predicts churn risk, identifies upsell opportunities, and generates proactive recommendations';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CLIENT_HEALTH_SCORE,
            AgentCapability::CLIENT_CHURN_PREDICT,
            AgentCapability::CLIENT_UPSELL_IDENTIFY,
            AgentCapability::CLIENT_REPORT_GENERATE,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'health_score_weights' => [
                'type' => 'array',
                'required' => false,
                'default' => [
                    'engagement' => 0.25,
                    'performance' => 0.30,
                    'communication' => 0.20,
                    'budget_utilization' => 0.15,
                    'satisfaction' => 0.10,
                ],
            ],
            'churn_risk_threshold' => [
                'type' => 'number',
                'required' => false,
                'default' => 0.7,
            ],
            'upsell_min_roi' => [
                'type' => 'number',
                'required' => false,
                'default' => 2.0,
            ],
            'report_frequency' => [
                'type' => 'string',
                'required' => false,
                'default' => 'weekly',
            ],
        ];
    }

    protected function performExecution(AgentContext $context): AgentResult
    {
        $tenant = $context->tenant;
        $results = [
            'clients_analyzed' => 0,
            'health_scores_updated' => 0,
            'churn_risks_identified' => 0,
            'upsell_opportunities' => 0,
            'reports_generated' => 0,
            'alerts_created' => [],
        ];

        $clients = Client::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with(['campaigns', 'accountManager'])
            ->get();

        foreach ($clients as $client) {
            try {
                $results['clients_analyzed']++;

                // Calculate health score
                $healthScore = $this->calculateHealthScore($client);
                $this->updateClientHealthScore($client, $healthScore);
                $results['health_scores_updated']++;

                // Check churn risk
                $churnRisk = $this->assessChurnRisk($client, $healthScore);
                if ($churnRisk > ($this->config['churn_risk_threshold'] ?? 0.7)) {
                    $results['churn_risks_identified']++;
                    $this->createChurnAlert($client, $churnRisk);
                    $results['alerts_created'][] = [
                        'client_id' => $client->id,
                        'type' => 'churn_risk',
                        'score' => $churnRisk,
                    ];
                }

                // Identify upsell opportunities
                $upsellOps = $this->identifyUpsellOpportunities($client);
                $results['upsell_opportunities'] += count($upsellOps);
                foreach ($upsellOps as $op) {
                    $results['alerts_created'][] = [
                        'client_id' => $client->id,
                        'type' => 'upsell_opportunity',
                        'details' => $op,
                    ];
                }

                // Generate scheduled report if needed
                if ($this->shouldGenerateReport($client)) {
                    $this->generateClientReport($client);
                    $results['reports_generated']++;
                }

            } catch (\Throwable $e) {
                Log::error("Client intelligence failed", [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return AgentResult::success($results, [
            'clients_processed' => $results['clients_analyzed'],
            'alerts_created' => count($results['alerts_created']),
        ]);
    }

    /**
     * Calculate comprehensive health score for a client.
     */
    protected function calculateHealthScore(Client $client): array
    {
        $weights = $this->config['health_score_weights'] ?? [
            'engagement' => 0.25,
            'performance' => 0.30,
            'communication' => 0.20,
            'budget_utilization' => 0.15,
            'satisfaction' => 0.10,
        ];

        $scores = [];

        // Engagement score (meetings, responses, activity)
        $scores['engagement'] = $this->calculateEngagementScore($client);

        // Performance score (campaign ROI, goal achievement)
        $scores['performance'] = $this->calculatePerformanceScore($client);

        // Communication score (response times, meeting attendance)
        $scores['communication'] = $this->calculateCommunicationScore($client);

        // Budget utilization score
        $scores['budget_utilization'] = $this->calculateBudgetUtilizationScore($client);

        // Satisfaction score (NPS, feedback)
        $scores['satisfaction'] = $this->calculateSatisfactionScore($client);

        // Calculate weighted total
        $totalScore = 0;
        foreach ($weights as $factor => $weight) {
            $totalScore += ($scores[$factor] ?? 0) * $weight;
        }

        return [
            'total_score' => round($totalScore, 2),
            'factors' => $scores,
            'weights' => $weights,
            'grade' => $this->scoreToGrade($totalScore),
            'trend' => $this->calculateScoreTrend($client),
        ];
    }

    /**
     * Calculate engagement score based on meetings, responses, activity.
     */
    protected function calculateEngagementScore(Client $client): float
    {
        // Last 30 days engagement
        $recentMeetings = DB::table('client_meetings')
            ->where('client_id', $client->id)
            ->where('meeting_date', '>=', now()->subDays(30))
            ->count();

        $responseRate = DB::table('client_communications')
            ->where('client_id', $client->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('direction', 'outbound')
            ->whereNotNull('response_at')
            ->count() / max(1, DB::table('client_communications')
                ->where('client_id', $client->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->where('direction', 'outbound')
                ->count());

        $portalLogins = DB::table('client_portal_sessions')
            ->where('client_id', $client->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Normalize to 0-100 scale
        $meetingScore = min(100, $recentMeetings * 10); // 10 meetings = 100
        $responseScore = $responseRate * 100;
        $portalScore = min(100, $portalLogins * 5); // 20 logins = 100

        return round(($meetingScore * 0.4 + $responseScore * 0.4 + $portalScore * 0.2), 2);
    }

    /**
     * Calculate performance score based on campaign results.
     */
    protected function calculatePerformanceScore(Client $client): float
    {
        $campaigns = $client->campaigns()->where('status', 'active')->get();
        
        if ($campaigns->isEmpty()) {
            return 50; // Neutral if no active campaigns
        }

        $avgRoas = $campaigns->avg('roas') ?? 0;
        $goalAchievement = $this->calculateGoalAchievement($client);

        // ROAS score: 2.0 = 50, 3.0 = 75, 4.0+ = 100
        $roasScore = min(100, ($avgRoas / 4) * 100);
        
        // Goal achievement: 0-100%
        $goalScore = $goalAchievement * 100;

        return round(($roasScore * 0.6 + $goalScore * 0.4), 2);
    }

    /**
     * Calculate communication score.
     */
    protected function calculateCommunicationScore(Client $client): float
    {
        // Average response time (hours)
        $avgResponseTime = DB::table('client_communications')
            ->where('client_id', $client->id)
            ->where('direction', 'outbound')
            ->whereNotNull('response_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->avg(DB::raw("TIMESTAMPDIFF(MINUTE, created_at, response_at)"));

        $avgResponseHours = ($avgResponseTime ?? 24) / 60;

        // Score: <1hr = 100, 4hr = 80, 24hr = 50, >48hr = 20
        if ($avgResponseHours <= 1) return 100;
        if ($avgResponseHours <= 4) return 80;
        if ($avgResponseHours <= 24) return 50;
        if ($avgResponseHours <= 48) return 30;
        return 20;
    }

    /**
     * Calculate budget utilization score.
     */
    protected function calculateBudgetUtilizationScore(Client $client): float
    {
        if (!$client->monthly_budget || $client->monthly_budget <= 0) {
            return 50;
        }

        $currentSpend = DB::table('campaigns')
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->sum('spent');

        $utilization = ($currentSpend / $client->monthly_budget) * 100;

        // Optimal: 85-95% = 100, 70-85% = 80, 50-70% = 60, <50% or >100% = lower
        if ($utilization >= 85 && $utilization <= 95) return 100;
        if ($utilization >= 70 && $utilization < 85) return 80;
        if ($utilization >= 50 && $utilization < 70) return 60;
        if ($utilization > 95) return 70; // Over budget risk
        return max(20, $utilization * 1.2); // Underutilized
    }

    /**
     * Calculate satisfaction score.
     */
    protected function calculateSatisfactionScore(Client $client): float
    {
        // Get latest NPS
        $latestNps = DB::table('client_nps')
            ->where('client_id', $client->id)
            ->orderBy('survey_date', 'desc')
            ->value('score');

        if (!$latestNps) {
            return 50; // Neutral if no data
        }

        // NPS: -100 to 100 -> 0 to 100
        return round(($latestNps + 100) / 2, 2);
    }

    /**
     * Calculate goal achievement percentage.
     */
    protected function calculateGoalAchievement(Client $client): float
    {
        $goals = DB::table('client_goals')
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->get();

        if ($goals->isEmpty()) {
            return 1.0; // No goals = 100% (neutral)
        }

        $achieved = $goals->filter(fn($g) => ($g->current_value ?? 0) >= ($g->target_value ?? 1))->count();
        return $achieved / $goals->count();
    }

    /**
     * Calculate score trend over time.
     */
    protected function calculateScoreTrend(Client $client): string
    {
        $history = DB::table('client_health_scores')
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->pluck('total_score');

        if ($history->count() < 2) {
            return 'stable';
        }

        $first = $history->first();
        $last = $history->last();
        $diff = $last - $first;

        if ($diff > 5) return 'improving';
        if ($diff < -5) return 'declining';
        return 'stable';
    }

    /**
     * Convert numeric score to letter grade.
     */
    protected function scoreToGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    /**
     * Update client health score in database.
     */
    protected function updateClientHealthScore(Client $client, array $healthData): void
    {
        DB::table('client_health_scores')->insert([
            'client_id' => $client->id,
            'total_score' => $healthData['total_score'],
            'factors' => json_encode($healthData['factors']),
            'grade' => $healthData['grade'],
            'trend' => $healthData['trend'],
            'created_at' => now(),
        ]);

        // Also update client model
        $client->update([
            'health_score' => $healthData['total_score'],
            'health_grade' => $healthData['grade'],
            'health_trend' => $healthData['trend'],
            'health_last_calculated' => now(),
        ]);
    }

    /**
     * Assess churn risk based on health score and other factors.
     */
    protected function assessChurnRisk(Client $client, array $healthData): float
    {
        $baseRisk = (100 - $healthData['total_score']) / 100;

        // Adjust based on trend
        $trendMultiplier = match ($healthData['trend']) {
            'declining' => 1.5,
            'improving' => 0.7,
            default => 1.0,
        };

        // Adjust based on contract status
        $contractRisk = 0;
        if ($client->contract_end_date && $client->contract_end_date->lt(now()->addDays(60))) {
            $contractRisk = 0.2;
        }

        // Adjust based on recent support tickets
        $openTickets = DB::table('support_tickets')
            ->where('client_id', $client->id)
            ->where('status', 'open')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        $ticketRisk = min(0.2, $openTickets * 0.05);

        $totalRisk = ($baseRisk * $trendMultiplier) + $contractRisk + $ticketRisk;
        return min(1.0, max(0.0, $totalRisk));
    }

    /**
     * Create churn risk alert.
     */
    protected function createChurnAlert(Client $client, float $risk): void
    {
        DB::table('client_alerts')->insert([
            'client_id' => $client->id,
            'type' => 'churn_risk',
            'severity' => $risk > 0.85 ? 'critical' : 'high',
            'message' => "Client {$client->name} has high churn risk ({$risk * 100}%)",
            'metadata' => json_encode(['risk_score' => $risk]),
            'created_at' => now(),
        ]);

        // Notify account manager
        if ($client->accountManager) {
            $client->accountManager->notify(new \App\Notifications\ClientChurnRisk($client, $risk));
        }
    }

    /**
     * Identify upsell opportunities.
     */
    protected function identifyUpsellOpportunities(Client $client): array
    {
        $opportunities = [];

        // Check for campaign expansion opportunities
        $activeCampaigns = $client->campaigns()->where('status', 'active')->count();
        if ($activeCampaigns < 3 && $client->monthly_budget > 5000) {
            $opportunities[] = [
                'type' => 'campaign_expansion',
                'description' => 'Client has budget for more campaigns but only running ' . $activeCampaigns,
                'estimated_value' => $client->monthly_budget * 0.2,
                'confidence' => 0.8,
            ];
        }

        // Check for platform expansion
        $platforms = $client->campaigns()->where('status', 'active')->pluck('platform')->unique();
        $allPlatforms = ['meta', 'google', 'linkedin', 'tiktok'];
        $missingPlatforms = array_diff($allPlatforms, $platforms->toArray());
        
        if (!empty($missingPlatforms)) {
            $opportunities[] = [
                'type' => 'platform_expansion',
                'description' => 'Expand to untapped platforms: ' . implode(', ', $missingPlatforms),
                'estimated_value' => $client->monthly_budget * 0.15 * count($missingPlatforms),
                'confidence' => 0.6,
            ];
        }

        // Check for service upgrades
        $currentServices = $client->services ?? [];
        if (!in_array('creative_production', $currentServices) && $client->monthly_budget > 10000) {
            $opportunities[] = [
                'type' => 'service_upgrade',
                'description' => 'Add creative production services for better ad performance',
                'estimated_value' => 2000,
                'confidence' => 0.7,
            ];
        }

        return $opportunities;
    }

    /**
     * Check if report should be generated.
     */
    protected function shouldGenerateReport(Client $client): bool
    {
        $frequency = $this->config['report_frequency'] ?? 'weekly';
        $lastReport = DB::table('client_reports')
            ->where('client_id', $client->id)
            ->orderBy('generated_at', 'desc')
            ->value('generated_at');

        if (!$lastReport) {
            return true;
        }

        return match ($frequency) {
            'daily' => Carbon::parse($lastReport)->lt(now()->subDay()),
            'weekly' => Carbon::parse($lastReport)->lt(now()->subWeek()),
            'monthly' => Carbon::parse($lastReport)->lt(now()->subMonth()),
            default => false,
        };
    }

    /**
     * Generate client report.
     */
    protected function generateClientReport(Client $client): void
    {
        // Generate and store report
        DB::table('client_reports')->insert([
            'client_id' => $client->id,
            'report_type' => 'scheduled',
            'data' => json_encode([
                'health_score' => $client->health_score,
                'campaigns' => $client->campaigns()->where('status', 'active')->get()->toArray(),
                'performance_summary' => $this->getPerformanceSummary($client),
            ]),
            'generated_at' => now(),
        ]);

        // Notify client and account manager
        if ($client->accountManager) {
            $client->accountManager->notify(new \App\Notifications\ClientReportReady($client));
        }
    }

    protected function getPerformanceSummary(Client $client): array
    {
        $campaigns = $client->campaigns()->where('status', 'active')->get();
        
        return [
            'total_spend' => $campaigns->sum('spent'),
            'total_conversions' => $campaigns->sum('conversions'),
            'avg_roas' => $campaigns->avg('roas'),
            'avg_cpa' => $campaigns->avg('cpa'),
            'active_campaigns' => $campaigns->count(),
        ];
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'health_score_weights' => [
                'engagement' => 0.25,
                'performance' => 0.30,
                'communication' => 0.20,
                'budget_utilization' => 0.15,
                'satisfaction' => 0.10,
            ],
            'churn_risk_threshold' => 0.7,
            'upsell_min_roi' => 2.0,
            'report_frequency' => 'weekly',
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
