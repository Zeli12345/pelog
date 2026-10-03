<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\GuruUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_akun_guru_dengan_password_awal_default(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new GuruUserSeeder)->run();

        $guru = User::query()->where('email', 'guru@pelog.local')->first();

        $this->assertNotNull($guru);
        $this->assertSame('guru', $guru->role->value);
        $this->assertTrue($guru->is_active);
        $this->assertTrue(
            Hash::check(GuruUserSeeder::DEFAULT_PASSWORD, $guru->password),
            'Instalasi baru memakai password awal yang terdokumentasi (wajib diganti setelah instalasi).'
        );
    }

    public function test_seeder_ulang_tidak_mereset_password_guru(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new GuruUserSeeder)->run();

        $guru = User::query()->where('email', 'guru@pelog.local')->firstOrFail();
        $guru->forceFill(['password' => 'PasswordGuruSudahDiganti!'])->save();

        (new GuruUserSeeder)->run();

        $guru->refresh();

        $this->assertTrue(
            Hash::check('PasswordGuruSudahDiganti!', $guru->password),
            'Menjalankan seeder ulang tidak boleh mengubah password guru yang sudah diganti.'
        );
    }
}
