<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceAdoptionTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'BLG-TEST-ADOPT';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('enrollment_code_hash', hash('sha256', self::CODE));
    }

    public function test_device_can_be_adopted_after_reimage_when_old_device_inactive(): void
    {
        $old = Device::factory()->create([
            'hostname' => 'LAB-REIMAGE-01',
            'uuid' => (string) Str::uuid(),
            'last_seen_at' => now()->subHours(5),
        ]);

        $newUuid = (string) Str::uuid();

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $newUuid,
            'hostname' => 'LAB-REIMAGE-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.device.uuid', $newUuid);

        $old->refresh();

        $this->assertSame($newUuid, $old->uuid, 'Perangkat lama harus diadopsi (uuid diganti).');
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_adopted']);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_adoption_rejected_when_old_device_still_active(): void
    {
        $old = Device::factory()->create([
            'hostname' => 'LAB-ACTIVE-01',
            'last_seen_at' => now(),
        ]);

        Student::factory()->create(['nisn' => '0051234500']);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '0051234500',
            'usage_purpose' => 'Sesi aktif menahan adopsi',
        ], $this->deviceHeadersFor($old))
            ->assertStatus(201);

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-ACTIVE-01',
        ])->assertStatus(409)->assertJsonPath('error.code', 'hostname_taken');
    }

    private function deviceHeadersFor(Device $device): array
    {
        $token = bin2hex(random_bytes(32));
        $device->forceFill(['device_token_hash' => hash('sha256', $token)])->save();

        return ['Authorization' => 'Bearer '.$token];
    }
}
