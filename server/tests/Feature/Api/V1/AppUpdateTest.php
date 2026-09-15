<?php

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_latest_reports_unavailable_when_no_release(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->getJson('/api/v1/app/latest', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_latest_reports_release_with_hash(): void
    {
        [$device, $token] = $this->enrolledDevice();

        Storage::disk('local')->put('releases/BALI-LOG_Setup_1.1.0.exe', 'installer-bytes');

        Setting::setValue('app_version', '1.1.0');
        Setting::setValue('app_installer_file', 'releases/BALI-LOG_Setup_1.1.0.exe');
        Setting::setValue('app_installer_sha256', hash('sha256', 'installer-bytes'));
        Setting::setValue('app_installer_size', strlen('installer-bytes'));

        $this->getJson('/api/v1/app/latest', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.version', '1.1.0')
            ->assertJsonPath('data.sha256', hash('sha256', 'installer-bytes'))
            ->assertJsonPath('data.url', url('/api/v1/app/installer'));
    }

    public function test_installer_can_be_downloaded_with_device_token(): void
    {
        [$device, $token] = $this->enrolledDevice();

        Storage::disk('local')->put('releases/BALI-LOG_Setup_1.1.0.exe', 'installer-bytes');
        Setting::setValue('app_installer_file', 'releases/BALI-LOG_Setup_1.1.0.exe');

        $response = $this->get('/api/v1/app/installer', $this->deviceHeaders($token));

        $response->assertOk();
        $this->assertSame('installer-bytes', $response->streamedContent());
    }

    public function test_installer_download_requires_device_token(): void
    {
        $this->getJson('/api/v1/app/installer')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_missing');
    }

    public function test_download_returns_404_without_release(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->getJson('/api/v1/app/installer', $this->deviceHeaders($token))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'release_not_found');
    }
}
