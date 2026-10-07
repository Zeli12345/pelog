<?php

namespace Tests\Feature\Dashboard;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_kode_menyimpan_plaintext_dan_hash_yang_cocok(): void
    {
        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)
            ->post('/settings/enrollment-code')
            ->assertRedirect(route('settings.index'));

        $code = Setting::getValue('enrollment_code');

        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^BLG-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        $this->assertSame(hash('sha256', $code), Setting::getValue('enrollment_code_hash'));
    }

    public function test_halaman_pengaturan_menampilkan_kode_yang_tersimpan(): void
    {
        Setting::setValue('enrollment_code', 'BLG-TEST-1234');
        Setting::setValue('enrollment_code_hash', hash('sha256', 'BLG-TEST-1234'));

        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk()
            ->assertSee('BLG-TEST-1234')
            ->assertSee('Salin');
    }

    public function test_command_enrollment_code_menyimpan_plaintext(): void
    {
        $this->artisan('pelog:enrollment-code')->assertSuccessful();

        $code = Setting::getValue('enrollment_code');

        $this->assertIsString($code);
        $this->assertSame(hash('sha256', $code), Setting::getValue('enrollment_code_hash'));
    }
}
