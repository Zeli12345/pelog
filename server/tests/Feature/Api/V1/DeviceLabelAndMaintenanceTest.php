<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceLabelAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'BLG-TEST-LABEL';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('enrollment_code_hash', hash('sha256', self::CODE));
    }

    public function test_enrollment_stores_label_from_client(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'DESKTOP-NEW-01',
            'label' => 'LAB-BL-09',
        ])
            ->assertOk()
            ->assertJsonPath('data.device.label', 'LAB-BL-09');

        $this->assertDatabaseHas('devices', ['uuid' => $uuid, 'label' => 'LAB-BL-09']);
    }

    public function test_re_enrollment_keeps_existing_label_when_not_provided(): void
    {
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'DESKTOP-NEW-02',
            'label' => 'LAB-BL-10',
        ])->assertOk();

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $uuid,
            'hostname' => 'DESKTOP-NEW-02',
        ])->assertOk()->assertJsonPath('data.device.label', 'LAB-BL-10');
    }

    public function test_bootstrap_includes_device_info(): void
    {
        $device = Device::factory()->create(['label' => 'LAB-BL-07', 'status' => 'maintenance']);

        $this->getJson('/api/v1/bootstrap', $this->deviceHeadersFor($device))
            ->assertOk()
            ->assertJsonPath('data.device.label', 'LAB-BL-07')
            ->assertJsonPath('data.device.maintenance', true);
    }

    public function test_session_start_rejected_for_maintenance_device(): void
    {
        $device = Device::factory()->create(['status' => 'maintenance']);
        $student = Student::factory()->create();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => $student->nisn,
            'usage_purpose' => 'Coba mulai saat perawatan',
        ], $this->deviceHeadersFor($device))
            ->assertStatus(423)
            ->assertJsonPath('error.code', 'device_maintenance');

        $this->assertDatabaseCount('usage_sessions', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_blocked_maintenance']);
    }

    private function deviceHeadersFor(Device $device): array
    {
        $token = bin2hex(random_bytes(32));
        $device->forceFill(['device_token_hash' => hash('sha256', $token)])->save();

        return ['Authorization' => 'Bearer '.$token];
    }
}
