<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Client>
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'tenant_id' => 1, // Will be overridden in tests
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->randomNumber(4),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => 'https://' . $this->faker->domainName(),
            'industry' => $this->faker->randomElement(['Technology', 'Healthcare', 'Finance', 'Retail', 'Education', 'Real Estate']),
            'description' => $this->faker->paragraph(),
            'branding' => [
                'logo_url' => $this->faker->imageUrl(200, 200),
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'font_family' => $this->faker->randomElement(['Inter', 'Roboto', 'Open Sans', 'Montserrat']),
            ],
            'social_accounts' => [],
            'ad_accounts' => [],
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => $this->faker->randomElement(['active', 'onboarded', 'paused', 'archived']),
            'contract_start_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'contract_end_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'monthly_budget' => $this->faker->randomFloat(2, 1000, 50000),
            'billing_details' => [
                'address' => $this->faker->address(),
                'tax_id' => $this->faker->numerify('##-#######'),
                'payment_terms' => 'Net 30',
            ],
            'account_manager_id' => null, // Will be set in tests
            'team_members' => [],
            'settings' => [
                'auto_reporting' => true,
                'report_frequency' => 'weekly',
            ],
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the client is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the client is onboarded.
     */
    public function onboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'onboarded',
        ]);
    }
}
