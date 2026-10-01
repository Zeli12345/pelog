<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentStaffAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_and_restore_students_keep_session_history(): void
    {
        $device = Device::factory()->create();
        $students = collect([
            Student::factory()->create(['name' => 'Siswa Bulk Satu']),
            Student::factory()->create(['name' => 'Siswa Bulk Dua']),
        ]);

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $students[0]->id,
            'usage_purpose' => 'Riwayat sesi yang tidak boleh ikut terhapus',
            'started_at_server' => now()->subHour(),
            'last_heartbeat_at' => now()->subMinutes(50),
            'closed_at' => now()->subMinutes(50),
            'close_reason' => 'normal',
            'duration_minutes' => 10,
        ]);

        $admin = User::factory()->adminIt()->create();
        $ids = $students->pluck('id')->all();

        $this->actingAs($admin)
            ->post('/students/bulk-delete', ['ids' => $ids])
            ->assertRedirect(route('students.index'));

        foreach ($students as $student) {
            $this->assertSoftDeleted($student);
        }

        $this->assertDatabaseHas('usage_sessions', [
            'id' => $session->id,
            'student_id' => $students[0]->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'students_bulk_deleted']);

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertDontSee('Siswa Bulk Satu');

        $this->actingAs($admin)
            ->get('/students?trashed=1')
            ->assertOk()
            ->assertSee('Siswa Bulk Satu')
            ->assertSee('Siswa Bulk Dua');

        $this->actingAs($admin)
            ->post('/students/bulk-restore', ['ids' => $ids])
            ->assertRedirect(route('students.index', ['trashed' => 1]));

        foreach ($students as $student) {
            $this->assertFalse($student->fresh()->trashed());
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'students_bulk_restored']);

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertSee('Siswa Bulk Satu');
    }

    public function test_admin_can_restore_single_student(): void
    {
        $student = Student::factory()->create(['name' => 'Siswa Pulih Sendiri']);
        $student->delete();

        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->post("/students/{$student->id}/restore")
            ->assertRedirect(route('students.index', ['trashed' => 1]));

        $this->assertFalse($student->fresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_restored']);
    }

    public function test_bulk_delete_and_restore_staff(): void
    {
        $members = collect([
            StaffMember::factory()->create(['name' => 'Pegawai Bulk Satu']),
            StaffMember::factory()->create(['name' => 'Pegawai Bulk Dua']),
        ]);

        $admin = User::factory()->adminIt()->create();
        $ids = $members->pluck('id')->all();

        $this->actingAs($admin)
            ->post('/staff/bulk-delete', ['ids' => $ids])
            ->assertRedirect(route('staff.index'));

        foreach ($members as $member) {
            $this->assertSoftDeleted($member);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_bulk_deleted']);

        $this->actingAs($admin)
            ->get('/staff')
            ->assertOk()
            ->assertDontSee('Pegawai Bulk Satu');

        $this->actingAs($admin)
            ->get('/staff?trashed=1')
            ->assertOk()
            ->assertSee('Pegawai Bulk Satu');

        $this->actingAs($admin)
            ->post('/staff/bulk-restore', ['ids' => $ids])
            ->assertRedirect(route('staff.index', ['trashed' => 1]));

        foreach ($members as $member) {
            $this->assertFalse($member->fresh()->trashed());
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'staff_bulk_restored']);

        $this->actingAs($admin)
            ->get('/staff')
            ->assertOk()
            ->assertSee('Pegawai Bulk Satu');
    }

    public function test_guru_cannot_bulk_delete_students_or_staff(): void
    {
        $student = Student::factory()->create(['name' => 'Siswa Dilindungi']);
        $member = StaffMember::factory()->create(['name' => 'Pegawai Dilindungi']);
        $guru = User::factory()->create(['role' => 'guru']);

        $this->actingAs($guru)
            ->post('/students/bulk-delete', ['ids' => [$student->id]])
            ->assertForbidden();

        $this->actingAs($guru)
            ->post('/staff/bulk-delete', ['ids' => [$member->id]])
            ->assertForbidden();

        $this->assertFalse($student->fresh()->trashed());
        $this->assertFalse($member->fresh()->trashed());
    }

    public function test_trashed_filter_lists_only_deleted_rows(): void
    {
        Student::factory()->create(['name' => 'Siswa Masih Aktif']);
        $deleted = Student::factory()->create(['name' => 'Siswa Sudah Dihapus']);
        $deleted->delete();

        StaffMember::factory()->create(['name' => 'Pegawai Masih Aktif']);
        $deletedMember = StaffMember::factory()->create(['name' => 'Pegawai Sudah Dihapus']);
        $deletedMember->delete();

        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->get('/students?trashed=1')
            ->assertOk()
            ->assertSee('Siswa Sudah Dihapus')
            ->assertDontSee('Siswa Masih Aktif');

        $this->actingAs($admin)
            ->get('/staff?trashed=1')
            ->assertOk()
            ->assertSee('Pegawai Sudah Dihapus')
            ->assertDontSee('Pegawai Masih Aktif');
    }

    public function test_bulk_delete_rejects_more_than_200_ids(): void
    {
        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)
            ->post('/students/bulk-delete', ['ids' => range(1, 201)])
            ->assertSessionHasErrors('ids');

        $this->actingAs($admin)
            ->post('/devices/bulk-delete', ['ids' => []])
            ->assertSessionHasErrors('ids');
    }
}
