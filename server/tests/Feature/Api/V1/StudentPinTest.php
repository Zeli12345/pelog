<?php

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use App\Models\Student;
use App\Services\PinHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class StudentPinTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('pin_length', 4);
        Setting::setValue('pin_forbid_weak', true);
    }

    public function test_student_can_set_pin_first_time(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $student = Student::factory()->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/students/0051234567/pin', [
            'pin' => '2468',
        ], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('data.has_pin', true);

        $student->refresh();
        $this->assertTrue($student->hasPin());
        $this->assertTrue(PinHasher::verify(
            '2468',
            $student->pin_salt,
            $student->pin_iterations,
            $student->pin_hash,
        ));

        $this->assertDatabaseHas('audit_logs', ['action' => 'pin_set']);
    }

    public function test_weak_pin_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/students/0051234567/pin', [
            'pin' => '1111',
        ], $this->deviceHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'pin_too_weak');
    }

    public function test_wrong_length_is_rejected(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/students/0051234567/pin', [
            'pin' => '12345',
        ], $this->deviceHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_pin_cannot_be_set_twice(): void
    {
        [$device, $token] = $this->enrolledDevice();
        Student::factory()->withPin('2468')->create(['nisn' => '0051234567']);

        $this->postJson('/api/v1/students/0051234567/pin', [
            'pin' => '1357',
        ], $this->deviceHeaders($token))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'pin_already_set');
    }

    public function test_pin_set_requires_valid_student(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $this->postJson('/api/v1/students/9999999999/pin', [
            'pin' => '2468',
        ], $this->deviceHeaders($token))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'student_not_found');
    }
}
