<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(5)),
            'name' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}
