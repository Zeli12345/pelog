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

    public function test_production_seeder_membuat_password_acak_tanpa_akun_contoh(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new AdminUserSeeder)->run();

        $admin = User::query()->where('email', 'admin@balilog.local')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdminIt());
        $this->assertTrue($admin->is_active);
        $this->assertFalse(
            Hash::check('Balilog!Admin2026', $admin->password),
            'Seeder produksi tidak boleh memakai password contoh yang ada di repositori.'
        );

        // Akun guru contoh tidak boleh ikut dibuat di produksi.
        $this->assertNull(User::query()->where('email', 'guru@balilog.local')->first());
    }

    public function test_seeder_ulang_tidak_mereset_password_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new AdminUserSeeder)->run();

        $before = User::query()->where('email', 'admin@balilog.local')->firstOrFail()->password;

        (new AdminUserSeeder)->run();

        $after = User::query()->where('email', 'admin@balilog.local')->firstOrFail()->password;

        $this->assertSame($before, $after, 'Menjalankan seeder ulang tidak boleh mengubah password admin.');
    }
}
