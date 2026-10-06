<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\ClientDownloadController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class SettingsInstallerUploadTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_unggahan_installer_dashboard_langsung_terpublikasi_ke_agen_kiosk(): void
    {
        Storage::fake('local');

        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)
            ->post(route('settings.client-installer'), [
                'client_latest_version' => '1.2.3',
                'client_installer' => UploadedFile::fake()->create('PELOG_Setup.exe', 128, 'application/octet-stream'),
                'client_update_notes' => 'Uji unggah dari dashboard',
            ])
            ->assertRedirect(route('settings.index'));

        // Berkas tersedia untuk unduhan manual...
        Storage::disk('local')->assertExists(ClientDownloadController::INSTALLER_PATH);

        // ...dan sekaligus terpublikasi sebagai rilis auto-update.
        Storage::disk('local')->assertExists('releases/PELOG_Setup_1.2.3.exe');
        $this->assertSame('1.2.3', Setting::getValue('app_version'));
        $this->assertSame('releases/PELOG_Setup_1.2.3.exe', Setting::getValue('app_installer_file'));
        $this->assertTrue((bool) Setting::getValue('app_updater_enabled'));

        // Agen kiosk melihat rilis tersebut sebagai tersedia.
        [$device, $token] = $this->enrolledDevice();

        $this->getJson('/api/v1/app/latest', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.version', '1.2.3')
            ->assertJsonPath('data.notes', 'Uji unggah dari dashboard');
    }

    public function test_halaman_pengaturan_menampilkan_tombol_unduh_installer(): void
    {
        $admin = User::factory()->adminUtama()->create();

        // Sebelum ada installer: halaman tetap tampil dengan ajakan unggah.
        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Belum ada installer yang dipublikasikan');

        Setting::setValue('client_latest_version', '1.0.0');
        Setting::setValue('client_installer_sha256', str_repeat('a', 64));
        Setting::setValue('client_installer_size', 54385977);
        Setting::setValue('client_installer_uploaded_at', now()->toIso8601String());
        Setting::setValue('client_update_notes', 'Installer produksi PELOG 1.0.0.');

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Unduh Installer (PELOG_Setup.exe)')
            ->assertSee(route('client.download'), false)
            ->assertSee('51.9 MB');
    }
}
