<?php

namespace Database\Factories;

use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nip_id' => fake()->unique()->numerify('19##############'),
            'name' => fake('id_ID')->name(),
            'role' => 'teacher',
            'is_active' => true,
        ];
    }
}
