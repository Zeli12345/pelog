<?php

namespace Tests\Feature\Api\V1;

use App\Models\Screenshot;
use App\Models\Student;
use App\Models\UsageSession;
use App\Services\ScreenshotService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class ScreenshotUploadTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function startStudentSession(array $headers): string
    {
        Student::factory()->create(['nisn' => '0051234567']);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/sessions/start', [
            'session_uuid' => $uuid,
            'user_type' => 'student',
            'nisn' => '0051234567',
            'usage_purpose' => 'Praktikum',
        ], $headers)->assertStatus(201);

        return $uuid;
    }

    public function test_screenshot_can_be_uploaded_once(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $file = UploadedFile::fake()->image('screen.jpg', 800, 600);

        $this->post(
            "/api/v1/sessions/{$uuid}/screenshot",
            [
                'screenshot_uuid' => (string) Str::uuid(),
                'image_file' => $file,
                'captured_at_client' => now()->toIso8601String(),
            ],
            $headers,
        )
            ->assertStatus(201)
            ->assertJsonPath('data.format', 'jpeg')
            ->assertJsonPath('data.thumb_available', true);

        $screenshot = Screenshot::query()->firstOrFail();
        Storage::disk('local')->assertExists($screenshot->path);
        $this->assertNotNull($screenshot->thumb_path);
        Storage::disk('local')->assertExists($screenshot->thumb_path);
        $this->assertGreaterThan(0, $screenshot->size_bytes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'screenshot_uploaded']);
    }

    public function test_retry_with_same_uuid_is_idempotent(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);
        $screenshotUuid = (string) Str::uuid();

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => $screenshotUuid,
            'image_file' => UploadedFile::fake()->image('a.jpg', 640, 480),
        ], $headers)->assertStatus(201);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => $screenshotUuid,
            'image_file' => UploadedFile::fake()->image('b.jpg', 640, 480),
        ], $headers)->assertOk();

        $this->assertDatabaseCount('screenshots', 1);
    }

    public function test_multiple_screenshots_per_session_are_allowed(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        // Konten harus benar-benar berbeda: fake()->image() menghasilkan byte
        // yang sama untuk ukuran yang sama, sehingga dedupe isi akan
        // menggabungkannya (dan itu memang perilaku yang diinginkan).
        foreach ([['a.jpg', 10, 20, 30], ['b.jpg', 40, 50, 60], ['c.jpg', 70, 80, 90]] as [$name, $r, $g, $b]) {
            $this->post("/api/v1/sessions/{$uuid}/screenshot", [
                'screenshot_uuid' => (string) Str::uuid(),
                'image_file' => $this->fixedImage($name, $r, $g, $b),
            ], $headers)->assertStatus(201);
        }

        $sessionId = Screenshot::query()->value('usage_session_id');

        $this->assertDatabaseCount('screenshots', 3);
        $this->assertSame(3, Screenshot::query()->where('usage_session_id', $sessionId)->count());

        // Berkas gambar tiap screenshot berbeda (tidak saling menimpa).
        $paths = Screenshot::query()->pluck('path')->all();
        $this->assertCount(3, array_unique($paths));
    }

    public function test_screenshot_uuid_is_used_for_row_and_file_path(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);
        $screenshotUuid = (string) Str::uuid();

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => $screenshotUuid,
            'image_file' => UploadedFile::fake()->image('a.jpg', 640, 480),
        ], $headers)->assertStatus(201);

        $screenshot = Screenshot::query()->firstOrFail();

        // Jalur berkas dan UUID baris harus memakai UUID permintaan. Baris dengan
        // UUID acak sementara (sisa balapan unggah ganda) akan salah merujuk
        // berkas milik screenshot yang sama.
        $this->assertSame($screenshotUuid, $screenshot->screenshot_uuid);
        $this->assertStringContainsString($screenshotUuid, $screenshot->path);
    }

    public function test_losing_duplicate_race_returns_existing_screenshot(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);
        $screenshotUuid = (string) Str::uuid();

        // Simulasikan balapan: permintaan lain menang lebih dulu (baris sudah
        // ada), lalu penyimpanan kita kalah oleh unique constraint.
        $this->instance(ScreenshotService::class, new class($screenshotUuid) extends ScreenshotService
        {
            public function __construct(private string $racingUuid) {}

            public function store(UploadedFile $file, UsageSession $session, ?string $screenshotUuid = null): Screenshot
            {
                Screenshot::query()->create([
                    'screenshot_uuid' => $this->racingUuid,
                    'usage_session_id' => $session->id,
                    'format' => \App\Enums\ScreenshotFormat::Jpeg,
                    'path' => 'screenshots/2026/10/race-winner.jpg',
                    'thumb_path' => null,
                    'size_bytes' => 10,
                    'captured_at' => now(),
                ]);

                throw new QueryException(
                    'mysql',
                    'insert into `screenshots` ...',
                    [],
                    new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry', 23000),
                );
            }
        });

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => $screenshotUuid,
            'image_file' => UploadedFile::fake()->image('b.jpg', 640, 480),
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.screenshot_uuid', $screenshotUuid);

        $this->assertDatabaseCount('screenshots', 1);
    }

    public function test_oversized_file_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $file = $this->oversizedJpeg();
        $this->assertGreaterThan(300 * 1024, $file->getSize());

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $file,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    private function oversizedJpeg(): UploadedFile
    {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 120, 120));

        $path = tempnam(sys_get_temp_dir(), 'pelog_big_').'.jpg';
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        // JPEG valid + padding acak agar ukurannya melewati batas 300 KB.
        $content = (string) file_get_contents($path).random_bytes(420 * 1024);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('big.jpg', $content);
    }

    public function test_non_image_file_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'),
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_screenshot_for_unknown_session_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->post('/api/v1/sessions/'.Str::uuid().'/screenshot', [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => UploadedFile::fake()->image('x.jpg', 320, 240),
        ], $this->deviceHeaders($token))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'session_not_found');
    }

    public function test_upload_identik_beruntun_dalam_jendela_digabung(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $firstUuid = (string) Str::uuid();

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => $firstUuid,
            'image_file' => $this->fixedImage('a.jpg', 10, 20, 30),
            'captured_at' => now()->toIso8601String(),
        ], $headers)->assertStatus(201);

        // Permintaan manual dimisalkan aktif; unggahan kedua yang identik
        // harus dianggap satu screenshot (tidak menggandakan baris).
        $device->refresh()->forceFill(['screenshot_requested_at' => now()])->save();

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $this->fixedImage('b.jpg', 10, 20, 30),
            'captured_at' => now()->addSecond()->toIso8601String(),
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.screenshot_uuid', $firstUuid);

        $this->assertDatabaseCount('screenshots', 1);
        $this->assertNull($device->refresh()->screenshot_requested_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'screenshot_deduplicated']);
    }

    public function test_upload_identik_di_luar_jendela_tetap_baris_baru(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $this->fixedImage('a.jpg', 10, 20, 30),
            'captured_at' => now()->toIso8601String(),
        ], $headers)->assertStatus(201);

        // Capture sebelumnya sudah lewat jendela (mis. screenshot interval
        // 15 menit) — unggahan identik berikutnya tetap baris baru.
        Screenshot::query()->firstOrFail()->forceFill(['captured_at' => now()->subMinutes(10)])->save();

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $this->fixedImage('b.jpg', 10, 20, 30),
            'captured_at' => now()->toIso8601String(),
        ], $headers)->assertStatus(201);

        $this->assertDatabaseCount('screenshots', 2);
    }

    public function test_upload_konten_berbeda_tetap_baris_baru(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $this->fixedImage('a.jpg', 10, 20, 30),
            'captured_at' => now()->toIso8601String(),
        ], $headers)->assertStatus(201);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => $this->fixedImage('b.jpg', 200, 10, 10),
            'captured_at' => now()->addSecond()->toIso8601String(),
        ], $headers)->assertStatus(201);

        $this->assertDatabaseCount('screenshots', 2);
    }

    /**
     * Gambar JPEG dengan byte deterministik agar dua unggahan bisa benar-benar
     * identik (fake()->image() memakai warna acak tiap panggilan).
     */
    private function fixedImage(string $name, int $red, int $green, int $blue): UploadedFile
    {
        $image = imagecreatetruecolor(64, 48);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));

        $path = tempnam(sys_get_temp_dir(), 'pelog_fixed_').'.jpg';
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $content = (string) file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
