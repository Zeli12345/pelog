<?php

namespace Tests\Feature\Api\V1;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class StudentLookupTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_lookup_returns_student_with_pin_data(): void
    {
        [$device, $token] = $this->enrolledDevice();

        Student::factory()->withPin('2468')->create([
            'nisn' => '0051234567',
            'name' => 'Budi Pratama',
            'class' => 'X RPL 1',
        ]);

        $this->getJson('/api/v1/students/0051234567', $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Pratama')
            ->assertJsonPath('data.class', 'X RPL 1')
            ->assertJsonPath('data.has_pin', true)
            ->assertJsonPath('data.pin.algo', 'pbkdf2-sha256');
    }

    public function test_lookup_returns_not_found_and_writes_audit(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->getJson('/api/v1/students/9999999999', $this->deviceHeaders($token))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'student_not_found');

        $this->assertDatabaseHas('audit_logs', ['action' => 'nisn_lookup_failed']);
    }

    public function test_lookup_rejects_inactive_student(): void
    {
        [$device, $token] = $this->enrolledDevice();

        Student::factory()->inactive()->create(['nisn' => '0059999999']);

        $this->getJson('/api/v1/students/0059999999', $this->deviceHeaders($token))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'student_inactive');
    }
}
