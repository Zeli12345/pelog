<?php

namespace App\Enums;

enum ScreenshotFormat: string
{
    case Webp = 'webp';
    case Jpeg = 'jpeg';

    public function mime(): string
    {
        return match ($this) {
            self::Webp => 'image/webp',
            self::Jpeg => 'image/jpeg',
        };
    }
}
