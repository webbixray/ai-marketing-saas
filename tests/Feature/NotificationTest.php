<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_retrieve_their_notifications()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Create a few notifications for the user
        Notification::factory()->count(3)->create([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
        ]);

        // Create a notification for another user (should not be visible)
        $otherUser = User::factory()->create();
        Notification::factory()->create([
            'notifiable_type' => get_class($otherUser),
            'notifiable_id' => $otherUser->id,
        ]);

        $response = $this->getJson($this->apiUrl('/notifications'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'notifications.data');
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $notification = Notification::factory()->create([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
        ]);

        $response = $this->postJson($this->apiUrl("/notifications/{$notification->id}/read"));

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        Notification::factory()->count(3)->create([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
        ]);

        $response = $this->postJson($this->apiUrl('/notifications/read-all'));

        $response->assertStatus(200);

        $this->assertDatabaseCount('notifications', 3);
        $this->assertEquals(0, \App\Models\Notification::where('read_at', null)->count());
    }

    public function test_user_can_get_unread_notification_count()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        Notification::factory()->count(3)->create([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
        ]);

        $response = $this->getJson($this->apiUrl('/notifications/unread-count'));

        $response->assertStatus(200);
        $response->assertJsonFragment(['unread_count' => 3]);
    }
}
