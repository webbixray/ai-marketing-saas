<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionBuilder;
use Mockery;

class BillingTest extends TestCase
{
    use WithFaker, DatabaseMigrations;

    public function test_user_can_subscribe_to_a_plan()
    {
        // Fake notifications to avoid sending actual emails/notifications
        Notification::fake();

        // Create a real user from factory
        $realUser = User::factory()->create();

        // Create a mock of the user (partial mock) to mock specific methods
        $mockUser = Mockery::mock($realUser, function ($mock) {
            $mock->shouldReceive('addPaymentMethod')->once()->with('pm_card_visa')->andReturnSelf();
            $mock->shouldReceive('newSubscription')->once()->with('main', config('billing.plans.basic.stripe_price'))
                ->andReturn($this->getMockSubscriptionBuilder());
        })->makePartial();

        // Create a mock subscription that will return the expected attributes
        $mockSubscription = $this->getMockSubscription();

        // Now use the mocked user for actingAs
        $this->actingAs($mockUser, 'sanctum');

        // Attempt to subscribe using a valid plan key from config
        $response = $this->postJson($this->apiUrl('/billing/subscribe'), [
            'plan' => 'basic',
            'payment_method' => 'pm_card_visa', // Stripe's test token that succeeds
        ]);

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'subscription' => [
                'id',
                'stripe_id',
                'stripe_plan',
                'stripe_status',
                'trial_ends_at',
                'ends_at',
            ]
        ]);
        
        // Verify notification was sent
        Notification::assertSentTo(
            $mockUser,
            \App\Notifications\SubscriptionCreated::class
        );
    }

    protected function getMockSubscriptionBuilder()
    {
        return Mockery::mock(SubscriptionBuilder::class, function ($mock) {
            $mock->shouldReceive('create')->once()->with('pm_card_visa')->andReturn($this->getMockSubscription());
        });
    }

    protected function getMockSubscription()
    {
        return Mockery::mock(Subscription::class, function ($mock) {
            $mock->shouldReceive('getAttribute')
                ->with('id')->andReturn(1);
            $mock->shouldReceive('getAttribute')
                ->with('stripe_id')->andReturn('sub_123');
            $mock->shouldReceive('getAttribute')
                ->with('stripe_plan')->andReturn('price_1Basic');
            $mock->shouldReceive('getAttribute')
                ->with('stripe_status')->andReturn('active');
            $mock->shouldReceive('getAttribute')
                ->with('trial_ends_at')->andReturn(null);
            $mock->shouldReceive('getAttribute')
                ->with('ends_at')->andReturn(null);
            // For any other attribute, return null to avoid errors
            $mock->shouldReceive('getAttribute')
                ->with(Mockery::any())
                ->andReturnNull();
            // Mock jsonSerialize for JSON conversion
            $mock->shouldReceive('jsonSerialize')->andReturn([
                'id' => 1,
                'stripe_id' => 'sub_123',
                'stripe_plan' => 'price_1Basic',
                'stripe_status' => 'active',
                'trial_ends_at' => null,
                'ends_at' => null,
            ]);
        });
    }

    public function test_stripe_webhook_handles_invoice_payment_succeeded()
    {
        // Create a user and a subscription (simplified)
        $user = User::factory()->create();
        $user->stripe_id = 'cus_test';
        $user->save();

        // Simulate the webhook event
        $response = $this->postJson('/stripe/webhook', [
            'id' => 'evt_test',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test',
                    'customer' => $user->stripe_id,
                    'subscription' => 'sub_test',
                    'status' => 'paid',
                    'amount_paid' => 1000,
                    'currency' => 'usd',
                ],
            ],
        ]);

        $response->assertStatus(200);
        // In a real test, we'd check that the subscription status was updated
    }

    public function test_stripe_webhook_handles_customer_subscription_deleted()
    {
        // Create a user and a subscription (simplified)
        $user = User::factory()->create();
        $user->stripe_id = 'cus_test';
        $user->save();

        $response = $this->postJson('/stripe/webhook', [
            'id' => 'evt_test',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test',
                    'customer' => $user->stripe_id,
                ],
            ],
        ]);

        $response->assertStatus(200);
        // Check that the subscription was marked as inactive or deleted
    }
}
