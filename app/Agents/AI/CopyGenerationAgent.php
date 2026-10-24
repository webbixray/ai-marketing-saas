<?php

namespace App\Agents\AI;

use App\Agents\BaseAgent;
use App\Domain\Agent\AgentCapability;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\CreativeAsset;
use App\Services\AI\LLMService;
use Illuminate\Support\Facades\Log;

class CopyGenerationAgent extends BaseAgent
{
    protected LLMService $llm;

    public function __construct(LLMService $llm)
    {
        $this->llm = $llm;
    }

    public function getId(): string
    {
        return 'copy.generation';
    }

    public function getName(): string
    {
        return 'AI Copy Generation Agent';
    }

    public function getDescription(): string
    {
        return 'Generates high-converting ad copy, headlines, CTAs, and creative concepts using LLM';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CREATIVE_VARIANT_GENERATE,
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
            'default_variants_per_request' => [
                'type' => 'integer',
                'required' => false,
                'default' => 5,
            ],
            'include_emotional_triggers' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'include_a_b_test_pairs' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'platform_optimization' => [
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

        $campaignId = $payload['campaign_id'] ?? null;
        $creativeId = $payload['creative_id'] ?? null;
        $clientId = $payload['client_id'] ?? null;
        $platform = $payload['platform'] ?? 'meta';
        $objective = $payload['objective'] ?? 'conversions';
        $targetAudience = $payload['target_audience'] ?? [];
        $productInfo = $payload['product_info'] ?? [];
        $variants = $payload['variants'] ?? ($this->config['default_variants_per_request'] ?? 5);
        $copyTypes = $payload['copy_types'] ?? ['headline', 'body', 'cta', 'description'];

        // Load context
        $campaign = $campaignId ? Campaign::find($campaignId) : null;
        $creative = $creativeId ? CreativeAsset::find($creativeId) : null;
        $client = $clientId ? Client::find($clientId) : ($campaign?->client ?? $creative?->client);

        $results = [];

        foreach ($copyTypes as $copyType) {
            $copies = $this->generateCopy(
                $copyType,
                $platform,
                $objective,
                $targetAudience,
                $productInfo,
                $campaign,
                $creative,
                $client,
                $variants
            );

            if (! empty($copies)) {
                $results[$copyType] = $copies;
            }
        }

        // Generate A/B test pairs if enabled
        if ($this->config['include_a_b_test_pairs']) {
            $results['ab_test_pairs'] = $this->generateAbTestPairs($results);
        }

        // Generate emotional trigger analysis if enabled
        if ($this->config['include_emotional_triggers']) {
            $results['emotional_analysis'] = $this->analyzeEmotionalTriggers($results);
        }

        return AgentResult::success($results, [
            'copy_types_generated' => array_keys($results),
            'total_variants' => array_sum(array_map('count', array_filter($results, fn ($v) => is_array($v)))),
        ])->withNextAction('save_copy_variants', [
            'campaign_id' => $campaignId,
            'creative_id' => $creativeId,
        ]);
    }

    /**
     * Generate copy for a specific type.
     */
    protected function generateCopy(
        string $copyType,
        string $platform,
        string $objective,
        array $targetAudience,
        array $productInfo,
        ?Campaign $campaign,
        ?CreativeAsset $creative,
        ?Client $client,
        int $variants
    ): array {
        $prompt = $this->buildCopyPrompt($copyType, $platform, $objective, $targetAudience, $productInfo, $campaign, $creative, $client, $variants);

        $schema = $this->getCopySchema($copyType, $variants);

        $result = $this->llm->completeJson($prompt, $schema, [
            'provider' => $this->config['llm_provider'] ?? 'openai',
            'model' => $this->config['llm_model'] ?? 'gpt-4-turbo',
            'temperature' => 0.7,
            'system_prompt' => $this->getCopySystemPrompt($copyType, $platform),
        ]);

        if (isset($result['error'])) {
            Log::error("Copy generation failed for {$copyType}", ['error' => $result['error']]);

            return [];
        }

        return $result['content'][$copyType] ?? [];
    }

    /**
     * Build prompt for copy generation.
     */
    protected function buildCopyPrompt(
        string $copyType,
        string $platform,
        string $objective,
        array $targetAudience,
        array $productInfo,
        ?Campaign $campaign,
        ?CreativeAsset $creative,
        ?Client $client,
        int $variants
    ): string {
        $context = "
Platform: {$platform}
Objective: {$objective}
Variants needed: {$variants}
Copy type: {$copyType}
";

        if ($campaign) {
            $context .= "
Campaign: {$campaign->name}
Campaign Objective: {$campaign->objective}
Campaign Type: {$campaign->type}
Campaign Platform: {$campaign->platform}
";
        }

        if ($creative) {
            $context .= "
Creative: {$creative->name}
Creative Elements: ".json_encode($creative->elements !== null ? $creative->elements : []).'
';
        }

        if ($client) {
            $context .= "
Client: {$client->name}
Industry: {$client->industry_var}
Brand Voice: ".json_encode($client->branding_var).'
';
        }

        if (! empty($targetAudience)) {
            $context .= '
Target Audience: '.json_encode($targetAudience).'
';
        }

        if (! empty($productInfo)) {
            $context .= '
Product/Service: '.json_encode($productInfo).'
';
        }

        $instructions = $this->getCopyInstructions($copyType, $platform, $objective);

        return "
You are an expert direct-response copywriter specializing in {$platform} advertising. Write compelling, high-converting ad copy.

{$context}

{$instructions}

Generate exactly {$variants} variants for {$copyType}. Each variant should be distinct in angle, tone, or approach.

Return as JSON with this structure:
{
  \"{$copyType}\": [
    {
      \"text\": \"string\",
      \"angle\": \"string\",
      \"emotional_trigger\": \"string\",
      \"character_count\": integer,
      \"platform_optimized\": boolean,
      \"ab_test_group\": \"A|B\"
    }
  ]
}
";
    }

