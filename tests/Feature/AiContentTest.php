<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AI surface per docs/launch/AI_SURFACE_SPEC.md — run against the mock
 * provider (phpunit.xml sets AI_DEFAULT_PROVIDER=mock). No test calls the
 * network; the "provider failure" test forces an unconfigured real
 * provider, which fails fast BEFORE any HTTP request.
 */
class AiContentTest extends TestCase
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

    public function test_generate_ad_copy_returns_201_and_logs_row(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), [
                'type' => 'ad_copy',
                'brief' => 'Spring sale for a coffee brand, 20% off',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('type', 'ad_copy')
            ->assertJsonPath('tenant_id', $this->tenant->id)
            ->assertJsonPath('user_id', $this->user->id);

        $this->assertNotEmpty($response->json('output'));

        $this->assertDatabaseHas('ai_generations', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'ad_copy',
            'status' => 'completed',
        ]);
    }

    public function test_generate_with_invalid_type_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), [
                'type' => 'haiku',
                'brief' => 'A short poem',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_provider_failure_returns_502_and_logs_failed_row(): void
    {
        // Force the real (unconfigured) provider — it throws before any
        // network call because no API key is set in the test environment.
        config(['ai.llm.default_provider' => 'openai']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), [
                'type' => 'email',
                'brief' => 'Newsletter about the launch',
            ]);

        $response->assertStatus(502)
            ->assertJsonStructure(['message']);

        $row = DB::table('ai_generations')->first();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->error);
    }

    public function test_generations_list_is_tenant_scoped(): void
    {
        config(['ai.llm.default_provider' => 'mock']);

        // Own-tenant generations
        foreach (['ad_copy', 'email'] as $type) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson($this->apiUrl('/ai/generate'), ['type' => $type, 'brief' => "brief for {$type}"])
                ->assertCreated();
        }

        // Cross-tenant generation
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->actingAs($otherUser, 'sanctum')
            ->postJson($this->apiUrl('/ai/generate'), ['type' => 'blog', 'brief' => 'other tenant blog'])
            ->assertCreated();

        // Own user sees only their two
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/ai/generations'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Cross-tenant user sees zero of the first tenant's rows
        $this->actingAs($otherUser, 'sanctum')
            ->getJson($this->apiUrl('/ai/generations'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'blog');
    }

    public function test_insights_returns_structured_rules_for_campaign_with_data(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'insights-rich-campaign',
            'status' => 'active',
            'platform' => 'meta',
            'budget' => 1000,
            'spent' => 950,          // >= 90% of budget -> budget warning
            'impressions' => 10000,
            'clicks' => 30,          // 0.3% CTR < 50% of facebook benchmark -> low_ctr
            'conversions' => 1,      // avoids the spend_without_conversions rule
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30),
            'created_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/ai/insights/campaigns/{$campaign->id}"))
            ->assertOk()
            ->assertJsonPath('campaign.id', $campaign->id);

        $types = array_column($response->json('insights'), 'type');
        $this->assertContains('budget', $types);
        $this->assertContains('low_ctr', $types);

        foreach ($response->json('insights') as $insight) {
            $this->assertArrayHasKey('severity', $insight);
            $this->assertArrayHasKey('message', $insight);
            $this->assertArrayHasKey('details', $insight);
        }
    }

    public function test_insights_empty_for_campaign_without_data(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'slug' => 'insights-empty-campaign',
            'status' => 'draft',
            'budget' => 0,
            'spent' => 0,
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/ai/insights/campaigns/{$campaign->id}"))
            ->assertOk()
            ->assertJsonPath('insights', []);
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson($this->apiUrl('/ai/generate'), ['type' => 'ad_copy', 'brief' => 'x'])
            ->assertUnauthorized();

        $this->getJson($this->apiUrl('/ai/generations'))
            ->assertUnauthorized();

        $this->getJson($this->apiUrl('/ai/status'))
            ->assertUnauthorized();
    }
}
