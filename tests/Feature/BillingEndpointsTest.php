<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Billing module: validation/auth paths of /api/v1/billing/*.
 *
 * The happy paths that mutate Stripe (subscribe/swap/cancel with a real
 * payment method, invoice listing) require a live Stripe account and are
 * NOT exercised here — see report.
 */
class BillingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_billing_requires_authentication(): void
    {
        $this->getJson($this->apiUrl('/billing/subscription'))->assertUnauthorized();
    }

    public function test_subscribe_requires_plan_and_payment_method(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/billing/subscribe'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan', 'payment_method']);
    }

    public function test_subscribe_rejects_unknown_plan(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/billing/subscribe'), [
                'plan' => 'gold',
                'payment_method' => 'pm_card_visa',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_subscription_returns_404_when_user_has_none(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/billing/subscription'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No active subscription');
    }

    public function test_swap_rejects_unknown_plan(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/billing/swap/gold'))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid plan');
    }

    public function test_swap_returns_404_without_subscription(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/billing/swap/pro'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No active subscription');
    }

    public function test_cancel_returns_404_without_subscription(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson($this->apiUrl('/billing/subscription'))
            ->assertNotFound()
            ->assertJsonPath('message', 'No active subscription');
    }
}
