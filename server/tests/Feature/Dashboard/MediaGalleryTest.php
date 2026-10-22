<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Screenshot;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_jenis_dan_pencarian_perangkat(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $device = Device::factory()->create(['label' => 'LAB-GALERI']);
        $student = Student::factory()->create();

        $screen = $this->makeMedia($device, $student, 'screen', now());
        $webcam = $this->makeMedia($device, $student, 'webcam', now());

        $this->actingAs($admin)->get('/screenshots?kind=webcam')
            ->assertOk()
            ->assertSee("/screenshots/{$webcam->id}/thumb")
            ->assertDontSee("/screenshots/{$screen->id}/thumb");

        $this->actingAs($admin)->get('/screenshots?kind=screen')
            ->assertOk()
            ->assertSee("/screenshots/{$screen->id}/thumb")
            ->assertDontSee("/screenshots/{$webcam->id}/thumb");

        $this->actingAs($admin)->get('/screenshots?q=LAB-GALERI')
            ->assertOk()
            ->assertSee("/screenshots/{$screen->id}/thumb")
            ->assertSee("/screenshots/{$webcam->id}/thumb");
    }

    public function test_tanggal_memakai_zona_wita_bukan_utc(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $device = Device::factory()->create();
        $student = Student::factory()->create();

        // 20 Okt 00:30 WITA = 19 Okt 16:30 UTC. Filter tanggal 20 Okt harus menemukannya.
        $capturedAt = Carbon::parse('2026-10-20 00:30:00', 'Asia/Makassar')->utc();
        $media = $this->makeMedia($device, $student, 'screen', $capturedAt);

        $this->actingAs($admin)->get('/screenshots?from=2026-10-20&to=2026-10-20')
            ->assertOk()
            ->assertSee("/screenshots/{$media->id}/thumb");

        $this->actingAs($admin)->get('/screenshots?from=2026-10-19&to=2026-10-19')
            ->assertOk()
            ->assertDontSee("/screenshots/{$media->id}/thumb");
    }

    public function test_viewer_bisa_melihat_galeri(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/screenshots')->assertOk()->assertSee('Galeri Layar');
    }

    private function makeMedia(Device $device, Student $student, string $kind, Carbon $capturedAt): Screenshot
    {
        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Uji galeri',
            'started_at_server' => $capturedAt->copy()->subHour(),
            'last_heartbeat_at' => $capturedAt,
            'closed_at' => $capturedAt,
            'close_reason' => 'normal',
            'duration_minutes' => 60,
        ]);

        return Screenshot::query()->create([
            'screenshot_uuid' => (string) Str::uuid(),
            'kind' => $kind,
            'usage_session_id' => $session->id,
            'format' => 'webp',
            'path' => 'screenshots/'.$session->session_uuid.'-'.$kind.'.webp',
            'thumb_path' => 'screenshots/thumbs/'.$session->session_uuid.'-'.$kind.'.webp',
            'size_bytes' => 1000,
            'captured_at' => $capturedAt,
        ]);
    }
}
