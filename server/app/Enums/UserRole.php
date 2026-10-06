<?php

namespace App\Enums;

enum UserRole: string
{
    case AdminUtama = 'admin_utama';
    case SubAdmin = 'sub_admin';
    case Viewer = 'viewer';

    /**
     * Label yang tampil di UI. Kedua tipe admin sengaja berlabel sama
     * ("Admin") — pembedaan kewenangan hanya ditegakkan di server.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdminUtama, self::SubAdmin => 'Admin',
            self::Viewer => 'Viewer',
        };
    }
}
