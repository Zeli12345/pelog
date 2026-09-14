<?php

namespace App\Enums;

enum UserType: string
{
    case Student = 'student';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::Staff => 'Guru / Pegawai',
        };
    }
}
