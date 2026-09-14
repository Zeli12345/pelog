<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['code' => 'PWPB', 'name' => 'Pemrograman Web & Perangkat Bergerak'],
            ['code' => 'PBO', 'name' => 'Pemrograman Berorientasi Objek'],
            ['code' => 'BD', 'name' => 'Basis Data'],
            ['code' => 'PPL', 'name' => 'Pemodelan Perangkat Lunak'],
            ['code' => 'JK', 'name' => 'Jaringan Komputer'],
            ['code' => 'ASJ', 'name' => 'Administrasi Sistem Jaringan'],
            ['code' => 'TJBL', 'name' => 'Teknologi Jaringan Berbasis Luas'],
            ['code' => 'KK', 'name' => 'Komputasi Awan'],
            ['code' => 'MTK', 'name' => 'Matematika'],
            ['code' => 'BIN', 'name' => 'Bahasa Indonesia'],
            ['code' => 'BIG', 'name' => 'Bahasa Inggris'],
            ['code' => 'PPKN', 'name' => 'Pendidikan Pancasila & Kewarganegaraan'],
            ['code' => 'SEJ', 'name' => 'Sejarah Indonesia'],
            ['code' => 'PJOK', 'name' => 'PJOK'],
            ['code' => 'SB', 'name' => 'Seni Budaya'],
            ['code' => 'PKK', 'name' => 'Projek Kreatif & Kewirausahaan'],
        ];

        foreach ($subjects as $subject) {
            Subject::query()->updateOrCreate(
                ['code' => $subject['code']],
                ['name' => $subject['name'], 'is_active' => true],
            );
        }
    }
}
