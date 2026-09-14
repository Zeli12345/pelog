<?php

namespace App\Enums;

enum CloseReason: string
{
    case Normal = 'normal';
    case Recovery = 'recovery';
    case Shutdown = 'shutdown';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Selesai normal',
            self::Recovery => 'Ditutup otomatis (recovery)',
            self::Shutdown => 'Laptop dimatikan',
            self::Admin => 'Ditutup admin',
        };
    }
}
