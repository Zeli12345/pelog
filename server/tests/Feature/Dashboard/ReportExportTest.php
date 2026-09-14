<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(): void
    {
        $device = Device::factory()->create();
        $student = Student::factory()->withPin('2468')->create();

        UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Ekspor laporan',
            'started_at_server' => now()->subHour(),
            'last_heartbeat_at' => now()->subMinutes(30),
            'closed_at' => now()->subMinutes(30),
            'close_reason' => 'normal',
            'duration_minutes' => 30,
            'student_feedback' => 'Belajar ekspor laporan',
            'comprehension_level' => 'paham',
        ]);
    }

    public function test_reports_page_renders(): void
    {
        $this->seedSession();
        $admin = User::factory()->adminIt()->create();

        $this->actingAs($admin)->get('/reports')->assertOk();
    }

    public function test_csv_export_downloads(): void
    {
        $this->seedSession();
        $admin = User::factory()->adminIt()->create();

        $response = $this->actingAs($admin)->get('/reports/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_xlsx_export_downloads(): void
    {
        $this->seedSession();
        $admin = User::factory()->adminIt()->create();

        $response = $this->actingAs($admin)->get('/reports/export?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }
}
