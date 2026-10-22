<?php

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class UpdateGateApiTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_version_endpoint_mengirim_informasi_pembaruan(): void
    {
        Setting::setValue('app_version', '1.0.3');
        Setting::setValue('app_installer_file', 'releases/PELOG_Setup_1.0.3.exe');
        Setting::setValue('app_installer_sha256', str_repeat('a', 64));
        Setting::setValue('app_installer_size', 123);
        Setting::setValue('app_update_mandatory', true);
        Setting::setValue('min_supported_version', '1.0.2');

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('releases/PELOG_Setup_1.0.3.exe', 'dummy');

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('data.latest_version', '1.0.3')
            ->assertJsonPath('data.min_supported_version', '1.0.2')
            ->assertJsonPath('data.mandatory', true)
            ->assertJsonPath('data.size', 123);
    }

    public function test_sesi_ditolak_jika_versi_agent_di_bawah_minimum(): void
    {
        Setting::setValue('min_supported_version', '9.9.9');

        [$device, $token] = $this->enrolledDevice(['agent_version' => '1.0.0']);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '1234567890',
            'usage_purpose' => 'Uji gate',
        ], $this->deviceHeaders($token))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'update_required');
    }
}
