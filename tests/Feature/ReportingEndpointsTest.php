<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Plan;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reporting module: extends ReportingTest with the trend endpoints,
 * auth guard, and value-level assertions on tenant-scoped counts.
 */
class ReportingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Insert a raw Cashier subscriptions row (tenant-scoped via the
     * subscriptions.tenant_id column this app's published Cashier
     * migration adds).
     */
    private function makeSubscription(array $overrides = []): void
    {
        DB::table('subscriptions')->insert(array_merge([
            'user_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'type' => 'main',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro_monthly',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makePricedPlan(int $amountCents = 4999, string $interval = 'month', int $intervalCount = 1): Plan
    {
        return Plan::create([
            'stripe_product_id' => 'prod_test_'.uniqid(),
            'stripe_price_id' => 'price_pro_monthly',
            'name' => 'Pro',
            'active' => true,
            'amount' => $amountCents,
            'currency' => 'usd',
            'interval' => $interval,
            'interval_count' => $intervalCount,
        ]);
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson($this->apiUrl('/reports/revenue'))->assertUnauthorized();
        $this->getJson($this->apiUrl('/reports/task-stats'))->assertUnauthorized();
        $this->getJson($this->apiUrl('/reports/revenue/trend'))->assertUnauthorized();
    }

    public function test_revenue_report_returns_structure(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue'))
            ->assertOk()
            ->assertJsonStructure(['mrr', 'currency', 'plan_breakdown'])
            ->assertJsonPath('currency', 'USD');
    }

    public function test_revenue_report_computes_nonzero_mrr_from_local_plans(): void
    {
        // $49.99/month plan, matched to subscriptions via stripe price ID.
        $this->makePricedPlan(4999);

        // Two active subs in the tenant: one never-ending, one ending next month.
        $this->makeSubscription();
        $this->makeSubscription(['ends_at' => now()->addMonth()]);

        // An expired sub must NOT count.
        $this->makeSubscription(['ends_at' => now()->subDay()]);

        // A sub from another tenant must NOT count (tenant scoping).
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $this->makeSubscription([
            'user_id' => $foreignUser->id,
            'tenant_id' => $foreignTenant->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue'))
            ->assertOk()
            ->assertJsonPath('mrr', 99.98)
            ->assertJsonPath('plan_breakdown.0.plan', 'Pro')
            ->assertJsonPath('plan_breakdown.0.count', 2)
            ->assertJsonPath('plan_breakdown.0.amount', 99.98);
    }

    public function test_revenue_report_mrr_is_zero_without_subscriptions(): void
    {
        // A priced plan exists but nobody is subscribed: MRR must be 0,
        // not some invented number.
        $this->makePricedPlan(4999);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue'))
            ->assertOk()
            ->assertJsonPath('mrr', 0)
            ->assertJsonPath('plan_breakdown', []);
    }

    public function test_revenue_report_ignores_subscriptions_without_matching_plan(): void
    {
        // Subscription references a price ID with no local plan: it shows up
        // as an 'unknown' bucket contributing 0 — no fake math.
        $this->makeSubscription(['stripe_price' => 'price_orphan_123']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue'))
            ->assertOk()
            ->assertJsonPath('mrr', 0)
            ->assertJsonPath('plan_breakdown.0.plan', 'unknown')
            ->assertJsonPath('plan_breakdown.0.amount', 0);
    }

    public function test_revenue_report_normalizes_yearly_plan_to_monthly(): void
    {
        // $588/year => $49/month.
        $this->makePricedPlan(58800, 'year', 1);
        $this->makeSubscription();

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue'))
            ->assertOk()
            ->assertJsonPath('mrr', 49);
    }

    public function test_revenue_trend_covers_six_months(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/revenue/trend'))
            ->assertOk()
            ->assertJsonStructure(['revenue_trend' => ['*' => ['period', 'mrr']]]);

        $trend = $response->json('revenue_trend');
        $this->assertCount(6, $trend);

        // Oldest first, ending with the current month.
        $this->assertSame(\Carbon\Carbon::now()->format('Y-m'), end($trend)['period']);
    }

    public function test_campaign_creation_trend_counts_this_months_campaigns(): void
    {
        Campaign::factory()->count(2)->create(['tenant_id' => $this->user->tenant_id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/campaign/creation-trend'))
            ->assertOk();

        $trend = $response->json('campaign_creation_trend');
        $this->assertCount(6, $trend);

        $current = end($trend);
        $this->assertSame(\Carbon\Carbon::now()->format('Y-m'), $current['period']);
        $this->assertSame(2, $current['created_campaigns']);
        // All other months are empty.
        $this->assertSame(2, array_sum(array_column($trend, 'created_campaigns')));
    }

    public function test_task_completion_trend_counts_recent_completions(): void
    {
        Task::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'assigned_to' => $this->user->id,
            'status' => 'completed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/task/completion-trend'))
            ->assertOk();

        $trend = $response->json('task_completion_trend');
        $this->assertCount(6, $trend);

        $current = end($trend);
        $this->assertSame(\Carbon\Carbon::now()->format('Y-m'), $current['period']);
        $this->assertGreaterThanOrEqual(1, $current['completed_tasks']);
    }

    public function test_user_activity_counts_users_active_in_last_30_days(): void
    {
        // The acting user plus one freshly created user in the same tenant.
        User::factory()->create(['tenant_id' => $this->user->tenant_id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/reports/user/activity'))
            ->assertOk()
            ->assertJsonPath('active_users_last_30_days', 2);
    }
}
