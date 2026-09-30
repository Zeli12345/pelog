<?php

namespace Tests\Feature\Api\V1;

use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UsageSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class SessionLifecycleTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_student_can_start_session(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $student = Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);
        $subject = Subject::factory()->create();

        $payload = [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => $student->nisn,
            'subject_id' => $subject->id,
            'usage_purpose' => 'Mengerjakan praktikum HTML & CSS',
            'started_at_client' => now()->subMinute()->toIso8601String(),
            'storage_total_gb' => 256,
            'storage_used_gb' => 120,
        ];

        $this->postJson('/api/v1/sessions/start', $payload, $this->deviceHeaders($token))
            ->assertStatus(201)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.user_type', 'student')
            ->assertJsonPath('data.duration_minutes', 0);

        $this->assertDatabaseHas('usage_sessions', [
            'session_uuid' => $payload['session_uuid'],
            'user_type' => 'student',
            'student_id' => $student->id,
            'sync_source' => 'online',
        ]);

        $this->assertSame('in_use', $device->refresh()->status->value);
        $this->assertSame(120, $device->storage_used_gb);
    }

    public function test_start_is_idempotent_for_same_uuid(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $payload = [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Belajar mandiri',
        ];

        $this->postJson('/api/v1/sessions/start', $payload, $this->deviceHeaders($token))->assertStatus(201);
        $this->postJson('/api/v1/sessions/start', $payload, $this->deviceHeaders($token))->assertOk();

        $this->assertDatabaseCount('usage_sessions', 1);
    }

    public function test_device_cannot_have_two_active_sessions(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Sesi pertama',
        ], $this->deviceHeaders($token))->assertStatus(201);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Sesi kedua',
        ], $this->deviceHeaders($token))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'device_busy');
    }

    public function test_student_without_pin_cannot_start(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Coba',
        ], $this->deviceHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'pin_not_set');
    }

    public function test_staff_session_requires_no_pin(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $staff = StaffMember::factory()->create(['nip_id' => '198501152010011002']);

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => (string) Str::uuid(),
            'user_type' => 'staff',
            'nip_id' => $staff->nip_id,
            'usage_purpose' => 'Pemeliharaan perangkat lab',
        ], $this->deviceHeaders($token))
            ->assertStatus(201)
            ->assertJsonPath('data.user_type', 'staff');
    }

    public function test_heartbeat_and_end_flow(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum',
        ], $this->deviceHeaders($token))->assertStatus(201);

        $session = UsageSession::query()->where('session_uuid', $uuid)->firstOrFail();
        $firstHeartbeat = $session->last_heartbeat_at;

        $this->travel(2)->minutes();

        $this->postJson('/api/v1/sessions/heartbeat', [
            'session_uuid' => $uuid,
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertTrue($session->refresh()->last_heartbeat_at->greaterThan($firstHeartbeat));

        $this->travel(30)->minutes();

        $this->postJson('/api/v1/sessions/end', [
            'session_uuid' => $uuid,
            'close_reason' => 'normal',
            'student_feedback' => 'Belajar membuat layout dengan CSS Grid.',
            'comprehension_level' => 'paham',
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.close_reason', 'normal');

        $session->refresh();
        $this->assertFalse($session->isActive());
        $this->assertSame('paham', $session->comprehension_level->value);
        $this->assertGreaterThanOrEqual(30, $session->duration_minutes);
        $this->assertSame('available', $device->refresh()->status->value);

        // Idempoten: end kedua kali tetap sukses tanpa mengubah data.
        $this->postJson('/api/v1/sessions/end', [
            'session_uuid' => $uuid,
        ], $this->deviceHeaders($token))->assertOk();
    }

    public function test_heartbeat_after_close_reports_inactive(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum',
        ], $this->deviceHeaders($token));

        $this->postJson('/api/v1/sessions/end', ['session_uuid' => $uuid], $this->deviceHeaders($token));

        $this->postJson('/api/v1/sessions/heartbeat', ['session_uuid' => $uuid], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_session_of_other_device_is_not_accessible(): void
    {
        [$deviceA, $tokenA] = $this->enrolledDevice();
        [$deviceB, $tokenB] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum',
        ], $this->deviceHeaders($tokenA))->assertStatus(201);

        $this->postJson('/api/v1/sessions/heartbeat', [
            'session_uuid' => $uuid,
        ], $this->deviceHeaders($tokenB))->assertStatus(404);
    }

    public function test_inactive_device_token_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $device->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_invalid');
    }

    public function test_end_releases_in_use_device(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum singkat',
        ], $this->deviceHeaders($token))->assertStatus(201);

        $this->assertSame('in_use', $device->refresh()->status->value);

        $this->postJson('/api/v1/sessions/end', [
            'session_uuid' => $uuid,
            'close_reason' => 'normal',
        ], $this->deviceHeaders($token))->assertOk();

        $this->assertSame('available', $device->refresh()->status->value);
    }

    public function test_end_keeps_maintenance_device_status(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Sesi berjalan lalu masuk perawatan',
        ], $this->deviceHeaders($token))->assertStatus(201);

        // Admin menandai perangkat masuk perawatan saat sesi masih berjalan.
        $device->forceFill(['status' => 'maintenance'])->save();

        $this->postJson('/api/v1/sessions/end', [
            'session_uuid' => $uuid,
            'close_reason' => 'normal',
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertSame(
            'maintenance',
            $device->refresh()->status->value,
            'Menutup sesi tidak boleh mengembalikan status perawatan menjadi available.',
        );
    }

    public function test_heartbeat_response_no_longer_includes_commands(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum tanpa perintah server',
        ], $this->deviceHeaders($token))->assertStatus(201);

        $this->postJson('/api/v1/sessions/heartbeat', [
            'session_uuid' => $uuid,
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonMissingPath('data.commands');
    }
}
