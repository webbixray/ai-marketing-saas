<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCIM provisioning endpoints must authenticate via the tenant's shared
 * SCIM bearer token (tenant_scim_config.bearer_token). Plain user Sanctum
 * tokens, missing tokens, and invalid/disabled tokens all get a SCIM-format
 * 401 — previously the group was only auth:sanctum, so any API user token
 * "passed" and the service ran against a container-instantiated empty Tenant.
 */
class ScimAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_scim_token_returns_401(): void
    {
        $this->getJson($this->apiUrl('/enterprise/scim/users'))
            ->assertUnauthorized()
            ->assertJsonPath('status', '401');
    }

    public function test_plain_user_token_is_rejected_on_scim_routes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson($this->apiUrl('/enterprise/scim/users'))
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson($this->apiUrl('/enterprise/scim/service-provider-config'))
            ->assertUnauthorized();
    }

    public function test_invalid_scim_token_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-scim-token')
            ->getJson($this->apiUrl('/enterprise/scim/users'))
            ->assertUnauthorized()
            ->assertJsonPath('detail', 'Invalid or missing SCIM bearer token.');
    }

    public function test_disabled_scim_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        \DB::table('tenant_scim_config')->insert([
            'tenant_id' => $tenant->id,
            'enabled' => false,
            'bearer_token' => 'scim-disabled-token',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer scim-disabled-token')
            ->getJson($this->apiUrl('/enterprise/scim/users'))
            ->assertUnauthorized();
    }

    public function test_valid_scim_token_grants_access(): void
    {
        $tenant = Tenant::factory()->create();
        \DB::table('tenant_scim_config')->insert([
            'tenant_id' => $tenant->id,
            'enabled' => true,
            'bearer_token' => 'scim-secret-token',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer scim-secret-token')
            ->getJson($this->apiUrl('/enterprise/scim/users'))
            ->assertOk()
            ->assertJsonPath('totalResults', 0);
    }
}
