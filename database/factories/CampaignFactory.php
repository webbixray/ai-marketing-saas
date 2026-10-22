<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Sometimes null for type
        $type = fake()->randomElement([null, 'social_media', 'email', 'search', 'display', 'video']);

        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->company()),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['draft', 'active', 'paused', 'completed', 'cancelled']),
            'type' => $type,
            'budget' => fake()->randomFloat(2, 100, 10000),
            'spent' => fake()->randomFloat(2, 0, 10000),
            'start_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'end_date' => fake()->dateTimeBetween('now', '+3 months'),
            'targeting' => [
                'age' => fake()->randomElement(['18-24', '25-34', '35-44', '45+']),
                'interests' => fake()->randomElements(['sports', 'technology', 'food', 'travel'], 2),
            ],
            'settings' => [
                'frequency_cap' => fake()->randomElement([1, 2, 3, 5]),
                'bid_strategy' => fake()->randomElement(['cpc', 'cpm', 'cpa']),
            ],
        ];
    }
}
