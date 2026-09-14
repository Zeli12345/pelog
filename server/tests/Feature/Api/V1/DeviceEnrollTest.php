<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceEnrollTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'BLG-TEST-1234';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('enrollment_code_hash', hash('sha256', self::CODE));
    }

    public function test_device_can_enroll_with_valid_code(): void
    {
        $uuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'LAB-BL-01',
            'mac_list' => ['AA:BB:CC:DD:EE:01'],
            'device_type' => 'laptop',
            'agent_version' => '1.0.0',
            'windows_version' => 'Windows 10 Pro 22H2',
            'storage_total_gb' => 256,
            'storage_used_gb' => 112,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.device.hostname', 'LAB-BL-01')
            ->assertJsonStructure(['data' => ['device_token', 'config' => ['pin_length', 'screenshot_enabled']]]);

        $token = $response->json('data.device_token');

        $device = Device::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($device);
        $this->assertSame(hash('sha256', $token), $device->device_token_hash);
        $this->assertNotNull($device->enrolled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_enrolled']);
    }

    public function test_enroll_rejected_when_code_disabled(): void
    {
        Setting::setValue('enrollment_code_hash', null);

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-02',
        ])->assertStatus(503)->assertJsonPath('error.code', 'enrollment_disabled');
    }

    public function test_enroll_rejected_with_wrong_code(): void
    {
        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => 'BLG-SALAH-0000',
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-03',
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_enrollment_code');
    }

    public function test_enroll_rejected_when_hostname_taken_by_other_device(): void
    {
        Device::factory()->create(['hostname' => 'LAB-BL-04']);

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-04',
        ])->assertStatus(409)->assertJsonPath('error.code', 'hostname_taken');
    }

    public function test_re_enroll_rotates_token(): void
    {
        $uuid = (string) Str::uuid();

        $first = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'LAB-BL-05',
        ])->assertOk()->json('data.device_token');

        $second = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'LAB-BL-05',
        ])->assertOk()->json('data.device_token');

        $this->assertNotSame($first, $second);

        $device = Device::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(hash('sha256', $second), $device->device_token_hash);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_enroll_validates_payload(): void
    {
        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => 'bukan-uuid',
            'hostname' => '',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }
}
