<?php

namespace App\Enums;

enum UserRole: string
{
    case AdminIt = 'admin_it';
    case Guru = 'guru';

    public function label(): string
    {
        return match ($this) {
            self::AdminIt => 'Admin IT',
            self::Guru => 'Guru',
        };
    }
}
