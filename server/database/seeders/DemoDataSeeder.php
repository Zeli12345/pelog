<?php

namespace Database\Seeders;

use App\Models\StaffMember;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan (local).
 * Data asli siswa/guru diimpor lewat dashboard (Excel/CSV).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $classes = ['X RPL 1', 'X RPL 2', 'X TKJ 1', 'XI RPL 1', 'XI TKJ 1', 'XII RPL 1'];

        for ($i = 1; $i <= 24; $i++) {
            Student::query()->updateOrCreate(
                ['nisn' => sprintf('00%08d', $i)],
                [
                    'name' => fake('id_ID')->name(),
                    'class' => $classes[($i - 1) % count($classes)],
                    'is_active' => true,
                ],
            );
        }

        $staff = [
            ['nip_id' => '198501152010011001', 'name' => 'I Komang Purwata, S.Pd.', 'role' => 'teacher'],
            ['nip_id' => '199002202015012002', 'name' => 'Ni Made Ariani, S.Kom.', 'role' => 'teacher'],
            ['nip_id' => '198807102012011003', 'name' => 'I Wayan Sudiarta', 'role' => 'staff'],
        ];

        foreach ($staff as $person) {
            StaffMember::query()->updateOrCreate(
                ['nip_id' => $person['nip_id']],
                [
                    'name' => $person['name'],
                    'role' => $person['role'],
                    'is_active' => true,
                ],
            );
        }
    }
}
