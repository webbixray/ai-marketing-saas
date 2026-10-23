<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0 regression guard: multi-tenant data isolation.
 *
 * Every end-user resource must be invisible to users of other tenants:
 * read, update, and delete attempts must fail (404 / 403), never leak data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function userForTenant(int $tenantId, string $name = 'Member'): User
    {
        $email = $name.'.'.Str::random(6).'@isolation.test';
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'tenant_id' => $tenantId,
        ]);

        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'api']
        );
        $user->assignRole($role);

        return $user;
    }

    public function test_user_cannot_read_another_tenants_campaign(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        $campaign = Campaign::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($userB)
            ->getJson("/api/v1/campaigns/{$campaign->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_update_another_tenants_campaign(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        $campaign = Campaign::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($userB)
            ->putJson("/api/v1/campaigns/{$campaign->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'name' => $campaign->name]);
    }

    public function test_user_cannot_delete_another_tenants_campaign(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        $campaign = Campaign::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($userB)
            ->deleteJson("/api/v1/campaigns/{$campaign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_campaign_index_only_returns_own_tenant_campaigns(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        Campaign::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Campaign::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userB)
            ->getJson('/api/v1/campaigns?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_read_another_tenants_task(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        $task = Task::factory()->create(['tenant_id' => $tenantA->id]);

        $response = $this->actingAs($userB)->getJson("/api/v1/tasks/{$task->id}");

        // 403 (explicit authorization) or 404 (tenant scope) — both must block access
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_cannot_read_another_tenants_client(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->userForTenant($tenantA->id, 'UserA');
        $userB = $this->userForTenant($tenantB->id, 'UserB');

        $client = Client::factory()->create(['tenant_id' => $tenantA->id]);

        $this->actingAs($userB)
            ->getJson("/api/v1/clients/{$client->id}")
            ->assertNotFound();
    }

    public function test_guest_cannot_read_any_campaign(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = $this->userForTenant($tenantA->id, 'UserA');

        $campaign = Campaign::factory()->create(['tenant_id' => $tenantA->id]);

        $this->getJson("/api/v1/campaigns/{$campaign->id}")->assertUnauthorized();
    }
}
