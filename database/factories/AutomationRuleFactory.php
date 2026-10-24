<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'triggers' => ['campaign.created'],
            'trigger_filters' => null,
            'conditions' => [[
                'field' => 'status',
                'operator' => 'equals',
                'value' => 'active',
            ]],
            'condition_logic' => 'AND',
            'actions' => [[
                'type' => 'notify',
                'params' => ['channel' => 'email'],
            ]],
            'priority' => 0,
            'is_active' => true,
            'execution_count' => 0,
        ];
    }

    /**
     * Indicate that the rule is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
