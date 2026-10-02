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

        Student::factory()->create(['name' => 'Budi Pratama', 'birth_date' => '2008-07-14']);
        Student::factory()->create(['name' => 'Ani Wijaya', 'birth_date' => '2008-11-02']);
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

        $budi = $students->firstWhere('name', 'Budi Pratama');
        $this->assertSame('2008-07-14', $budi['birth_date']);

        $ani = $students->firstWhere('name', 'Ani Wijaya');
        $this->assertSame('2008-11-02', $ani['birth_date']);

        $this->assertCount(1, $response->json('data.staff'));
        $this->assertCount(1, $response->json('data.subjects'));
        $this->assertSame('PWPB', $response->json('data.subjects.0.code'));

        $this->assertArrayHasKey('screenshot_enabled', $response->json('data.config'));
        $this->assertArrayNotHasKey('pin_length', $response->json('data.config'));
    }
}
