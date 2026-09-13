<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'company_name' => $company,
            'website' => 'https://www.'.Str::slug($company).'.com',
            'country' => fake()->randomElement(['Germany', 'Egypt', 'United Arab Emirates', 'Netherlands', 'Saudi Arabia']),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'industry' => fake()->randomElement(['Manufacturing', 'Import/Export', 'Retail', 'Automotive', 'Electronics']),
            'description' => fake()->catchPhrase(),
            'contact_name' => fake()->name(),
            'contact_title' => fake()->randomElement(['Logistics Manager', 'Procurement Manager', 'Operations Director']),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'source' => 'manual',
            'lead_score' => fake()->numberBetween(30, 95),
            'shipping_relevance' => fake()->randomElement(['high', 'medium', 'low']),
            'potential_need' => fake()->randomElement(['Ocean freight / import', 'Air freight / export', 'Customs clearance']),
            'status' => fake()->randomElement(LeadStatus::cases()),
            'assigned_to' => User::factory()->sales(),
            'normalized_company_name' => Str::slug($company),
            'company_domain' => Str::slug($company).'.com',
        ];
    }
}
