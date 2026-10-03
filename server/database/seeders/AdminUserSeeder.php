<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Password awal akun admin bila PELOG_ADMIN_PASSWORD tidak diset.
     * GANTI setelah instalasi pertama (dashboard > Profil, atau set
     * PELOG_ADMIN_PASSWORD lalu jalankan ulang seeder ini).
     */
    public const DEFAULT_PASSWORD = 'Pelog!Admin2026';

    public function run(): void
    {
        $email = trim((string) env('PELOG_ADMIN_EMAIL', 'admin@pelog.local'));
        $email = $email !== '' ? $email : 'admin@pelog.local';

        $envPassword = trim((string) env('PELOG_ADMIN_PASSWORD', ''));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // Jangan pernah menimpa password yang sudah diubah admin. Rotasi hanya
            // bila PELOG_ADMIN_PASSWORD diberikan secara eksplisit (mis. reset).
            $attributes = [
                'name' => 'Admin IT PELOG',
                'role' => 'admin_it',
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ];

            if ($envPassword !== '') {
                $attributes['password'] = $envPassword;
            }

            $existing->forceFill($attributes)->save();

            if ($envPassword !== '') {
                $this->command?->info('  Password admin diperbarui dari PELOG_ADMIN_PASSWORD.');
            }

            return;
        }

        User::query()->create([
            'email' => $email,
            'name' => 'Admin IT PELOG',
            'password' => $envPassword !== '' ? $envPassword : self::DEFAULT_PASSWORD,
            'role' => 'admin_it',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($envPassword === '') {
            $this->command?->warn('  Akun admin dibuat dengan password awal: '.self::DEFAULT_PASSWORD);
            $this->command?->warn('  Segera ganti password (dashboard > Profil) atau set PELOG_ADMIN_PASSWORD lalu jalankan ulang seeder.');
        }
    }
}
