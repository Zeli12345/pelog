<?php

namespace Database\Factories;

use App\Models\Student;
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
            'birth_date' => fake()->dateTimeBetween('-19 years', '-15 years')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
