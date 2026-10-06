<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Setting;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_produksi_hanya_membuat_akun_awal_dan_pengaturan(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new DatabaseSeeder)->run();

        $this->assertSame(1, User::query()->count(), 'Produksi hanya boleh punya satu akun admin utama.');
        $admin = User::query()->where('email', 'admin@pelog.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdminUtama());
        $this->assertSame('Admin', $admin->role->label());

        $this->assertSame(0, Student::query()->withTrashed()->count(), 'Tidak ada siswa contoh.');
        $this->assertSame(0, StaffMember::query()->withTrashed()->count(), 'Tidak ada guru/staf contoh.');
        $this->assertSame(0, Device::query()->withTrashed()->count(), 'Tidak ada perangkat contoh.');
        $this->assertSame(0, Subject::query()->count(), 'Mapel bawaan tidak di-seed di produksi.');

        $this->assertNotNull(Setting::getValue('school_name'), 'Pengaturan default tetap terisi.');
        $this->assertSame('1.0.0', Setting::getValue('app_version'));
    }
}
