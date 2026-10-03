<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun guru dashboard (read-only) untuk login dengan NIP/akun.
 * Dibuat bersama AdminUserSeeder agar instalasi baru langsung memiliki
 * akun admin & guru dengan kredensial awal yang terdokumentasi.
 */
class GuruUserSeeder extends Seeder
{
    /**
     * Password awal akun guru bila PELOG_GURU_PASSWORD tidak diset.
     * GANTI setelah instalasi pertama.
     */
    public const DEFAULT_PASSWORD = 'Pelog!Guru2026';

    public function run(): void
    {
        $email = trim((string) env('PELOG_GURU_EMAIL', 'guru@pelog.local'));
        $email = $email !== '' ? $email : 'guru@pelog.local';

        $envPassword = trim((string) env('PELOG_GURU_PASSWORD', ''));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $attributes = [
                'role' => 'guru',
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ];

            if ($envPassword !== '') {
                $attributes['password'] = $envPassword;
            }

            $existing->forceFill($attributes)->save();

            if ($envPassword !== '') {
                $this->command?->info('  Password guru diperbarui dari PELOG_GURU_PASSWORD.');
            }

            return;
        }

        User::query()->create([
            'email' => $email,
            'name' => 'Guru',
            'password' => $envPassword !== '' ? $envPassword : self::DEFAULT_PASSWORD,
            'role' => 'guru',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($envPassword === '') {
            $this->command?->warn('  Akun guru dibuat dengan password awal: '.self::DEFAULT_PASSWORD);
            $this->command?->warn('  Segera ganti password (dashboard > Profil) atau set PELOG_GURU_PASSWORD lalu jalankan ulang seeder.');
        }
    }
}
