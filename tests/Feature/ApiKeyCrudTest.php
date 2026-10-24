<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * API keys module: CRUD via /api/v1/api-keys for service-to-service
 * authentication, plus scope listing and regeneration.
 */
class ApiKeyCrudTest extends TestCase
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

    private function makeApiKey(array $overrides = []): ApiKey
    {
        $generated = ApiKey::generateKey();

        return ApiKey::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'CI service key',
            'key_hash' => Hash::make($generated['raw_key']),
            'key_prefix' => $generated['key_prefix'],
            'scopes' => ['campaigns:read'],
            'is_active' => true,
        ], $overrides));
    }

    public function test_api_keys_require_authentication(): void
    {
        $this->getJson($this->apiUrl('/api-keys'))->assertUnauthorized();
    }

    public function test_index_lists_keys_without_exposing_hashes(): void
    {
        $key = $this->makeApiKey();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/api-keys'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($key->name, $response->json('data.0.name'));
        $this->assertNull($response->json('data.0.key_hash'));
    }

    public function test_store_api_key_returns_raw_key_once(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/api-keys'), [
                'name' => 'Integration key',
                'scopes' => ['reports:read', 'campaigns:read'],
                'ip_whitelist' => ['10.0.0.1'],
                'description' => 'For the analytics job',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['api_key', 'raw_key', 'message']);

        $raw = $response->json('raw_key');
        $this->assertNotEmpty($raw);
        $this->assertStringStartsWith('wbx_', $raw);
        $this->assertSame(substr($raw, 0, 12), $response->json('api_key.key_prefix'));
        $this->assertNull($response->json('api_key.key_hash'));

        // Persisted row stores the hash, never the raw key.
        $this->assertDatabaseHas('api_keys', [
            'name' => 'Integration key',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $stored = ApiKey::where('name', 'Integration key')->first();
        $this->assertNotSame($raw, $stored->key_hash);
        $this->assertTrue(Hash::check($raw, $stored->key_hash));
    }

    public function test_store_api_key_validates_required_name(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/api-keys'), ['scopes' => ['*']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_api_key_rejects_past_expiry(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/api-keys'), [
                'name' => 'Expired key',
                'expires_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    }

    public function test_show_api_key_hides_hash_and_loads_creator(): void
    {
        $key = $this->makeApiKey();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl("/api-keys/{$key->id}"))
            ->assertOk()
            ->assertJsonPath('name', $key->name)
            ->assertJsonPath('creator.id', $this->user->id);

        $this->assertNull($response->json('key_hash'));
    }

    public function test_update_api_key_persists_changes(): void
    {
        $key = $this->makeApiKey();

        $this->actingAs($this->user, 'sanctum')
            ->putJson($this->apiUrl("/api-keys/{$key->id}"), [
                'name' => 'Renamed key',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed key')
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('api_keys', [
            'id' => $key->id,
            'name' => 'Renamed key',
            'is_active' => false,
        ]);
    }

    public function test_regenerate_api_key_issues_new_secret(): void
    {
        $key = $this->makeApiKey();
        $oldHash = $key->fresh()->key_hash;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl("/api-keys/{$key->id}/regenerate"))
            ->assertOk()
            ->assertJsonStructure(['api_key', 'raw_key', 'message']);

        $newRaw = $response->json('raw_key');
        $this->assertStringStartsWith('wbx_', $newRaw);

        $fresh = $key->fresh();
        $this->assertNotSame($oldHash, $fresh->key_hash);
        $this->assertTrue(Hash::check($newRaw, $fresh->key_hash));
    }

    public function test_delete_api_key_removes_it(): void
    {
        $key = $this->makeApiKey();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl("/api-keys/{$key->id}"))
            ->assertNoContent();

        $this->assertSoftDeleted('api_keys', ['id' => $key->id]);
    }

    public function test_scopes_endpoint_lists_available_scopes(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/api-keys/scopes'))
            ->assertOk()
            ->assertJsonStructure(['scopes' => ['campaigns:read', 'reports:read']]);
    }
}
