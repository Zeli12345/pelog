<?php

namespace App\Support;

use App\Models\Student;

class StudentPayload
{
    /**
     * Representasi siswa untuk aplikasi client.
     * Field PIN (algo/salt/iterations/hash) disertakan agar verifikasi PIN
     * bisa dilakukan offline oleh client.
     *
     * @return array<string, mixed>
     */
    public static function forDevice(Student $student): array
    {
        return [
            'nisn' => $student->nisn,
            'name' => $student->name,
            'class' => $student->class,
            'has_pin' => $student->hasPin(),
            'pin' => $student->hasPin() ? [
                'algo' => $student->pin_algo,
                'salt' => $student->pin_salt,
                'iterations' => $student->pin_iterations,
                'hash' => $student->pin_hash,
            ] : null,
            'updated_at' => $student->updated_at?->toIso8601String(),
        ];
    }
}
