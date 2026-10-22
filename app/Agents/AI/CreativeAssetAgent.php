<?php

namespace App\Agents\AI;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Services\AI\LLMService;
use App\Models\Campaign;
use App\Models\CreativeAsset;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreativeAssetAgent extends BaseAgent
{
    protected LLMService $llm;

    public function __construct(LLMService $llm)
    {
        $this->llm = $llm;
    }

    public function getId(): string
    {
        return 'creative.asset';
    }

    public function getName(): string
    {
        return 'AI Creative Asset Generator';
    }

    public function getDescription(): string
    {
        return 'Generates ad creative concepts, prompts for DALL-E/Midjourney, and manages creative asset lifecycle';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CREATIVE_VARIANT_GENERATE,
            AgentCapability::CREATIVE_ASSET_MANAGE,
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
            'image_provider' => [
                'type' => 'string',
                'required' => false,
                'default' => 'dalle',
            ],
            'image_model' => [
                'type' => 'string',
                'required' => false,
                'default' => 'dall-e-3',
            ],
            'default_image_size' => [
                'type' => 'string',
                'required' => false,
                'default' => '1024x1024',
            ],
            'default_quality' => [
                'type' => 'string',
                'required' => false,
                'default' => 'standard',
            ],
            'auto_save_assets' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'generate_variants' => [
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
        $brief = $payload['brief'] ?? '';
        $concepts = $payload['concepts'] ?? ($this->config['generate_variants'] ? 4 : 1);
        $imageSize = $payload['image_size'] ?? ($this->config['default_image_size'] ?? '1024x1024');
        $quality = $payload['quality'] ?? ($this->config['default_quality'] ?? 'standard');
        $style = $payload['style'] ?? 'photorealistic';

        if (empty($brief)) {
            return AgentResult::failure('Creative brief is required');
        }

        // Load context
        $campaign = $campaignId ? Campaign::find($campaignId) : null;
        $client = $clientId ? \App\Models\Client::find($clientId) : ($campaign?->client ?? null);

        // Generate creative concepts
        $concepts = $this->generateConcepts($brief, $campaign, $client, $payload, $concepts);

        if (empty($concepts)) {
            return AgentResult::failure('Failed to generate creative concepts');
        }

        // Generate image prompts for each concept
        $promptResults = [];
        foreach ($concepts as $index => $concept) {
            $prompt = $this->buildImagePrompt($concept, $payload['style'] ?? 'photorealistic');
            
            // Generate image using DALL-E
            $imageResult = $this->llm->generateImage($prompt, [
                'provider' => $this->config['image_provider'] ?? 'dalle',
                'model' => $this->config['image_model'] ?? 'dall-e-3',
                'size' => $payload['image_size'] ?? $this->config['default_image_size'] ?? '1024x1024',
                'quality' => $payload['quality'] ?? $this->config['default_quality'] ?? 'standard',
                'n' => 1,
            ]);

            $promptResults[] = [
                'concept_index' => $index,
                'concept' => $concept,
                'prompt' => $prompt,
                'image_result' => $imageResult,
            ];
        }

        // Save creative assets if enabled
        $savedAssets = [];
        if ($this->config['auto_save_assets']) {
            foreach ($promptResults as $result) {
                if (!empty($result['image_result']['images'])) {
                    foreach ($result['image_result']['images'] as $imageUrl) {
                        $asset = $this->saveCreativeAsset($result, $tenant, $campaign, $client, $imageUrl);
                        if ($asset) {
                            $savedAssets[] = $asset;
                        }
                    }
                }
            }
        }

        return AgentResult::success([
            'concepts_generated' => count($concepts),
            'concepts' => $concepts,
            'prompt_results' => $promptResults,
            'saved_assets' => $savedAssets,
            'total_images' => count($savedAssets),
        ], [
            'concepts_generated' => count($concepts),
            'images_generated' => count($savedAssets),
        ])->withNextAction('review_creatives', [
            'asset_ids' => collect($savedAssets)->pluck('id')->toArray(),
        ]);
    }

    /**
     * Generate creative concepts from brief.
     */
    protected function generateConcepts(string $brief, ?Campaign $campaign, ?Client $client, array $payload, int $count): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'concepts' => [
                    'type' => 'array',
                    'minItems' => $count,
                    'maxItems' => $count,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'visual_concept' => ['type' => 'string'],
                            'style' => ['type' => 'string'],
                            'color_palette' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'composition' => ['type' => 'string'],
                            'key_elements' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'mood' => ['type' => 'string'],
                            'text_overlay' => ['type' => 'string'],
                            'aspect_ratio' => ['type' => 'string'],
                            'platform_optimization' => ['type' => 'object'],
                        ],
                        'required' => ['title', 'description', 'visual_concept', 'style'],
                    ],
                ],
            ],
            'required' => ['concepts'],
        ];

        $prompt = $this->buildConceptPrompt($brief, $campaign, $client, $payload, $count);

        $result = $this->llm->completeJson($prompt, $schema, [
            'provider' => $this->config['llm_provider'] ?? 'openai',
            'model' => $this->config['llm_model'] ?? 'gpt-4-turbo',
            'temperature' => 0.8,
            'system_prompt' => $this->getConceptSystemPrompt(),
        ]);

        if (isset($result['error'])) {
            Log::error('Concept generation failed', ['error' => $result['error']]);
            return [];
        }

        return $result['content']['concepts'] ?? [];
    }

    /**
     * Build prompt for concept generation.
     */
    protected function buildConceptPrompt(string $brief, ?Campaign $campaign, ?Client $client, array $payload, int $count): string
    {
        $context = "
Creative Brief: {$brief}

Number of Concepts: {$count}
";

        if ($campaign) {
            $context .= "
Campaign: {$campaign->name}
Platform: {$campaign->platform}
Objective: {$campaign->objective}
Target Audience: " . json_encode($campaign->audience_targeting ?? []) . "
";
        }

        if ($client) {
            $context .= "
Client: {$client->name}
Industry: {$client->industry ?? 'Not specified'}
Brand Guidelines: " . json_encode($client->branding ?? []) . "
";
        }

        if (!empty($payload['product_info'])) {
            $context .= "
Product/Service: " . json_encode($payload['product_info']) . "
";
        }

        if (!empty($payload['target_audience'])) {
            $context .= "
Target Audience: " . json_encode($payload['target_audience']) . "
";
        }

        if (!empty($payload['style'])) {
            $context .= "
Preferred Style: {$payload['style']}
";
        }

        return "
You are an award-winning creative director and visual strategist. Create {$count} distinct visual concepts for ad creatives.

Brief: {$brief}

{$context}

Each concept should be distinct in visual approach, targeting different psychological triggers or audience segments.

Consider:
1. Platform-specific best practices (Meta: 1:1 or 9:16, Google: 1:1/4:5, TikTok: 9:16)
2. Platform-specific creative formats (Meta: carousel, single image, video; Google: responsive display; TikTok: native video)
3. Brand consistency with client guidelines
4. Platform-specific creative formats and specs
5. Conversion psychology (attention → interest → desire → action)
6. Visual hierarchy and reading patterns
7. Color psychology and brand alignment
8. Mobile-first design (majority of traffic)

Return as JSON with this structure:
{
  \"concepts\": [
    {
      \"title\": \"string\",
      \"description\": \"string\",
      \"visual_concept\": \"string\",
      \"style\": \"string\",
      \"color_palette\": [\"hex1\", \"hex2\", \"hex3\"],
      \"composition\": \"string\",
      \"key_elements\": [\"element1\", \"element2\", \"element3\"],
      \"mood\": \"string\",
      \"text_overlay\": \"string\",
      \"aspect_ratio\": \"1:1|9:16|4:5|16:9\",
      \"platform_optimization\": {\"meta\": {}, \"google\": {}, \"tiktok\": {}}
    }
  ]
}
";
    }

    /**
     * Get system prompt for concept generation.
     */
    protected function getConceptSystemPrompt(): string
    {
        return "You are an award-winning Creative Director with expertise in digital advertising across Meta, Google, TikTok, LinkedIn, and programmatic. You create visually compelling, conversion-optimized ad concepts that balance brand aesthetics with performance marketing. You understand platform-specific creative formats, color psychology, visual hierarchy, and the science of attention. You think in terms of visual storytelling that drives measurable action.";
    }

    /**
     * Build DALL-E prompt from concept.
     */
    protected function buildImagePrompt(array $concept, string $style): string
    {
        $promptParts = [];

        // Main visual concept
        $promptParts[] = $concept['visual_concept'] ?? '';

        // Style
        $styleMap = [
            'photorealistic' => 'photorealistic, ultra-high definition, 8k, professional photography',
            'illustration' => 'digital illustration, clean vector art, modern flat design',
            'minimalist' => 'minimalist design, clean lines, negative space, simple composition',
            'abstract' => 'abstract art, geometric shapes, artistic interpretation',
            'cinematic' => 'cinematic lighting, dramatic composition, movie still aesthetic',
            'lifestyle' => 'lifestyle photography, candid, authentic, relatable',
            'product' => 'product photography, studio lighting, clean background, commercial quality',
        ];
        $promptParts[] = $styleMap[$style] ?? $style;

        // Color palette
        if (!empty($concept['color_palette'])) {
            $promptParts[] = 'color palette: ' . implode(', ', $concept['color_palette']);
        }

        // Composition
        if (!empty($concept['composition'])) {
            $promptParts[] = 'composition: ' . $concept['composition'];
        }

        // Key elements
        if (!empty($concept['key_elements'])) {
            $promptParts[] = 'featuring: ' . implode(', ', $concept['key_elements']);
        }

        // Mood
        if (!empty($concept['mood'])) {
            $promptParts[] = 'mood: ' . $concept['mood'];
        }

        // Text overlay guidance
        if (!empty($concept['text_overlay'])) {
            $promptParts[] = 'text space for: ' . $concept['text_overlay'];
        }

        // Aspect ratio
        $aspectRatio = $concept['aspect_ratio'] ?? '1:1';
        $arMap = [
            '1:1' => 'square aspect ratio',
            '9:16' => 'vertical 9:16 aspect ratio, mobile stories format',
            '4:5' => '4:5 aspect ratio, Instagram feed',
            '16:9' => '16:9 widescreen aspect ratio',
        ];
        $promptParts[] = $arMap[$aspectRatio] ?? 'square aspect ratio';

        // Quality modifiers
        $promptParts[] = 'high quality, professional advertising photography, conversion-optimized';

        return implode(', ', array_filter($promptParts));
    }

    /**
     * Save creative asset to database.
     */
    protected function saveCreativeAsset(array $result, Tenant $tenant, ?Campaign $campaign, ?Client $client, string $imageUrl): ?CreativeAsset
    {
        try {
            $concept = $result['concept'];
            
            $asset = CreativeAsset::create([
                'tenant_id' => $tenant->id,
                'client_id' => $client?->id,
                'campaign_id' => $campaign?->id,
                'name' => $concept['title'] ?? 'AI Generated Creative',
                'type' => 'image',
                'format' => 'png',
                'media_url' => $imageUrl,
                'elements' => [
                    'headline' => $concept['text_overlay'] ?? '',
                    'description' => $concept['description'] ?? '',
                    'style' => $concept['style'] ?? '',
                    'visual_concept' => $concept['visual_concept'] ?? '',
                ],
                'performance_metrics' => [],
                'status' => 'draft',
                'metadata' => array_merge($concept, [
                    'generated_by' => 'creative_asset_agent',
                    'generation_prompt' => $result['prompt'] ?? '',
                    'image_provider' => 'dalle',
                    'generation_timestamp' => now()->toISOString(),
                ]),
                'created_by' => auth()->id(),
            ]);

            return $asset;
        } catch (\Throwable $e) {
            Log::error('Failed to save creative asset', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate creative variants from existing asset.
     */
    public function generateVariants(CreativeAsset $asset, int $count = 4): array
    {
        $concepts = $asset->generateVariantRecommendations();
        $variants = [];

        foreach ($concepts as $concept) {
            if (count($variants) >= $count) break;
            
            foreach (($concept['suggestions'] ?? []) as $suggestion) {
                if (count($variants) >= $count) break;
                
                $variant = [
                    'type' => $concept['type'],
                    'original' => $concept['original'] ?? '',
                    'suggestion' => $suggestion,
                    'test_type' => $concept['test_type'] ?? 'A/B',
                ];
                $variants[] = $variant;
            }
        }

        return array_slice($variants, 0, $count);
    }

    /**
     * Analyze creative performance and suggest improvements.
     */
    public function analyzeCreative(CreativeAsset $creative): array
    {
        $performanceScore = $creative->performance_score ?? 0;
        $fatigue = $creative->isFatigued();
        $variants = $creative->generateVariantRecommendations();

        $analysis = [
            'performance_score' => $performanceScore,
            'fatigue_status' => $fatigue,
            'variant_suggestions' => $variants,
            'recommendations' => [],
        ];

        if ($fatigue['is_fatigued']) {
            $analysis['recommendations'][] = [
                'type' => 'replace',
                'priority' => 'high',
                'message' => 'Creative is fatigued - replace immediately',
            ];
        }

        if ($performanceScore < 50) {
            $analysis['recommendations'][] = [
                'type' => 'optimize',
                'priority' => 'high',
                'message' => 'Performance below average - test new variants',
            ];
        }

        if ($performanceScore > 75 && !$fatigue['is_fatigued']) {
            $analysis['recommendations'][] = [
                'type' => 'scale',
                'priority' => 'medium',
                'message' => 'High performer - consider scaling budget and testing variants',
            ];
        }

        return $analysis;
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'image_provider' => 'dalle',
            'image_model' => 'dall-e-3',
            'default_image_size' => '1024x1024',
            'default_quality' => 'standard',
            'auto_save_assets' => true,
            'generate_variants' => true,
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
