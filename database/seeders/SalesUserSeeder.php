<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SalesUserSeeder extends Seeder
{
    public function run(): void
    {
        $salesUsers = [
            ['name' => 'Sarah Youssef', 'email' => 'sarah@pyramidth.com'],
            ['name' => 'Omar Khaled', 'email' => 'omar@pyramidth.com'],
        ];

        foreach ($salesUsers as $data) {
            User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Sales,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
