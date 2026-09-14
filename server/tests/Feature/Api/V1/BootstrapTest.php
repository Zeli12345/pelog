<?php

namespace Tests\Feature\Api\V1;

use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class BootstrapTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_bootstrap_requires_device_token(): void
    {
        $this->getJson('/api/v1/bootstrap')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_missing');

        $this->getJson('/api/v1/bootstrap', ['Authorization' => 'Bearer salah'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_invalid');
    }

    public function test_bootstrap_returns_students_staff_subjects_and_config(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $studentWithPin = Student::factory()->withPin('2468')->create(['name' => 'Budi Pratama']);
        Student::factory()->create(['name' => 'Tanpa Pin']);
        Student::factory()->inactive()->create(['name' => 'Non Aktif']);
        StaffMember::factory()->create(['name' => 'I Komang Purwata']);
        Subject::factory()->create(['code' => 'PWPB', 'name' => 'Pemrograman Web']);
        Subject::factory()->create(['is_active' => false, 'name' => 'Mapel Non Aktif']);

        $response = $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $students = collect($response->json('data.students'));
        $this->assertCount(2, $students);
        $this->assertFalse($students->contains('name', 'Non Aktif'));

        $withPin = $students->firstWhere('name', 'Budi Pratama');
        $this->assertTrue($withPin['has_pin']);
        $this->assertSame('pbkdf2-sha256', $withPin['pin']['algo']);
        $this->assertNotEmpty($withPin['pin']['salt']);
        $this->assertNotEmpty($withPin['pin']['hash']);

        $withoutPin = $students->firstWhere('name', 'Tanpa Pin');
        $this->assertFalse($withoutPin['has_pin']);
        $this->assertNull($withoutPin['pin']);

        $this->assertCount(1, $response->json('data.staff'));
        $this->assertCount(1, $response->json('data.subjects'));
        $this->assertSame('PWPB', $response->json('data.subjects.0.code'));

        $this->assertArrayHasKey('pin_length', $response->json('data.config'));
        $this->assertArrayHasKey('screenshot_enabled', $response->json('data.config'));
    }
}
