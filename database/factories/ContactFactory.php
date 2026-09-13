<?php

namespace Database\Factories;

use App\Enums\ContactableType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contactable_type' => ContactableType::Client->value,
            'contactable_id' => 1,
            'name' => fake()->name(),
            'job_title' => fake()->randomElement(['Logistics Manager', 'Procurement Manager', 'CEO']),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_primary' => false,
        ];
    }
}