    /**
     * Get system prompt for copy generation.
     */
    protected function getCopySystemPrompt(string $copyType, string $platform): string
    {
        $basePrompt = "You are an expert direct-response copywriter with 20+ years of experience writing high-converting ad copy for {$platform}. You understand platform-specific constraints, audience psychology, and conversion optimization. You write copy that stops the scroll, creates desire, and drives action.";

        $typePrompts = [
            'headline' => 'You specialize in headlines that stop the scroll. You know the 3-second rule. You write headlines that promise value, create curiosity, or trigger emotion in under 40 characters.',
            'body' => 'You write persuasive body copy that builds desire, overcomes objections, and leads to action. You know the PAS, AIDA, and BAB frameworks. You write for scanning, not reading.',
            'cta' => "You write calls-to-action that convert. You know the difference between 'Learn More' and 'Get My Free Guide'. You write CTAs that reduce friction and increase urgency.",
            'description' => 'You write platform-optimized descriptions that complement headlines and drive clicks. You know character limits, formatting options, and what works in each placement.',
        ];

        return $basePrompt."\n\n".($typePrompts[$copyType] ?? '');
    }

    /**
     * Get copy generation instructions.
     */
    protected function getCopyInstructions(string $copyType, string $platform, string $objective): string
    {
        $platformRules = [
            'meta' => 'Facebook/Instagram: Headlines ≤40 chars, Primary text ≤125 chars, Description ≤30 chars. Use emojis. Test 2-3 headlines.',
            'google' => 'Google Ads: Headlines ≤30 chars (3 max), Descriptions ≤90 chars (2 max). Use keywords. Include USP.',
            'linkedin' => 'LinkedIn: Headlines ≤200 chars, Intro text ≤600 chars. Professional tone. B2B focus.',
            'tiktok' => 'TikTok: Captions ≤150 chars. Native, authentic tone. Hook in first 3 seconds. Hashtags.',
        ];

        $typeInstructions = [
            'headline' => "Write {$variants} distinct headlines. Each must: 1) Grab attention in 3 seconds, 2) Promise specific value, 3) Fit platform character limits, 4) Use different angles (benefit, curiosity, urgency, social proof, question).",
            'body' => "Write {$variants} body copy variants. Each must: 1) Hook in first sentence, 2) Build desire with benefits not features, 3) Include social proof, 4) Overcome top objection, 5) Lead to CTA naturally.",
            'cta' => "Write {$variants} CTAs. Each must: 1) Start with strong verb, 2) Communicate value, 3) Create urgency or scarcity, 4) Reduce friction, 3-5 words max.",
            'description' => "Write {$variants} descriptions. Each must: 1) Support headline, 2) Add key benefit, 3) Include trust signal, 4) Fit character limits.",
        ];

        $variants = $this->config['default_variants_per_request'] ?? 5;

        return "
Copy Type: {$copyType}
Platform: {$platform}
Objective: {$objective}

$platform_rules_var = \$platformRules[\$platform] ?? 'Follow platform best practices';\n
Platform Rules: {$platform_rules_var}

Instructions: {$typeInstructions_var[$copyType]}
$typeInstructions_var = $typeInstructions;

Additional Requirements:
- Include character count for each variant
- Identify emotional trigger (fear, greed, curiosity, belonging, pride, urgency, etc.)
- Mark A/B test group (A or B) for testing
- Flag platform_optimized: true if follows platform best practices
";
    }

