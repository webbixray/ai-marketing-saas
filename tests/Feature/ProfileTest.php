<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profile module: /api/v1/profile show/update, password change.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_profile_show_returns_current_user(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->apiUrl('/profile'))
            ->assertOk()
            ->assertJsonPath('email', $this->user->email);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson($this->apiUrl('/profile'))->assertUnauthorized();
    }

    public function test_profile_update_changes_name(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->apiUrl('/profile'), ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'New Name']);
    }

    public function test_profile_update_rejects_invalid_email(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->apiUrl('/profile'), ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_change_password_requires_current_password(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson($this->apiUrl('/change-password'), [
                'current_password' => 'wrong',
                'new_password' => 'SuperSecret1!',
                'new_password_confirmation' => 'SuperSecret1!',
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Current password is incorrect');
    }

    public function test_change_password_revokes_tokens_and_allows_relogin(): void
    {
        // Authenticate with a real bearer token (actingAs memoizes the user
        // on the guard and would bypass token revocation).
        $token = $this->user->createToken('pre-change')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson($this->apiUrl('/change-password'), [
                'current_password' => 'password',
                'new_password' => 'SuperSecret1!',
                'new_password_confirmation' => 'SuperSecret1!',
            ])
            ->assertOk();

        // Old token was revoked. (forgetGuards: Sanctum's RequestGuard
        // memoizes the user per app instance — a test-only artifact.)
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson($this->apiUrl('/user'))
            ->assertUnauthorized();

        // New password logs in.
        $this->postJson($this->apiUrl('/login'), [
            'email' => $this->user->email,
            'password' => 'SuperSecret1!',
        ])->assertOk();
    }
}
