<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin user management: /admin/users CRUD-lite for platform admins.
 *
 * Roles live on the 'api' Spatie guard (see RolePermissionSeeder); the web
 * 'admin' middleware checks hasRole('admin') with no guard argument, so
 * web admins are users holding the api-guard admin role.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regular;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        // Platform admin without a tenant (sees all tenants' users).
        $this->admin = User::factory()->create(['tenant_id' => null]);
        $this->admin->assignRole('admin');

        $this->tenant = Tenant::factory()->create();
        $this->regular = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->regular->assignRole('user');
    }

    // ---------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------

    public function test_admin_can_view_users_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertViewHas('users')
            ->assertViewHas('tenants')
            ->assertViewHas('roles')
            ->assertSee($this->regular->name)
            ->assertSee($this->regular->email);
    }

    public function test_index_search_filters_by_name_or_email(): void
    {
        User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Findable Person', 'email' => 'findable@example.test']);

        $this->actingAs($this->admin)
            ->get('/admin/users?search=Findable')
            ->assertOk()
            ->assertSee('Findable Person');

        $this->actingAs($this->admin)
            ->get('/admin/users?search=nomatchzzz')
            ->assertOk()
            ->assertDontSee('Findable Person');
    }

    public function test_regular_user_is_redirected_from_users_index(): void
    {
        $this->actingAs($this->regular)
            ->get('/admin/users')
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_users_index(): void
    {
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Store
    // ---------------------------------------------------------------

    public function test_admin_can_create_user_in_tenant_with_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'New Teammate',
                'email' => 'newteammate@example.test',
                'password' => 'supersecret123',
                'password_confirmation' => 'supersecret123',
                'tenant_id' => $this->tenant->id,
                'role' => 'user',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::where('email', 'newteammate@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue($user->hasRole('user'));

        // The created password works for API login
        $this->postJson($this->apiUrl('/login'), [
            'email' => 'newteammate@example.test',
            'password' => 'supersecret123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', ['name' => 'Incomplete'])
            ->assertSessionHasErrors(['email', 'password', 'tenant_id', 'role']);
    }

    public function test_create_user_rejects_unknown_role(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'Roleless',
                'email' => 'roleless@example.test',
                'password' => 'supersecret123',
                'password_confirmation' => 'supersecret123',
                'tenant_id' => $this->tenant->id,
                'role' => 'superadmin',
            ])
            ->assertSessionHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'roleless@example.test']);
    }

    // ---------------------------------------------------------------
    // Toggle active
    // ---------------------------------------------------------------

    public function test_toggle_active_deactivates_and_reactivates_user(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', ['id' => $this->regular->id, 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('is_active', true);
    }

    public function test_deactivated_user_cannot_login_via_api(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/toggle-active")
            ->assertOk();

        $this->postJson($this->apiUrl('/login'), [
            'email' => $this->regular->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_deactivated_user_cannot_login_via_web(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/toggle-active")
            ->assertOk();

        $this->post('/login', [
            'email' => $this->regular->email,
            'password' => 'password',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    // ---------------------------------------------------------------
    // Role change
    // ---------------------------------------------------------------

    public function test_admin_can_change_user_role(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('roles.0', 'admin');

        $this->assertTrue($this->regular->fresh()->hasRole('admin'));
        $this->assertFalse($this->regular->fresh()->hasRole('user'));
    }

    public function test_role_change_rejects_unknown_role(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->regular->id}/role", ['role' => 'wizard'])
            ->assertSessionHasErrors(['role']);

        $this->assertTrue($this->regular->fresh()->hasRole('user'));
    }

    public function test_toggle_active_on_missing_user_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users/999999/toggle-active')
            ->assertNotFound();
    }
}
