<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auth module: register, login, logout, /user (me), password reset,
 * email verification.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(Tenant $tenant, string $email): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_id' => $tenant->id,
        ];
    }

    // ---------------------------------------------------------------
    // Register
    // ---------------------------------------------------------------

    public function test_register_creates_user_and_returns_token(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson($this->apiUrl('/register'), $this->registerPayload($tenant, 'jane@example.com'));

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'tenant_id' => $tenant->id,
        ]);

        // Registration seeds the four default notification preferences.
        $this->assertDatabaseCount('notification_preferences', 4);
    }

    public function test_register_assigns_default_user_role_when_seeded(): void
    {
        Role::create(['name' => 'user', 'guard_name' => 'api']);
        $tenant = Tenant::factory()->create();

        $this->postJson($this->apiUrl('/register'), $this->registerPayload($tenant, 'roled@example.com'))
            ->assertCreated();

        $user = User::where('email', 'roled@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_register_validates_required_fields(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson($this->apiUrl('/register'), [
            'email' => 'bad',
            'password' => 'short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'tenant_id']);
    }

    public function test_register_rejects_unconfirmed_password(): void
    {
        $tenant = Tenant::factory()->create();

        $payload = $this->registerPayload($tenant, 'noconfirm@example.com');
        unset($payload['password_confirmation']);

        $this->postJson($this->apiUrl('/register'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_unknown_tenant(): void
    {
        $payload = $this->registerPayload(new Tenant(), 'x@example.com');
        $payload['tenant_id'] = 999999;

        $this->postJson($this->apiUrl('/register'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $tenant = Tenant::factory()->create();
        $payload = $this->registerPayload($tenant, 'dup@example.com');

        $this->postJson($this->apiUrl('/register'), $payload)->assertCreated();

        $this->postJson($this->apiUrl('/register'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ---------------------------------------------------------------
    // Login
    // ---------------------------------------------------------------

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);

        $response = $this->postJson($this->apiUrl('/login'), [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['user' => ['id', 'email'], 'token']);

        // The returned token authenticates subsequent API calls.
        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson($this->apiUrl('/user'))
            ->assertOk()
            ->assertJsonPath('email', 'login@example.com');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'wrong@example.com']);

        $this->postJson($this->apiUrl('/login'), [
            'email' => 'wrong@example.com',
            'password' => 'not-the-password',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson($this->apiUrl('/login'), [
            'email' => 'ghost@example.com',
            'password' => 'password',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_validates_credentials(): void
    {
        $this->postJson($this->apiUrl('/login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ---------------------------------------------------------------
    // Me / logout
    // ---------------------------------------------------------------

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson($this->apiUrl('/user'))->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->apiUrl('/user'))
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($this->apiUrl('/logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out');

        // The revoked token no longer authenticates. (forgetGuards: Sanctum's
        // RequestGuard memoizes the user per app instance, which would leak
        // the request-1 user into request 2 in tests only.)
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($this->apiUrl('/user'))
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Password reset
    // ---------------------------------------------------------------

    public function test_password_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->forceFill([
            'password_reset_token' => 'valid-reset-token',
            'password_reset_sent_at' => now(),
        ])->save();

        $response = $this->postJson($this->apiUrl('/password/reset'), [
            'token' => 'valid-reset-token',
            'email' => 'reset@example.com',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ]);

        $response->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->password_reset_token);
        $this->assertNull($fresh->password_reset_sent_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass-1', $fresh->password));

        // Old password no longer works.
        $this->postJson($this->apiUrl('/login'), [
            'email' => 'reset@example.com',
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson($this->apiUrl('/password/reset'), [
            'token' => 'bogus',
            'email' => 'reset2@example.com',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertStatus(400)->assertJsonPath('message', 'Invalid or expired reset token.');
    }

    // ---------------------------------------------------------------
    // Email verification
    // ---------------------------------------------------------------

    public function test_email_verification_flow(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);

        $this->postJson($this->apiUrl('/email/verification-notification'), [
            'email' => 'verify@example.com',
        ])->assertOk()->assertJsonPath('message', 'Verification email sent.');

        $token = $user->fresh()->email_verification_token;
        $this->assertNotNull($token);

        $this->postJson($this->apiUrl('/email/verify'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verify_rejects_invalid_token(): void
    {
        $this->postJson($this->apiUrl('/email/verify'), ['token' => 'nope'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid or expired verification token.');
    }

    public function test_send_verification_rejects_unknown_email(): void
    {
        $this->postJson($this->apiUrl('/email/verification-notification'), [
            'email' => 'nobody@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
