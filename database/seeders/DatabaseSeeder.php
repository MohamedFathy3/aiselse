<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds development data only. Production deployments should run
     * ProductionSeeder (admin account only) via `php artisan db:seed --class=ProductionSeeder`.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SalesUserSeeder::class,
            DevelopmentDataSeeder::class,
        ]);
    }
}
