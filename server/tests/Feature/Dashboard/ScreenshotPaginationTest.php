<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Screenshot;
use App\Models\Student;
use App\Models\UsageSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScreenshotPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function seedScreenshots(int $count): void
    {
        $device = Device::factory()->create();
        $student = Student::factory()->create();

        $session = UsageSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'user_type' => 'student',
            'student_id' => $student->id,
            'usage_purpose' => 'Uji pagination',
            'started_at_server' => now()->subHours(2),
            'last_heartbeat_at' => now(),
            'closed_at' => now(),
            'close_reason' => 'normal',
            'duration_minutes' => 120,
        ]);

        for ($i = 0; $i < $count; $i++) {
            Screenshot::query()->create([
                'screenshot_uuid' => (string) Str::uuid(),
                'usage_session_id' => $session->id,
                'format' => 'webp',
                'path' => 'screenshots/'.$session->session_uuid.'-'.$i.'.webp',
                'thumb_path' => null,
                'size_bytes' => 1000 + $i,
                'captured_at' => now()->subMinutes($i),
            ]);
        }
    }

    public function test_per_page_dapat_diatur_10_25_50_100(): void
    {
        $this->seedScreenshots(30);
        $admin = User::factory()->adminUtama()->create();

        foreach ([10, 25, 50, 100] as $perPage) {
            $response = $this->actingAs($admin)->get('/screenshots?per_page='.$perPage);

            $response->assertOk();

            $page = $response->viewData('screenshots');

            $this->assertSame($perPage, $page->perPage(), "per_page={$perPage} harus dipakai.");
            $this->assertSame(min(30, $perPage), $page->count(), "Jumlah kartu untuk per_page={$perPage}.");
        }
    }

    public function test_nilai_per_page_tidak_sah_kembali_ke_default_25(): void
    {
        $this->seedScreenshots(5);
        $admin = User::factory()->adminUtama()->create();

        $response = $this->actingAs($admin)->get('/screenshots?per_page=999');
        $response->assertOk();
        $this->assertSame(25, $response->viewData('screenshots')->perPage());

        $response = $this->actingAs($admin)->get('/screenshots');
        $this->assertSame(25, $response->viewData('screenshots')->perPage());
    }

    public function test_halaman_menyediakan_pemilih_per_page_dan_mempertahankannya_di_tautan(): void
    {
        $this->seedScreenshots(30);
        $admin = User::factory()->adminUtama()->create();

        $response = $this->actingAs($admin)->get('/screenshots?per_page=10&sort=oldest');

        $response->assertOk()
            ->assertSee('name="per_page"', false)
            ->assertSee('value="10" selected', false)
            ->assertSee('per_page=10', false)
            ->assertSee('sort=oldest', false);
    }
}
