<?php

namespace App\Enums;

enum StaffRole: string
{
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Guru',
            self::Staff => 'Pegawai',
            self::Admin => 'Admin',
        };
    }
}
