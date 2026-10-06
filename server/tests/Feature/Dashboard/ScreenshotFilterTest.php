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

class ScreenshotFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_device_search_and_sort(): void
    {
        $deviceA = Device::factory()->create(['label' => 'LAB-FILTER-A']);
        $deviceB = Device::factory()->create(['label' => 'LAB-FILTER-B']);
        $studentA = Student::factory()->create(['name' => 'Ahmad Fauzi']);
        $studentB = Student::factory()->create(['name' => 'Bunga Lestari']);

        $older = $this->makeScreenshot($deviceA, $studentA, 'Latihan sistem operasi', now()->subDay());
        $newer = $this->makeScreenshot($deviceB, $studentB, 'Ujian jaringan komputer', now());

        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)
            ->get('/screenshots')
            ->assertOk()
            ->assertSeeInOrder(["/screenshots/{$newer->id}/thumb", "/screenshots/{$older->id}/thumb"]);

        $this->actingAs($admin)
            ->get('/screenshots?device_id='.$deviceA->id)
            ->assertOk()
            ->assertSee("/screenshots/{$older->id}/thumb")
            ->assertDontSee("/screenshots/{$newer->id}/thumb");

        $this->actingAs($admin)
            ->get('/screenshots?q=Bunga')
            ->assertOk()
            ->assertSee("/screenshots/{$newer->id}/thumb")
            ->assertDontSee("/screenshots/{$older->id}/thumb");

        $this->actingAs($admin)
            ->get('/screenshots?sort=oldest')
            ->assertOk()
            ->assertSeeInOrder(["/screenshots/{$older->id}/thumb", "/screenshots/{$newer->id}/thumb"]);

        $this->actingAs($admin)
            ->get('/screenshots?sort=device')
            ->assertOk()
            ->assertSeeInOrder(["/screenshots/{$older->id}/thumb", "/screenshots/{$newer->id}/thumb"]);
    }

    private function makeScreenshot(Device $device, Student $student, string $purpose, Carbon $capturedAt): Screenshot
    {
        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => $purpose,
            'started_at_server' => $capturedAt->copy()->subHour(),
            'last_heartbeat_at' => $capturedAt,
            'closed_at' => $capturedAt,
            'close_reason' => 'normal',
            'duration_minutes' => 60,
        ]);

        return Screenshot::query()->create([
            'screenshot_uuid' => (string) Str::uuid(),
            'usage_session_id' => $session->id,
            'format' => 'webp',
            'path' => 'screenshots/'.$session->session_uuid.'.webp',
            'thumb_path' => 'screenshots/thumbs/'.$session->session_uuid.'.webp',
            'size_bytes' => 40000,
            'captured_at' => $capturedAt,
        ]);
    }
}
