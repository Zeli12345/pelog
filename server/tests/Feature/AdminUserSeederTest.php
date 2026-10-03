<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_admin_dengan_password_awal_default(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new AdminUserSeeder)->run();

        $admin = User::query()->where('email', 'admin@pelog.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdminIt());
        $this->assertTrue($admin->is_active);
        $this->assertTrue(
            Hash::check(AdminUserSeeder::DEFAULT_PASSWORD, $admin->password),
            'Instalasi baru memakai password awal yang terdokumentasi (wajib diganti setelah instalasi).'
        );
    }

    public function test_seeder_ulang_tidak_mereset_password_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new AdminUserSeeder)->run();

        $admin = User::query()->where('email', 'admin@pelog.local')->firstOrFail();
        $admin->forceFill(['password' => 'PasswordBaruYangSudahDiganti!'])->save();

        (new AdminUserSeeder)->run();

        $admin->refresh();

        $this->assertTrue(
            Hash::check('PasswordBaruYangSudahDiganti!', $admin->password),
            'Menjalankan seeder ulang tidak boleh mengubah password admin yang sudah diganti.'
        );
    }
}
