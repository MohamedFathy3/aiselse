<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'company_name' => $company,
            'website' => 'https://www.'.Str::slug($company).'.com',
            'country' => fake()->randomElement(['Germany', 'Egypt', 'United Arab Emirates', 'Netherlands']),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'industry' => fake()->randomElement(['Manufacturing', 'Import/Export', 'Retail']),
            'description' => fake()->catchPhrase(),
            'status' => ClientStatus::Active,
            'user_id' => User::factory()->sales(),
            'normalized_company_name' => Str::slug($company),
            'company_domain' => Str::slug($company).'.com',
        ];
    }
}
