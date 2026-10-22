<?php

namespace App\Agents\AI;

use App\Agents\BaseAgent;
use App\Contracts\AgentStatus;
use App\Domain\Agent\AgentContext;
use App\Domain\Agent\AgentResult;
use App\Domain\Agent\AgentCapability;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Services\AI\LLMService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CampaignBuilderAgent extends BaseAgent
{
    protected LLMService $llm;

    public function __construct(LLMService $llm)
    {
        $this->llm = $llm;
    }

    public function getId(): string
    {
        return 'campaign.builder';
    }

    public function getName(): string
    {
        return 'AI Campaign Builder';
    }

    public function getDescription(): string
    {
        return 'Creates complete marketing campaigns from natural language descriptions using LLM';
    }

    public function getCapabilities(): array
    {
        return [
            AgentCapability::CAMPAIGN_CREATE,
            AgentCapability::CAMPAIGN_ANALYZE,
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
            'max_campaigns_per_request' => [
                'type' => 'integer',
                'required' => false,
                'default' => 3,
            ],
            'include_creative_suggestions' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'include_budget_recommendations' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
            'include_audience_suggestions' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ],
        ];
    }

    protected function performExecution(AgentContext $context): AgentResult
    {
        $tenant = $context->tenant;
        $user = $context->user;
        $payload = $context->payload;

        // Get natural language input
        $description = $payload['description'] ?? '';
        $clientId = $payload['client_id'] ?? null;
        $options = $payload['options'] ?? [];

        if (empty($description)) {
            return AgentResult::failure('Campaign description is required');
        }

        // Validate client if provided
        $client = null;
        if ($clientId) {
            $client = Client::where('id', $clientId)
                ->where('tenant_id', $tenant->id)
                ->first();
            
            if (!$client) {
                return AgentResult::failure('Client not found');
            }
        }

        // Generate campaign(s) from description
        $campaigns = $this->buildCampaigns($description, $client, $options, $tenant);

        if (empty($campaigns)) {
            return AgentResult::failure('Failed to generate campaigns from description');
        }

        // Save campaigns to database
        $savedCampaigns = [];
        foreach ($campaigns as $campaignData) {
            $campaign = $this->saveCampaign($campaignData, $tenant, $client, $user);
            if ($campaign) {
                $savedCampaigns[] = $campaign;
            }
        }

        return AgentResult::success([
            'campaigns_created' => count($savedCampaigns),
            'campaigns' => $savedCampaigns,
            'original_description' => $description,
        ], [
            'campaigns_created' => count($savedCampaigns),
        ])->withNextAction('review_campaigns', [
            'campaign_ids' => collect($savedCampaigns)->pluck('id')->toArray(),
        ]);
    }

    /**
     * Generate campaign structures from natural language.
     */
    protected function buildCampaigns(string $description, ?Client $client, array $options, Tenant $tenant): array
    {
        $schema = $this->getCampaignSchema($options);
        
        $prompt = $this->buildCampaignPrompt($description, $client, $options);

        $result = $this->llm->completeJson($prompt, $schema, [
            'provider' => $this->config['llm_provider'] ?? 'openai',
            'model' => $this->config['llm_model'] ?? 'gpt-4-turbo',
            'temperature' => 0.4,
            'system_prompt' => $this->getSystemPrompt(),
        ]);

        if (isset($result['error'])) {
            Log::error('Campaign generation failed', ['error' => $result['error']]);
            return [];
        }

        $campaigns = $result['content']['campaigns'] ?? [];
        
        // Limit number of campaigns
        $maxCampaigns = $this->config['max_campaigns_per_request'] ?? 3;
        return array_slice($campaigns, 0, $maxCampaigns);
    }

    /**
     * Build the prompt for campaign generation.
     */
    protected function buildCampaignPrompt(string $description, ?Client $client, array $options): string
    {
        $clientInfo = $client ? "
Client Information:
- Name: {$client->name}
- Industry: {$client->industry ?? 'Not specified'}
- Monthly Budget: \${$client->monthly_budget ?? 'Not set'}
- Brand Guidelines: " . json_encode($client->branding ?? []) . "
" : "";

        $platformOptions = $this->getPlatformOptions();
        $objectiveOptions = $this->getObjectiveOptions();
        $audienceOptions = $this->getAudienceOptions();

        return "
You are an expert marketing campaign strategist. Create comprehensive digital marketing campaigns from the user's natural language description.

User Description: {$description}

{$clientInfo}

Available Platforms: " . implode(', ', array_keys($platformOptions)) . "
Available Objectives: " . implode(', ', array_keys($objectiveOptions)) . "
Available Audience Types: " . implode(', ', array_keys($audienceOptions)) . "

Generate 1-3 complete campaign plans. Each campaign should include:
1. Campaign name (compelling, descriptive)
2. Platform (choose from available)
3. Objective (choose from available)
4. Campaign type (social_media, email, search, display, video)
5. Target audience (demographics, interests, behaviors)
6. Geographic targeting
7. Budget allocation (daily, lifetime, total)
8. Bidding strategy recommendation
9. Creative concepts (headlines, descriptions, CTAs)
10. Targeting criteria (demographics, interests, behaviors)
11. Schedule (start/end dates, dayparting)
12. Key performance indicators to track

Return as JSON with this structure:
{
  \"campaigns\": [
    {
      \"name\": \"string\",
      \"slug\": \"string\",
      \"description\": \"string\",
      \"platform\": \"string\",
      \"objective\": \"string\",
      \"type\": \"string\",
      \"status\": \"draft\",
      \"budget\": number,
      \"daily_budget\": number,
      \"lifetime_budget\": number,
      \"bidding_strategy\": \"string\",
      \"bid_amount\": number,
      \"start_date\": \"YYYY-MM-DD\",
      \"end_date\": \"YYYY-MM-DD\",
      \"targeting\": {\"demographics\": [], \"interests\": [], \"behaviors\": []},
      \"geo_targeting\": {\"countries\": [], \"regions\": []},
      \"audience_targeting\": {\"age_min\": 18, \"age_max\": 65, \"genders\": []},
      \"creatives\": [{\"headline\": \"\", \"body\": \"\", \"cta\": \"\", \"media_suggestions\": []}],
      \"ad_copy_variants\": [{\"headline\": \"\", \"body\": \"\", \"cta\": \"\"}],
      \"landing_page_url\": \"\",
      \"utm_parameters\": {\"utm_source\": \"\", \"utm_medium\": \"\", \"utm_campaign\": \"\"},
      \"schedule\": {\"days\": [], \"hours\": []},
      \"automation_rules\": [],
      \"tags\": [],
      \"campaign_group\": \"string\"
    }
  ]
}
";
    }

    /**
     * Get system prompt for campaign generation.
     */
    protected function getSystemPrompt(): string
    {
        return "You are an expert digital marketing campaign strategist with 15+ years of experience across Meta, Google, LinkedIn, TikTok, and programmatic advertising. You create data-driven, ROI-focused campaigns that convert. You understand platform-specific best practices, audience targeting nuances, creative psychology, and budget optimization. Always produce campaigns that are actionable, measurable, and aligned with business objectives.";
    }

    /**
     * Get JSON schema for campaign generation.
     */
    protected function getCampaignSchema(array $options): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'campaigns' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'platform' => ['type' => 'string', 'enum' => array_keys($this->getPlatformOptions())],
                            'objective' => ['type' => 'string', 'enum' => array_keys($this->getObjectiveOptions())],
                            'type' => ['type' => 'string', 'enum' => ['social_media', 'email', 'search', 'display', 'video']],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'paused']],
                            'budget' => ['type' => 'number'],
                            'daily_budget' => ['type' => 'number'],
                            'lifetime_budget' => ['type' => 'number'],
                            'bidding_strategy' => ['type' => 'string'],
                            'bid_amount' => ['type' => 'number'],
                            'start_date' => ['type' => 'string', 'format' => 'date'],
                            'end_date' => ['type' => 'string', 'format' => 'date'],
                            'targeting' => ['type' => 'object'],
                            'geo_targeting' => ['type' => 'object'],
                            'audience_targeting' => ['type' => 'object'],
                            'creatives' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'ad_copy_variants' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'landing_page_url' => ['type' => 'string', 'format' => 'uri'],
                            'utm_parameters' => ['type' => 'object'],
                            'schedule' => ['type' => 'object'],
                            'automation_rules' => ['type' => 'array'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'campaign_group' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'platform', 'objective', 'type', 'budget', 'start_date'],
                    ],
                ],
            ],
            'required' => ['campaigns'],
        ];
    }

    /**
     * Save campaign to database.
     */
    protected function saveCampaign(array $data, Tenant $tenant, ?Client $client, ?User $user): ?Campaign
    {
        try {
            $campaign = Campaign::create([
                'tenant_id' => $tenant->id,
                'client_id' => $client?->id,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6),
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'draft',
                'approval_status' => 'draft',
                'type' => $data['type'] ?? 'social_media',
                'platform' => $data['platform'] ?? 'meta',
                'budget' => $data['budget'] ?? 0,
                'daily_budget' => $data['daily_budget'] ?? null,
                'lifetime_budget' => $data['lifetime_budget'] ?? null,
                'bidding_strategy' => $data['bidding_strategy'] ?? 'maximize_conversions',
                'bid_amount' => $data['bid_amount'] ?? null,
                'start_date' => $data['start_date'] ?? now()->format('Y-m-d'),
                'end_date' => $data['end_date'] ?? null,
                'targeting' => $data['targeting'] ?? [],
                'audience_targeting' => $data['audience_targeting'] ?? [],
                'geo_targeting' => $data['geo_targeting'] ?? [],
                'audience_targeting' => $data['audience_targeting'] ?? [],
                'placement_targeting' => $data['placement_targeting'] ?? [],
                'settings' => $data['schedule'] ?? [],
                'creatives' => $data['creatives'] ?? [],
                'ad_copy_variants' => $data['ad_copy_variants'] ?? [],
                'landing_page_url' => $data['landing_page_url'] ?? null,
                'utm_parameters' => $data['utm_parameters'] ?? [],
                'schedule' => $data['schedule'] ?? [],
                'automation_rules' => $data['automation_rules'] ?? [],
                'tags' => $data['tags'] ?? [],
                'campaign_group' => $data['campaign_group'] ?? null,
            ]);

            return $campaign;
        } catch (\Throwable $e) {
            Log::error('Failed to save campaign', ['error' => $e->getMessage(), 'data' => $data]);
            return null;
        }
    }

    /**
     * Get platform options.
     */
    protected function getPlatformOptions(): array
    {
        return [
            'meta' => ['name' => 'Meta (Facebook/Instagram)', 'objectives' => ['awareness', 'traffic', 'engagement', 'leads', 'sales', 'app_promotes']],
            'google' => ['name' => 'Google Ads', 'objectives' => ['sales', 'leads', 'website_traffic', 'brand_awareness', 'app_promotion']],
            'linkedin' => ['name' => 'LinkedIn Ads', 'objectives' => ['brand_awareness', 'engagement', 'video_views', 'lead_generation', 'website_conversions']],
            'tiktok' => ['name' => 'TikTok Ads', 'objectives' => ['reach', 'traffic', 'video_views', 'lead_generation', 'conversions']],
            'programmatic' => ['name' => 'Programmatic/DSP', 'objectives' => ['awareness', 'consideration', 'conversions']],
        ];
    }

    /**
     * Get objective options.
     */
    protected function getObjectiveOptions(): array
    {
        return [
            'awareness' => 'Brand Awareness',
            'traffic' => 'Website Traffic',
            'engagement' => 'Engagement',
            'leads' => 'Lead Generation',
            'sales' => 'Sales/Conversions',
            'app_promotes' => 'App Promotion',
            'video_views' => 'Video Views',
            'reach' => 'Reach',
        ];
    }

    /**
     * Get audience options.
     */
    protected function getAudienceOptions(): array
    {
        return [
            'demographics' => ['age', 'gender', 'location', 'language', 'education', 'relationship_status'],
            'interests' => ['technology', 'business', 'finance', 'health', 'lifestyle', 'entertainment', 'sports'],
            'behaviors' => ['purchase_intent', 'device_usage', 'travel', 'content_consumption'],
            'custom' => ['customer_lists', 'lookalike', 'retargeting', 'engagement_custom'],
        ];
    }

    protected function onInitialize(array $config): void
    {
        $this->config = array_merge([
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4-turbo',
            'max_campaigns_per_request' => 3,
            'include_creative_suggestions' => true,
            'include_budget_recommendations' => true,
            'include_audience_suggestions' => true,
        ], $config);
    }

    protected function onShutdown(): void
    {
    }
}
