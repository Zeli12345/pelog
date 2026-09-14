<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::InUse => 'Digunakan',
            self::Maintenance => 'Perawatan',
            self::Offline => 'Offline',
        };
    }
}
