<?php

namespace Database\Factories;

use App\Models\Student;
use App\Services\PinHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nisn' => '00'.fake()->unique()->numerify('########'),
            'name' => fake('id_ID')->name(),
            'class' => fake()->randomElement(['X RPL 1', 'X RPL 2', 'X TKJ 1', 'XI RPL 1', 'XI TKJ 1']),
            'is_active' => true,
        ];
    }

    public function withPin(string $pin = '2468'): static
    {
        return $this->state(function () use ($pin) {
            $hashed = PinHasher::make($pin);

            return [
                'pin_algo' => $hashed['algo'],
                'pin_salt' => $hashed['salt'],
                'pin_iterations' => $hashed['iterations'],
                'pin_hash' => $hashed['hash'],
                'pin_set_at' => now(),
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
