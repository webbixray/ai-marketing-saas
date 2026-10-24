<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rate limiting: the 'auth' limiter allows 10 requests/minute per IP on
 * the auth endpoints; health checks use a far higher limit.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_endpoints_throttle_after_ten_requests_per_minute(): void
    {
        $user = User::factory()->create(['email' => 'throttle@example.com']);

        $payload = [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ];

        $statuses = [];
        for ($i = 1; $i <= 11; $i++) {
            $statuses[] = $this->postJson($this->apiUrl('/login'), $payload)->status();
        }

        // First 10 attempts get through to the controller (401),
        // the 11th is rejected by the limiter.
        $this->assertSame([401, 401, 401, 401, 401, 401, 401, 401, 401, 401, 429], $statuses);
    }

    public function test_health_endpoints_are_not_throttled_at_the_auth_limit(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->getJson($this->apiUrl('/health'))->assertOk();
        }
    }
}
