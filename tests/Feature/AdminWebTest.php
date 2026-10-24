<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin web module: /admin/* routes behind ['auth', 'admin'].
 *
 * Plan store/update/destroy success paths require live Stripe API
 * calls and are not exercised; validation (422) and read paths are.
 */
class AdminWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        // Platform admin without a tenant (sees everything).
        $this->admin = User::factory()->create(['tenant_id' => null]);
        $this->admin->assignRole('admin');

        $this->regular = User::factory()->create();
        $this->regular->assignRole('user');
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'stripe_product_id' => 'prod_test'.random_int(1000, 9999),
            'stripe_price_id' => 'price_test'.random_int(1000, 9999),
            'name' => 'Pro Monthly',
            'description' => 'The pro plan',
            'active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function test_admin_can_view_dashboard(): void
    {
        // A healthy client in a tenant — the funnel counts it as an
        // opportunity (exercises the clients.health_score query).
        \App\Models\Client::factory()->create(['health_score' => 90, 'slug' => 'dash-client']);

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('stats')
            ->assertViewHas('charts')
            ->assertViewHas('funnel', fn ($funnel) => $funnel['opportunities'] === 1);
    }

    public function test_regular_user_is_redirected_from_dashboard(): void
    {
        $this->actingAs($this->regular)
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Plans
    // ---------------------------------------------------------------

    public function test_admin_can_view_plans_index(): void
    {
        $this->makePlan();

        $this->actingAs($this->admin)
            ->get('/admin/plans')
            ->assertOk()
            ->assertViewHas('plans');
    }

    public function test_admin_can_view_plans_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/plans/create')
            ->assertOk();
    }

    public function test_admin_can_view_plan_edit_form(): void
    {
        $plan = $this->makePlan();

        $this->actingAs($this->admin)
            ->get("/admin/plans/{$plan->id}/edit")
            ->assertOk()
            ->assertViewHas('plan', fn ($p) => $p->id === $plan->id);
    }

    public function test_admin_plans_store_validates_input(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/plans', ['name' => 'Broken Plan'])
            ->assertSessionHasErrors(['amount', 'currency', 'interval', 'interval_count']);
    }

    public function test_regular_user_cannot_view_plans(): void
    {
        $this->actingAs($this->regular)
            ->get('/admin/plans')
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Monitoring
    // ---------------------------------------------------------------

    public function test_admin_can_view_monitoring_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/monitoring')
            ->assertOk();
    }

    public function test_admin_monitoring_status_returns_json(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/admin/monitoring/status')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_regular_user_cannot_view_monitoring(): void
    {
        $this->actingAs($this->regular)
            ->get('/admin/monitoring')
            ->assertRedirect(route('login'));
    }
}
