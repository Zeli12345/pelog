<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SubjectSeeder::class,
            SettingsSeeder::class,
        ]);

        // Data contoh hanya untuk pengembangan lokal.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
