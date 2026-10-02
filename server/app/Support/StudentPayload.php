<?php

namespace App\Support;

use App\Models\Student;

class StudentPayload
{
    /**
     * Representasi siswa untuk aplikasi client.
     *
     * @return array<string, mixed>
     */
    public static function forDevice(Student $student): array
    {
        return [
            'nisn' => $student->nisn,
            'name' => $student->name,
            'class' => $student->class,
            'birth_date' => $student->birth_date?->toDateString(),
            'updated_at' => $student->updated_at?->toIso8601String(),
        ];
    }
}
