<?php

namespace Database\Seeders;

use App\Enums\ContactableType;
use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample Leads/Clients/Contacts/Agents for local development only.
 * Never run this against a production database.
 */
class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping DevelopmentDataSeeder in production.');

            return;
        }

        $salesUsers = User::where('role', UserRole::Sales)->get();

        if ($salesUsers->isEmpty()) {
            return;
        }

        Agent::factory()->count(5)->create();

        Lead::factory()
            ->count(15)
            ->create(['assigned_to' => fn () => $salesUsers->random()->id]);

        Client::factory()
            ->count(6)
            ->create(['user_id' => fn () => $salesUsers->random()->id])
            ->each(function (Client $client) {
                Contact::factory()->create([
                    'contactable_type' => ContactableType::Client->value,
                    'contactable_id' => $client->id,
                    'is_primary' => true,
                ]);
            });
    }
}
