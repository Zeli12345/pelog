<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rename_device_and_set_maintenance(): void
    {
        $device = Device::factory()->create(['hostname' => 'DESKTOP-X1', 'label' => null]);
        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->put("/devices/{$device->id}", [
                'label' => 'LAB-BL-10',
                'location_label' => 'Lab Komputer 1',
                'status' => 'maintenance',
            ])
            ->assertRedirect();

        $device->refresh();

        $this->assertSame('LAB-BL-10', $device->label);
        $this->assertSame('Lab Komputer 1', $device->location_label);
        $this->assertSame('maintenance', $device->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_updated']);
    }

    public function test_device_detail_page_shows_admin_panel(): void
    {
        $device = Device::factory()->create();
        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->get("/devices/{$device->id}")
            ->assertOk()
            ->assertSee('Pengaturan Perangkat');
    }

    public function test_guru_cannot_change_device(): void
    {
        $device = Device::factory()->create();
        $guru = User::factory()->create(['role' => 'guru']);

        $this->actingAs($guru)
            ->put("/devices/{$device->id}", ['status' => 'maintenance'])
            ->assertForbidden();
    }

    public function test_admin_can_force_close_active_session(): void
    {
        $device = Device::factory()->create(['status' => 'in_use']);
        $student = Student::factory()->withPin('2468')->create();

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Sesi untuk ditutup paksa',
            'started_at_server' => now()->subMinutes(20),
            'last_heartbeat_at' => now()->subMinutes(2),
        ]);

        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->post("/sessions/{$session->id}/close")
            ->assertRedirect();

        $session->refresh();

        $this->assertFalse($session->isActive());
        $this->assertSame('admin', $session->close_reason->value);
        $this->assertSame('available', $device->refresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_closed_admin']);
    }

    public function test_guru_cannot_force_close_session(): void
    {
        $device = Device::factory()->create(['status' => 'in_use']);
        $student = Student::factory()->withPin('2468')->create();

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Sesi guru tidak boleh tutup',
            'started_at_server' => now()->subMinutes(10),
            'last_heartbeat_at' => now()->subMinute(),
        ]);

        $guru = User::factory()->create(['role' => 'guru']);

        $this->actingAs($guru)
            ->post("/sessions/{$session->id}/close")
            ->assertForbidden();

        $this->assertTrue($session->refresh()->isActive());
    }
}
