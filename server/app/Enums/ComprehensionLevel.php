<?php

namespace App\Enums;

enum ComprehensionLevel: string
{
    case SangatPaham = 'sangat_paham';
    case Paham = 'paham';
    case Cukup = 'cukup';
    case Kurang = 'kurang';

    public function label(): string
    {
        return match ($this) {
            self::SangatPaham => 'Sangat Paham',
            self::Paham => 'Paham',
            self::Cukup => 'Cukup',
            self::Kurang => 'Kurang Paham',
        };
    }
}
