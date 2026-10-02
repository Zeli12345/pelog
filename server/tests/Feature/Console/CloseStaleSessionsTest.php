<?php

namespace Tests\Feature\Console;

use App\Models\Device;
use App\Models\Student;
use App\Models\UsageSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CloseStaleSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_session_is_closed_as_recovery_and_device_freed(): void
    {
        $device = Device::factory()->create(['status' => 'in_use', 'last_seen_at' => now()]);
        $student = Student::factory()->create();

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Sesi menggantung (laptop mati)',
            'started_at_server' => now()->subHour(),
            'last_heartbeat_at' => now()->subMinutes(30),
        ]);

        $this->artisan('pelog:close-stale-sessions')->assertSuccessful();

        $session->refresh();

        $this->assertNotNull($session->closed_at);
        $this->assertSame('recovery', $session->close_reason->value);
        $this->assertGreaterThanOrEqual(30, $session->duration_minutes);
        $this->assertSame('available', $device->refresh()->status->value);
    }

    public function test_fresh_session_is_not_closed(): void
    {
        $device = Device::factory()->create(['status' => 'in_use']);
        $student = Student::factory()->create();

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Sesi sehat',
            'started_at_server' => now(),
            'last_heartbeat_at' => now(),
        ]);

        $this->artisan('pelog:close-stale-sessions')->assertSuccessful();

        $this->assertNull($session->refresh()->closed_at);
        $this->assertSame('in_use', $device->refresh()->status->value);
    }
}
