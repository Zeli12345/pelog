<?php

namespace Tests\Feature\Api\V1;

use App\Models\Screenshot;
use App\Models\Student;
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
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);
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

    public function test_second_upload_for_same_session_is_idempotent(): void
    {
        [$device, $token] = $this->enrolledDevice();
        $headers = $this->deviceHeaders($token);
        $uuid = $this->startStudentSession($headers);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => UploadedFile::fake()->image('a.jpg', 640, 480),
        ], $headers)->assertStatus(201);

        $this->post("/api/v1/sessions/{$uuid}/screenshot", [
            'screenshot_uuid' => (string) Str::uuid(),
            'image_file' => UploadedFile::fake()->image('b.jpg', 640, 480),
        ], $headers)->assertOk();

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

        $path = tempnam(sys_get_temp_dir(), 'balilog_big_').'.jpg';
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
}
