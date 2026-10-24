<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Campaigns module: full CRUD via /api/v1/campaigns.
 *
 * Cross-tenant isolation is covered by TenantIsolationTest; here we
 * exercise same-tenant happy paths, validation, and 404s.
 */
class CampaignCrudTest extends TestCase
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

    private function campaignPayload(string $name = 'Spring Sale', ?string $slug = null): array
    {
        return [
            'name' => $name,
            'slug' => $slug ?? Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'A test campaign',
            'status' => 'draft',
            'type' => 'email',
            'budget' => 1500,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-01',
            'targeting' => ['age' => '18-24'],
            'settings' => ['frequency_cap' => 1],
        ];
    }

    public function test_campaigns_index_requires_authentication(): void
    {
        $this->getJson($this->apiUrl('/campaigns'))->assertUnauthorized();
    }

    public function test_index_lists_own_tenant_campaigns(): void
    {
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alpha', 'slug' => 'alpha']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beta', 'slug' => 'beta']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/campaigns'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = array_column($response->json('data'), 'name');
        sort($names);
        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function test_index_filters_by_status(): void
    {
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active', 'slug' => 'act-1']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'paused', 'slug' => 'pas-1']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/campaigns?status=active'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_store_campaign_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/campaigns'), $this->campaignPayload());

        $response->assertCreated()
            ->assertJsonPath('name', 'Spring Sale')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('tenant_id', $this->tenant->id);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Spring Sale',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_campaign_validates_required_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/campaigns'), ['name' => 'Only Name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'status', 'budget']);
    }

    public function test_store_campaign_rejects_invalid_status(): void
    {
        $payload = $this->campaignPayload();
        $payload['status'] = 'exploded';

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/campaigns'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_campaign_rejects_duplicate_slug(): void
    {
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'taken-slug']);

        $payload = $this->campaignPayload();
        $payload['slug'] = 'taken-slug';

        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/campaigns'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_show_campaign_returns_details(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Showable',
            'slug' => 'showable',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/campaigns/{$campaign->id}"))
            ->assertOk()
            ->assertJsonPath('name', 'Showable');
    }

    public function test_show_missing_campaign_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/campaigns/999999'))
            ->assertNotFound();
    }

    public function test_update_campaign_persists_changes(): void
    {
        $campaign = Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'slug' => 'updatable',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/campaigns/{$campaign->id}"), [
                'name' => 'Updated Name',
                'status' => 'active',
                'budget' => 999,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'Updated Name',
            'status' => 'active',
        ]);
    }

    public function test_update_campaign_rejects_invalid_status(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'updatable2']);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/campaigns/{$campaign->id}"), ['status' => 'nonsense'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_delete_campaign_removes_it(): void
    {
        $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'deletable']);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl("/campaigns/{$campaign->id}"))
            ->assertNoContent();

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    public function test_delete_missing_campaign_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl('/campaigns/999999'))
            ->assertNotFound();
    }
}
