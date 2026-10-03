<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder produksi HANYA membuat akun awal (admin + guru) dan nilai
     * pengaturan default. Tidak ada data contoh di produksi.
     *
     * Data siswa/guru/staf diimpor lewat dashboard; mata pelajaran dikelola
     * dari menu Mapel. Seed mata pelajaran bawaan + data demo hanya untuk
     * pengembangan lokal.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            GuruUserSeeder::class,
            SettingsSeeder::class,
        ]);

        // Data bawaan/contoh hanya untuk pengembangan lokal.
        if (app()->environment('local')) {
            $this->call([
                SubjectSeeder::class,
                DemoDataSeeder::class,
            ]);
        }
    }
}
