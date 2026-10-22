<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['todo', 'in_progress', 'review', 'completed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
            'assigned_to' => $user->id,
            'tenant_id' => $user->tenant_id,
        ];
    }
}
