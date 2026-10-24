<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clients module: full CRUD via /api/v1/clients plus the
 * client-scoped campaigns and performance sub-resources.
 */
class ClientCrudTest extends TestCase
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

    private function clientPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'email' => 'billing@acme.test',
            'industry' => 'Technology',
            'status' => 'active',
            'monthly_budget' => 5000,
        ], $overrides);
    }

    public function test_index_lists_own_clients(): void
    {
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Client One', 'slug' => 'client-one']);
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Client Two', 'slug' => 'client-two']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/clients'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = array_column($response->json('data'), 'name');
        sort($names);
        $this->assertSame(['Client One', 'Client Two'], $names);
    }

    public function test_index_filters_by_search(): void
    {
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gamma Industries', 'slug' => 'gamma-ind']);
        Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Delta Media', 'slug' => 'delta-med']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/clients?search=Gamma'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gamma Industries');
    }

    public function test_store_client_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/clients'), $this->clientPayload());

        $response->assertCreated()
            ->assertJsonPath('name', 'Acme Corp')
            ->assertJsonPath('slug', 'acme-corp')
            ->assertJsonPath('tenant_id', $this->tenant->id);

        $this->assertDatabaseHas('clients', [
            'slug' => 'acme-corp',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_client_validates_required_fields(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/clients'), ['name' => 'No Slug'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_client_rejects_invalid_status(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/clients'), $this->clientPayload(['status' => 'defunct']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_show_client_includes_relationships(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'account_manager_id' => $this->user->id,
            'slug' => 'showable-client',
        ]);
        Campaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'slug' => 'client-campaign',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/clients/{$client->id}"))
            ->assertOk()
            ->assertJsonPath('name', $client->name)
            ->assertJsonPath('account_manager.id', $this->user->id)
            ->assertJsonCount(1, 'campaigns');
    }

    public function test_show_missing_client_returns_404(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/clients/999999'))
            ->assertNotFound();
    }

    public function test_update_client_persists_changes(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'slug' => 'updatable-client',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/clients/{$client->id}"), [
                'name' => 'Renamed Corp',
                'status' => 'paused',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed Corp')
            ->assertJsonPath('status', 'paused');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Renamed Corp',
            'status' => 'paused',
        ]);
    }

    public function test_update_client_rejects_invalid_status(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'updatable-client2']);

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/clients/{$client->id}"), ['status' => 'bogus'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_delete_client_removes_it(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'deletable-client']);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl("/clients/{$client->id}"))
            ->assertNoContent();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_client_campaigns_subresource_lists_only_that_clients_campaigns(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'campaign-client']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'slug' => 'cc-1', 'status' => 'active']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'slug' => 'cc-2', 'status' => 'paused']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'unrelated-campaign']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/clients/{$client->id}/campaigns"))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/clients/{$client->id}/campaigns?status=active"))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cc-1');
    }

    public function test_client_performance_summary_aggregates_campaigns(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'perf-client']);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'slug' => 'perf-1', 'status' => 'active', 'budget' => 100, 'spent' => 40]);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'slug' => 'perf-2', 'status' => 'paused', 'budget' => 200, 'spent' => 10]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/clients/{$client->id}/performance"))
            ->assertOk()
            ->assertJsonPath('performance.total_campaigns', 2)
            ->assertJsonPath('performance.active_campaigns', 1)
            ->assertJsonPath('performance.total_budget', 300)
            ->assertJsonPath('performance.total_spent', 50);
    }
}
