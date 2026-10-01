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

    public function test_re_enroll_does_not_reactivate_device_disabled_by_admin(): void
    {
        $device = Device::factory()->create([
            'hostname' => 'LAB-BL-06',
            'is_active' => false,
        ]);

        $token = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $device->uuid,
            'hostname' => 'LAB-BL-06',
        ])
            ->assertOk()
            ->assertJsonPath('data.device.uuid', $device->uuid)
            ->json('data.device_token');

        $device->refresh();

        $this->assertFalse($device->is_active, 'Re-enroll tidak boleh mengaktifkan ulang perangkat.');
        $this->assertSame(hash('sha256', $token), $device->device_token_hash);

        // Token hasil re-enroll tetap tidak berguna selama admin menonaktifkan perangkat.
        $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_invalid');
    }

    public function test_re_enroll_after_delete_restores_device_by_hostname(): void
    {
        $old = Device::factory()->create([
            'hostname' => 'LAB-WIPE-01',
            'device_token_hash' => hash('sha256', 'token-lama'),
        ]);

        $old->delete();
        $newUuid = (string) Str::uuid();

        $token = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $newUuid,
            'hostname' => 'LAB-WIPE-01',
        ])->assertOk()->json('data.device_token');

        $device = Device::query()->where('hostname', 'LAB-WIPE-01')->firstOrFail();

        $this->assertSame($old->id, $device->id, 'Baris lama harus dipulihkan, bukan membuat baris baru.');
        $this->assertSame($newUuid, $device->uuid);
        $this->assertNull($device->deleted_at);
        $this->assertSame(hash('sha256', $token), $device->device_token_hash);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_reenrolled']);

        // Token baru langsung bisa dipakai — laptop hasil wipe bekerja end to end.
        $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_re_enroll_after_delete_with_same_uuid_restores_device(): void
    {
        $old = Device::factory()->create(['hostname' => 'LAB-WIPE-02']);
        $old->delete();

        $token = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $old->uuid,
            'hostname' => 'LAB-WIPE-02',
        ])->assertOk()->json('data.device_token');

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'id' => $old->id,
            'deleted_at' => null,
            'device_token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_re_enroll_after_delete_keeps_device_disabled_by_admin(): void
    {
        $device = Device::factory()->create([
            'hostname' => 'LAB-WIPE-03',
            'is_active' => false,
        ]);
        $device->delete();

        $token = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => $device->uuid,
            'hostname' => 'LAB-WIPE-03',
        ])->assertOk()->json('data.device_token');

        $this->assertFalse($device->refresh()->is_active);

        $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_invalid');
    }

    public function test_enroll_rejects_mac_list_longer_than_32_entries(): void
    {
        $macs = [];

        for ($i = 0; $i <= 32; $i++) {
            $macs[] = sprintf('AA:BB:CC:DD:EE:%02X', $i);
        }

        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-07',
            'mac_list' => $macs,
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }

    public function test_enroll_rejects_non_string_mac_list_entries(): void
    {
        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-08',
            'mac_list' => ['AA:BB:CC:DD:EE:01', 12345],
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }

    public function test_enroll_menyimpan_label_dan_lokasi_kustom(): void
    {
        $response = $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-09',
            'label' => 'LAB-BL-09 Kustom',
            'location' => 'Lab RPL 1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.device.label', 'LAB-BL-09 Kustom');

        $this->assertDatabaseHas('devices', [
            'hostname' => 'LAB-BL-09',
            'label' => 'LAB-BL-09 Kustom',
            'location_label' => 'Lab RPL 1',
        ]);
    }

    public function test_enroll_menolak_lokasi_lebih_dari_60_karakter(): void
    {
        $this->postJson('/api/v1/devices/enroll', [
            'enrollment_code' => self::CODE,
            'device_uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-BL-10',
            'location' => str_repeat('x', 61),
        ])->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }
}
