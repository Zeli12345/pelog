<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@balilog.local'],
            [
                'name' => 'Admin IT BALI-LOG',
                'password' => 'Balilog!Admin2026',
                'role' => 'admin_it',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'guru@balilog.local'],
            [
                'name' => 'Guru Contoh',
                'password' => 'Balilog!Guru2026',
                'role' => 'guru',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
