<?php

namespace Tests\Feature\Api\V1;

use App\Models\Student;
use App\Models\UsageSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_sync_processes_items_individually(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $validUuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/sync/sessions', [
            'sessions' => [
                [
                    'session_uuid' => $validUuid,
                    'user_type' => 'student',
                    'nisn' => '0051234567',
                    'usage_purpose' => 'Sesi offline',
                    'started_at_client' => now()->subHour()->toIso8601String(),
                    'ended_at_client' => now()->subMinutes(30)->toIso8601String(),
                    'close_reason' => 'recovery',
                    'student_feedback' => 'Selesai latihan soal.',
                    'comprehension_level' => 'cukup',
                ],
                [
                    'session_uuid' => (string) Str::uuid(),
                    'user_type' => 'student',
                    'nisn' => '0051234567',
                    // usage_purpose sengaja hilang -> harus jadi error per-item
                ],
            ],
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'created')
            ->assertJsonPath('data.results.1.status', 'error');

        $this->assertDatabaseHas('usage_sessions', [
            'session_uuid' => $validUuid,
            'sync_source' => 'offline',
            'close_reason' => 'recovery',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'session_synced_offline']);
    }

    public function test_sync_is_idempotent_for_existing_session(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();

        $payload = [
            'sessions' => [[
                'session_uuid' => $uuid,
                'user_type' => 'student',
                'nisn' => '0051234567',
                'usage_purpose' => 'Sesi offline',
                'started_at_client' => now()->subHour()->toIso8601String(),
                'ended_at_client' => now()->toIso8601String(),
                'close_reason' => 'normal',
            ]],
        ];

        $this->postJson('/api/v1/sync/sessions', $payload, $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'created');

        $this->postJson('/api/v1/sync/sessions', $payload, $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'skipped');

        $this->assertDatabaseCount('usage_sessions', 1);
    }

    public function test_sync_rejects_unknown_student(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->postJson('/api/v1/sync/sessions', [
            'sessions' => [[
                'session_uuid' => (string) Str::uuid(),
                'user_type' => 'student',
                'nisn' => '9999999999',
                'usage_purpose' => 'Sesi offline',
            ]],
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'error');
    }

    public function test_sync_validates_payload_shape(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->postJson('/api/v1/sync/sessions', ['sessions' => 'bukan-array'], $this->deviceHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_sync_applies_reflection_to_session_closed_server_side(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $student = Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $uuid = (string) Str::uuid();
        $startedAt = now()->subHour();
        $closedAt = now()->subMinutes(10);
        $endedAt = now()->subMinutes(30);

        // Sesi ditutup server lebih dulu (mis. oleh balilog:close-stale-sessions)
        // dan belum sempat menyimpan refleksi dari client.
        UsageSession::query()->create([
            'session_uuid' => $uuid,
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Sesi offline ditutup server',
            'started_at_client' => $startedAt,
            'closed_at' => $closedAt,
            'close_reason' => 'recovery',
            'duration_minutes' => 50,
        ]);

        $this->postJson('/api/v1/sync/sessions', [
            'sessions' => [[
                'session_uuid' => $uuid,
                'user_type' => 'student',
                'nisn' => '0051234567',
                'usage_purpose' => 'Sesi offline ditutup server',
                'started_at_client' => $startedAt->toIso8601String(),
                'ended_at_client' => $endedAt->toIso8601String(),
                'close_reason' => 'normal',
                'student_feedback' => 'Refleksi telat dari client.',
                'comprehension_level' => 'paham',
            ]],
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'skipped');

        $session = UsageSession::query()->where('session_uuid', $uuid)->firstOrFail();

        $this->assertSame('Refleksi telat dari client.', $session->student_feedback);
        $this->assertSame('paham', $session->comprehension_level->value);
        $this->assertSame(
            $closedAt->toDateTimeString(),
            $session->closed_at->toDateTimeString(),
            'closed_at server tidak boleh diubah.',
        );
        $this->assertSame(30, $session->duration_minutes);
    }
}