    /**
     * Get JSON schema for copy generation.
     */
    protected function getCopySchema(string $copyType, int $variants): array
    {
        return [
            'type' => 'object',
            'properties' => [
                $copyType => [
                    'type' => 'array',
                    'minItems' => $variants,
                    'maxItems' => $variants,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'angle' => ['type' => 'string'],
                            'emotional_trigger' => ['type' => 'string'],
                            'character_count' => ['type' => 'integer'],
                            'platform_optimized' => ['type' => 'boolean'],
                            'ab_test_group' => ['type' => 'string', 'enum' => ['A', 'B']],
                        ],
                        'required' => ['text', 'angle', 'emotional_trigger', 'character_count', 'platform_optimized', 'ab_test_group'],
                    ],
                ],
            ],
            'required' => [$copyType],
        ];
    }

    /**
     * Generate A/B test pairs from generated copies.
     */
    protected function generateAbTestPairs(array $copies): array
    {
        $pairs = [];

        foreach ($copies as $type => $variants) {
            if (! is_array($variants)) {
                continue;
            }

            $groupA = array_filter($variants, fn ($v) => ($v['ab_test_group'] ?? 'A') === 'A');
            $groupB = array_filter($variants, fn ($v) => ($v['ab_test_group'] ?? 'B') === 'B');

            // Pair them up
            $count = min(count($groupA), count($groupB));
            for ($i = 0; $i < $count; $i++) {
                $pairs[] = [
                    'copy_type' => $type,
                    'variant_a' => array_values($groupA)[$i] ?? null,
                    'variant_b' => array_values($groupB)[$i] ?? null,
                    'test_hypothesis' => $this->generateHypothesis($type, array_values($groupA)[$i] ?? [], array_values($groupB)[$i] ?? []),
                ];
            }
        }

        return $pairs;
    }

    /**
     * Generate hypothesis for A/B test.
     */
    protected function generateHypothesis(string $type, array $variantA, array $variantB): string
    {
        $angleA = $variantA['angle'] ?? 'Angle A';
        $angleB = $variantB['angle'] ?? 'Angle B';
        $triggerA = $variantA['emotional_trigger'] ?? 'trigger A';
        $triggerB = $variantB['emotional_trigger'] ?? 'trigger B';

        return "Testing {$angleA} ({$triggerA}) vs {$angleB} ({$triggerB}) for {$type}. Hypothesis: Different emotional triggers will produce different conversion rates.";
    }

    /**
     * Analyze emotional triggers in generated copy.
     */
    protected function analyzeEmotionalTriggers(array $results): array
    {
        $triggers = [];
        $totalVariants = 0;

        foreach ($results as $type => $variants) {
            if (! is_array($variants)) {
                continue;
            }

            foreach ($variants as $variant) {
                $trigger = $variant['emotional_trigger'] ?? 'unknown';
                $triggers[$trigger] = ($triggers[$trigger] ?? 0) + 1;
                $totalVariants++;
            }
        }

        // Calculate percentages
        $analysis = [];
        foreach ($triggers as $trigger => $count) {
            $analysis[$trigger] = [
                'count' => $count,
                'percentage' => round(($count / max(1, $totalVariants)) * 100, 1),
            ];
        }

        // Identify dominant triggers
        arsort($analysis);
        $dominant = array_slice($analysis, 0, 3, true);

        return [
            'trigger_distribution' => $analysis,
            'dominant_triggers' => array_keys($dominant),
            'total_variants_analyzed' => $totalVariants,
            'recommendation' => $this->getTriggerRecommendation($dominant),
        ];
    }

    /**
     * Get recommendation based on emotional triggers.
     */
    protected function getTriggerRecommendation(array $dominant): string
    {
        $topTrigger = array_key_first($dominant);

        $recommendations = [
            'fear' => 'Consider balancing fear-based copy with hope/relief to avoid ad fatigue',
            'urgency' => 'Urgency works well for limited-time offers but can cause burnout if overused',
            'curiosity' => 'Curiosity gaps work well for top-of-funnel; pair with clear value prop',
            'greed' => 'Value-focused copy performs well for consideration stage',
            'belonging' => 'Community/social proof works for brand building',
            'pride' => 'Achievement-oriented copy works for premium/high-ticket',
            'hope' => 'Aspirational copy works for transformation products',
        ];

        return $recommendations[$topTrigger] ?? 'Balanced emotional mix recommended for broad appeal';
    }

    /**
     * Get platform character limits.
     */
    protected function getCharacterLimits(string $platform, string $copyType): array
    {
        return match ($platform) {
            'meta' => match ($copyType) {
                'headline' => ['min' => 5, 'max' => 40, 'ideal' => 25],
                'body' => ['min' => 10, 'max' => 125, 'ideal' => 90],
                'cta' => ['min' => 2, 'max' => 25, 'ideal' => 15],
                'description' => ['min' => 5, 'max' => 30, 'ideal' => 20],
                default => ['min' => 1, 'max' => 200, 'ideal' => 100],
            },
            'google' => match ($copyType) {
                'headline' => ['min' => 5, 'max' => 30, 'ideal' => 25],
                'body' => ['min' => 10, 'max' => 90, 'ideal' => 80],
                default => ['min' => 1, 'max' => 90, 'ideal' => 60],
            },
            'linkedin' => match ($copyType) {
                'headline' => ['min' => 10, 'max' => 200, 'ideal' => 150],
                'body' => ['min' => 20, 'max' => 600, 'ideal' => 400],
                default => ['min' => 1, 'max' => 600, 'ideal' => 300],
            },
            'tiktok' => match ($copyType) {
                'body' => ['min' => 10, 'max' => 150, 'ideal' => 100],
                default => ['min' => 1, 'max' => 150, 'ideal' => 80],
            },
            default => ['min' => 1, 'max' => 200, 'ideal' => 100],
        };
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'default_variants_per_request' => 5,
            'include_emotional_triggers' => true,
            'include_a_b_test_pairs' => true,
            'platform_optimization' => true,
        ], $config);
    }

    protected function onShutdown(): void {}
}
