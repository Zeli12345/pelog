<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(array $attributes = []): void
    {
        $device = Device::factory()->create();
        $student = Student::factory()->create();

        UsageSession::query()->create(array_merge([
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
        ], $attributes));
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

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $this->seedSession(['usage_purpose' => '=SUM(A1:A2)']);
        $admin = User::factory()->adminIt()->create();

        $from = now()->subDays(2)->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->actingAs($admin)->get("/reports/export?format=csv&from={$from}&to={$to}");

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=SUM(A1:A2)", $csv);
        $this->assertStringNotContainsString(',=SUM(A1:A2)', $csv);
    }

    public function test_xlsx_export_honours_search_filter(): void
    {
        $this->seedSession(['usage_purpose' => 'ZEBRA praktikum jaringan']);
        $this->seedSession(['usage_purpose' => 'OMEGA pemrograman web']);
        $admin = User::factory()->adminIt()->create();

        $from = now()->subDays(2)->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->actingAs($admin)->get("/reports/export?format=xlsx&q=ZEBRA&from={$from}&to={$to}");

        $response->assertOk();

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false);
        $text = collect($rows)->flatten()->filter(fn ($value) => $value !== null && $value !== '')->implode(' | ');

        $this->assertStringContainsString('ZEBRA praktikum jaringan', $text);
        $this->assertStringNotContainsString('OMEGA pemrograman web', $text);
    }

    public function test_report_date_filter_follows_wita_timezone(): void
    {
        // 06 Okt 2026 02:00 WITA = 05 Okt 2026 18:00 UTC — masih tanggal 06 di sekolah.
        $this->seedSession([
            'started_at_server' => Carbon::parse('2026-10-05 18:00:00', 'UTC'),
            'usage_purpose' => 'WITA-PAGI',
        ]);
        // 07 Okt 2026 00:30 WITA = 06 Okt 2026 16:30 UTC — sudah lewat tanggal 06 di sekolah.
        $this->seedSession([
            'started_at_server' => Carbon::parse('2026-10-06 16:30:00', 'UTC'),
            'usage_purpose' => 'WITA-LEWAT-TENGAH-MALAM',
        ]);
        $admin = User::factory()->adminIt()->create();

        $csv = $this->actingAs($admin)
            ->get('/reports/export?format=csv&from=2026-10-06&to=2026-10-06')
            ->streamedContent();

        $this->assertStringContainsString('WITA-PAGI', $csv);
        $this->assertStringNotContainsString('WITA-LEWAT-TENGAH-MALAM', $csv);
    }

    public function test_report_includes_offline_synced_sessions_without_started_at_server(): void
    {
        // Sesi offline tersinkron: started_at_server NULL, tanggal dari started_at_client.
        $this->seedSession([
            'started_at_server' => null,
            'started_at_client' => Carbon::parse('2026-10-05 19:00:00', 'UTC'), // 06 Okt 03:00 WITA
            'usage_purpose' => 'SESI-OFFLINE-SINKRON',
        ]);
        $admin = User::factory()->adminIt()->create();

        $csv = $this->actingAs($admin)
            ->get('/reports/export?format=csv&from=2026-10-06&to=2026-10-06')
            ->streamedContent();

        $this->assertStringContainsString('SESI-OFFLINE-SINKRON', $csv);
    }
}
