<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();

        // Akun guru contoh HANYA untuk pengembangan/pengujian lokal.
        // Jangan pernah dibuat di produksi (kredensial contoh = pintu belakang).
        if (app()->environment('local', 'testing')) {
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

    private function seedAdmin(): void
    {
        $email = trim((string) env('BALILOG_ADMIN_EMAIL', 'admin@balilog.local'));
        $email = $email !== '' ? $email : 'admin@balilog.local';

        $envPassword = (string) env('BALILOG_ADMIN_PASSWORD', '');
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // Jangan pernah menimpa password yang sudah diubah admin. Rotasi hanya
            // bila BALILOG_ADMIN_PASSWORD diberikan secara eksplisit (mis. reset).
            $attributes = [
                'name' => 'Admin IT BALI-LOG',
                'role' => 'admin_it',
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ];

            if ($envPassword !== '') {
                $attributes['password'] = $envPassword;
            }

            $existing->forceFill($attributes)->save();

            if ($envPassword !== '') {
                $this->command?->info('  Password admin diperbarui dari BALILOG_ADMIN_PASSWORD.');
            }

            return;
        }

        $password = $envPassword;
        $generated = false;

        if ($password === '') {
            if (app()->environment('production')) {
                $password = Str::password(20);
                $generated = true;
            } else {
                // Lingkungan lokal/pengujian: kredensial tetap agar mudah dipakai.
                $password = 'Balilog!Admin2026';
            }
        }

        User::query()->create([
            'email' => $email,
            'name' => 'Admin IT BALI-LOG',
            'password' => $password,
            'role' => 'admin_it',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($generated) {
            $this->command?->warn('  Akun admin dibuat dengan password ACAK (hanya ditampilkan sekali):');
            $this->command?->line('    Email    : '.$email);
            $this->command?->line('    Password : '.$password);
            $this->command?->warn('  Simpan sekarang, atau set BALILOG_ADMIN_PASSWORD lalu jalankan ulang seeder ini untuk merotasi.');
        }
    }
}
