<?php

namespace App\Agents\Intelligence;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreativeIntelligenceAgent extends BaseAgent
{
    public function getId(): string
    {
        return 'creative.intelligence';
    }

    public function getName(): string
    {
        return 'Creative Intelligence Agent';
    }

    public function getDescription(): string
    {
        return 'Analyzes creative performance, detects fatigue, generates variant recommendations, and manages creative asset lifecycle';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CREATIVE_PERFORMANCE_SCORE,
            AgentCapability::CREATIVE_FATIGUE_DETECT,
            AgentCapability::CREATIVE_VARIANT_GENERATE,
            AgentCapability::CREATIVE_ASSET_MANAGE,
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'fatigue_threshold_frequency' => [
                'type' => 'number',
                'required' => false,
                'default' => 3.0,
            ],
            'fatigue_threshold_ctr_decline' => [
                'type' => 'number',
                'required' => false,
                'default' => 0.3, // 30% decline
            ],
            'variant_test_min_impressions' => [
                'type' => 'integer',
                'required' => false,
                'default' => 1000,
            ],
            'auto_generate_variants' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false,
            ],
        ];
    }

    protected function performExecution(AgentContext $context): AgentResult
    {
        $tenant = $context->tenant;
        $results = [
            'creatives_analyzed' => 0,
            'fatigue_detected' => 0,
            'variants_generated' => 0,
            'assets_archived' => 0,
            'recommendations' => [],
        ];

        // Get all active creatives for tenant
        $creatives = DB::table('creative_assets')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();

        foreach ($creatives as $creative) {
            try {
                $results['creatives_analyzed']++;

                // Score creative performance
                $performanceScore = $this->scoreCreativePerformance($creative);
                
                // Detect fatigue
                $fatigue = $this->detectFatigue($creative);
                if ($fatigue['is_fatigued']) {
                    $results['fatigue_detected']++;
                    $results['recommendations'][] = [
                        'creative_id' => $creative->id,
                        'type' => 'fatigue_alert',
                        'severity' => $fatigue['severity'],
                        'message' => $fatigue['message'],
                        'recommended_action' => 'Pause and replace with fresh creative',
                    ];
                }

                // Generate variant recommendations
                if ($performanceScore > 70 && !$fatigue['is_fatigued']) {
                    $variants = $this->generateVariantRecommendations($creative);
                    $results['variants_generated'] += count($variants);
                    foreach ($variants as $variant) {
                        $results['recommendations'][] = [
                            'creative_id' => $creative->id,
                            'type' => 'variant_suggestion',
                            'variant' => $variant,
                        ];
                    }
                }

                // Archive old unused assets
                if ($this->shouldArchive($creative)) {
                    $this->archiveCreative($creative);
                    $results['assets_archived']++;
                }

            } catch (\Throwable $e) {
                Log::error("Creative analysis failed", [
                    'creative_id' => $creative->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return AgentResult::success($results, [
            'creatives_processed' => $results['creatives_analyzed'],
            'fatigue_alerts' => $results['fatigue_detected'],
        ]);
    }

    /**
     * Score creative performance (0-100).
     */
    protected function scoreCreativePerformance($creative): float
    {
        $metrics = json_decode($creative->performance_metrics ?? '{}', true);
        
        $ctr = $metrics['ctr'] ?? 0;
        $cvr = $metrics['cvr'] ?? 0;
        $roas = $metrics['roas'] ?? 0;
        $impressions = $metrics['impressions'] ?? 0;

        // Weight components
        $ctrScore = min(100, ($ctr / 2.0) * 100); // 2% CTR = 100
        $cvrScore = min(100, ($cvr / 5.0) * 100); // 5% CVR = 100
        $roasScore = min(100, ($roas / 4.0) * 100); // 4.0 ROAS = 100
        
        // Volume bonus
        $volumeScore = min(100, ($impressions / 100000) * 100); // 100k impressions = 100

        return round(($ctrScore * 0.3 + $cvrScore * 0.3 + $roasScore * 0.3 + $volumeScore * 0.1), 2);
    }

    /**
     * Detect creative fatigue.
     */
    protected function detectFatigue($creative): array
    {
        $frequency = $creative->frequency ?? 0;
        $ctrTrend = $this->getCtrTrend($creative->id);
        $frequencyThreshold = $this->config['fatigue_threshold_frequency'] ?? 3.0;
        $ctrDeclineThreshold = $this->config['fatigue_threshold_ctr_decline'] ?? 0.3;

        $isFatigued = false;
        $severity = 'low';
        $reasons = [];

        // Frequency-based fatigue
        if ($frequency > $frequencyThreshold) {
            $isFatigued = true;
            $severity = 'medium';
            $reasons[] = "Frequency ({$frequency}) exceeds threshold ({$frequencyThreshold})";
        }

        // CTR decline fatigue
        if ($ctrTrend < -$ctrDeclineThreshold) {
            $isFatigued = true;
            $severity = 'high';
            $reasons[] = "CTR declined by " . abs($ctrTrend) * 100 . "%";
        }

        // Time-based fatigue (creative older than 60 days with declining performance)
        $age = \Carbon\Carbon::parse($creative->created_at)->diffInDays(now());
        if ($age > 60 && $ctrTrend < -0.1) {
            $isFatigued = true;
            $severity = 'high';
            $reasons[] = "Creative age: {$age} days with declining CTR";
        }

        return [
            'is_fatigued' => $isFatigued,
            'severity' => $severity,
            'reasons' => $reasons,
            'frequency' => $frequency,
            'ctr_trend' => $ctrTrend,
            'age_days' => $age,
            'message' => $isFatigued ? implode('; ', $reasons) : 'No fatigue detected',
        ];
    }

    /**
     * Get CTR trend over last 14 days.
     */
    protected function getCtrTrend(int $creativeId): float
    {
        $data = DB::table('creative_performance_daily')
            ->where('creative_id', $creativeId)
            ->where('date', '>=', now()->subDays(14))
            ->orderBy('date', 'asc')
            ->pluck('ctr');

        if ($data->count() < 3) {
            return 0;
        }

        $firstHalf = $data->slice(0, (int)($data->count() / 2))->avg();
        $secondHalf = $data->slice((int)($data->count() / 2))->avg();

        if ($firstHalf == 0) {
            return 0;
        }

        return ($secondHalf - $firstHalf) / $firstHalf;
    }

    /**
     * Generate variant recommendations for high-performing creatives.
     */
    protected function generateVariantRecommendations($creative): array
    {
        $variants = [];
        $elements = json_decode($creative->elements ?? '[]', true);

        // Headline variants
        if (!empty($elements['headline'])) {
            $variants[] = [
                'type' => 'headline_variant',
                'original' => $elements['headline'],
                'suggestions' => $this->generateHeadlineVariants($elements['headline']),
                'test_type' => 'A/B',
            ];
        }

        // Image/Video variants
        if (!empty($elements['media_url'])) {
            $variants[] = [
                'type' => 'media_variant',
                'original' => $elements['media_url'],
                'suggestions' => [
                    'Try different color scheme',
                    'Test with people vs product only',
                    'Try carousel vs single image',
                    'Test video vs static',
                ],
                'test_type' => 'multivariate',
            ];
        }

        // CTA variants
        if (!empty($elements['cta'])) {
            $variants[] = [
                'type' => 'cta_variant',
                'original' => $elements['cta'],
                'suggestions' => $this->generateCtaVariants($elements['cta']),
                'test_type' => 'A/B',
            ];
        }

        // Audience variants
        $variants[] = [
            'type' => 'audience_variant',
            'description' => 'Test with lookalike audience expansion',
            'test_type' => 'audience_split',
        ];

        return $variants;
    }

    protected function generateHeadlineVariants(string $headline): array
    {
        $variants = [];
        
        // Benefit-focused
        $variants[] = "Transform Your {$headline}";
        
        // Question format
        $variants[] = "Ready to {$headline}?";
        
        // Urgency
        $variants[] = "Start {$headline} Today";
        
        // Social proof
        $variants[] = "Join 10,000+ Who {$headline}";
        
        // Benefit + urgency
        $variants[] = "Get Results: {$headline} in 30 Days";

        return $variants;
    }

    protected function generateCtaVariants(string $cta): array
    {
        $actionVerbs = ['Get', 'Start', 'Join', 'Discover', 'Learn', 'Try', 'Claim'];
        $variants = [];

        foreach ($actionVerbs as $verb) {
            $variants[] = "{$verb} {$cta}";
        }

        // Value-focused
        $variants[] = "Get Free {$cta}";
        $variants[] = "Start Free Trial";
        $variants[] = "See How It Works";

        return $variants;
    }

    /**
     * Check if creative should be archived.
     */
    protected function shouldArchive($creative): bool
    {
        $age = \Carbon\Carbon::parse($creative->created_at)->diffInDays(now());
        $lastImpression = DB::table('creative_performance_daily')
            ->where('creative_id', $creative->id)
            ->orderBy('date', 'desc')
            ->value('date');

        // Archive if: older than 90 days AND no impressions in 30 days
        if ($age > 90) {
            if ($lastImpression && \Carbon\Carbon::parse($lastImpression)->lt(now()->subDays(30))) {
                return true;
            }
        }

        // Archive if explicitly marked for archival
        if (($creative->metadata['archive_requested'] ?? false) === true) {
            return true;
        }

        return false;
    }

    protected function archiveCreative($creative): void
    {
        DB::table('creative_assets')
            ->where('id', $creative->id)
            ->update([
                'status' => 'archived',
                'archived_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($creative->metadata ?? '[]', true),
                    ['archived_reason' => 'auto_archived', 'archived_by' => 'creative_intelligence_agent']
                )),
            ]);
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'fatigue_threshold_frequency' => 3.0,
            'fatigue_threshold_ctr_decline' => 0.3,
            'variant_test_min_impressions' => 1000,
            'auto_generate_variants' => false,
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
