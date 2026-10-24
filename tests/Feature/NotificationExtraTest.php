<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifications module: complements NotificationTest with the delete
 * endpoint, 404 handling, and count behavior after marking read.
 */
class NotificationExtraTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user): Notification
    {
        return Notification::factory()->create([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
        ]);
    }

    public function test_user_can_delete_a_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $notification = $this->makeNotification($user);

        $this->deleteJson($this->apiUrl("/notifications/{$notification->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Notification deleted');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_deleting_a_missing_notification_returns_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->deleteJson($this->apiUrl('/notifications/'.\Illuminate\Support\Str::uuid()))
            ->assertNotFound()
            ->assertJsonPath('message', 'Notification not found');
    }

    public function test_marking_missing_notification_as_read_returns_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson($this->apiUrl('/notifications/'.\Illuminate\Support\Str::uuid().'/read'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Notification not found');
    }

    public function test_unread_count_decreases_after_marking_one_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $first = $this->makeNotification($user);
        $this->makeNotification($user);

        $this->getJson($this->apiUrl('/notifications/unread-count'))
            ->assertOk()
            ->assertJsonFragment(['unread_count' => 2]);

        $this->postJson($this->apiUrl("/notifications/{$first->id}/read"))
            ->assertOk();

        $this->getJson($this->apiUrl('/notifications/unread-count'))
            ->assertOk()
            ->assertJsonFragment(['unread_count' => 1]);
    }
}
