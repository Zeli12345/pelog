<?php

namespace App\Enums;

enum ScreenshotKind: string
{
    case Screen = 'screen';
    case Webcam = 'webcam';

    public function label(): string
    {
        return match ($this) {
            self::Screen => 'Layar',
            self::Webcam => 'Webcam',
        };
    }
}
