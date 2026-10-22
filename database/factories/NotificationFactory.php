<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => function () {
                return User::factory()->create()->id;
            },
            'data' => ['test' => 'data'],
            'read_at' => null,
        ];
    }

    /**
     * Configure the factory.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterMaking(function (Notification $notification) {
            // If the notifiable_id is set and is a user, set the tenant_id to the user's tenant_id
            if ($notification->notifiable_type === User::class && $notification->notifiable_id) {
                $user = User::find($notification->notifiable_id);
                if ($user) {
                    $notification->tenant_id = $user->tenant_id;
                }
            }
        })->afterCreating(function (Notification $notification) {
            // Same as afterMaking but for created models
            if ($notification->notifiable_type === User::class && $notification->notifiable_id) {
                $user = User::find($notification->notifiable_id);
                if ($user) {
                    $notification->tenant_id = $user->tenant_id;
                    $notification->save();
                }
            }
        });
    }
}
