<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company().' Logistics',
            'country' => fake()->randomElement(['China', 'Singapore', 'United States', 'Netherlands']),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'website' => fake()->url(),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'services' => fake()->randomElements(['ocean', 'air', 'inland', 'customs_clearance'], 2),
            'status' => 'active',
        ];
    }
}
