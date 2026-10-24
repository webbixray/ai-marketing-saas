<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI surface contract tests: /ai/generate, /ai/generations, /ai/status,
 * /ai/insights — mock provider only (no network in tests).
 */
class AiSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_generate_unauthenticated_returns_401(): void
    {
        $this->postJson($this->apiUrl('/ai/generate'), ['type' => 'ad_copy', 'brief' => 'x'])
            ->assertUnauthorized();
    }

    public function test_generate_success_returns_content_and_persists_row(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), [
                'type' => 'social_caption',
                'brief' => 'Product launch for a fitness app',
                'tone' => 'energetic',
                'platform' => 'instagram',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('provider', 'mock')
            ->assertJsonPath('model', 'mock-1')
            ->assertJsonPath('tone', 'energetic')
            ->assertJsonPath('platform', 'instagram');

        $this->assertNotEmpty($response->json('output'));
        $this->assertNotNull($response->json('input_tokens'));
        $this->assertNotNull($response->json('output_tokens'));

        $this->assertDatabaseHas('ai_generations', [
            'id' => $response->json('id'),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);
    }

    public function test_generate_requires_brief(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brief']);
    }

    public function test_generate_rejects_unknown_type(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'sonnet', 'brief' => 'a poem'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_generate_rejects_out_of_range_max_tokens(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), [
                'type' => 'blog',
                'brief' => 'Quarterly growth report',
                'max_tokens' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_tokens']);
    }

    public function test_generate_requires_tenant(): void
    {
        $platformAdmin = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($platformAdmin, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'blog', 'brief' => 'x'])
            ->assertUnprocessable();
    }

    public function test_generations_list_is_paginated_and_tenant_scoped(): void
    {
        foreach (['ad_copy', 'email', 'blog'] as $type) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson($this->apiUrl('/ai/generate'), ['type' => $type, 'brief' => "brief {$type}"])
                ->assertCreated();
        }

        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $this->actingAs($foreignUser, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'email', 'brief' => 'foreign'])
            ->assertCreated();

        // Own tenant: 3 rows, paginated shape, type filter works
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/ai/generations'))
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total'])
            ->assertJsonPath('total', 3);
        $this->assertSame(3, $response->json('total'));

        // Cross-tenant user sees only their own 1 row (zero of ours)
        $this->actingAs($foreignUser, 'sanctum')
            ->getJson($this->apiUrl('/ai/generations'))
            ->assertOk()
            ->assertJsonPath('total', 1);

        // Type filter
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/ai/generations?type=email'))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_status_endpoint_lists_configured_providers(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/ai/status'))
            ->assertOk()
            ->assertJsonPath('default_provider', 'mock')
            ->assertJsonStructure(['default_provider', 'timeout', 'providers']);

        $names = array_column($response->json('providers'), 'name');
        $this->assertContains('openai', $names);
        $this->assertContains('mock', $names);
    }

    public function test_status_endpoint_never_leaks_api_keys(): void
    {
        $secretKey = 'sk-test-super-secret-key-1234567890';
        config(['ai.llm.providers.openai.api_key' => $secretKey]);

        $body = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/ai/status'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($secretKey, $body);

        // openai flips to configured=true when a key is present
        $openai = collect(json_decode($body, true)['providers'])->firstWhere('name', 'openai');
        $this->assertTrue($openai['configured']);
    }

    public function test_provider_failure_returns_502_not_500(): void
    {
        config(['ai.llm.default_provider' => 'openai']); // no API key -> fails pre-network

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'ad_copy', 'brief' => 'launch ad'])
            ->assertStatus(502)
            ->assertJsonStructure(['message']);
    }

    public function test_insights_cross_tenant_campaign_returns_404(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignCampaign = \App\Models\Campaign::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'slug' => 'foreign-ai-campaign',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/ai/insights/campaigns/{$foreignCampaign->id}"))
            ->assertNotFound();

        $this->actingAs($foreignUser, 'sanctum')
            ->getJson($this->apiUrl("/ai/insights/campaigns/{$foreignCampaign->id}"))
            ->assertOk();
    }
}
