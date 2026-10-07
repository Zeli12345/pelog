<?php

namespace App\Support;

class Duration
{
    /**
     * Format durasi menit menjadi label yang manusiawi:
     * 45 -> "45 menit", 60 -> "1 jam", 65 -> "1 jam 5 menit".
     */
    public static function human(?int $minutes): string
    {
        $minutes = max(0, (int) $minutes);

        if ($minutes < 60) {
            return $minutes.' menit';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0
            ? $hours.' jam'
            : $hours.' jam '.$rest.' menit';
    }
}
