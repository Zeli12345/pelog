<?php

namespace App\Services;

class PinPolicy
{
    /**
     * PIN dianggap lemah jika bukan angka, atau polanya terlalu mudah ditebak.
     */
    public static function isWeak(string $pin): bool
    {
        if (! preg_match('/^\d+$/', $pin)) {
            return true;
        }

        // Semua digit sama: 0000, 1111, ...
        if (preg_match('/^(\d)\1+$/', $pin)) {
            return true;
        }

        // Berurutan naik/turun: 1234, 4321, 0123, ...
        $digits = array_map('intval', str_split($pin));
        $ascending = true;
        $descending = true;

        for ($i = 1; $i < count($digits); $i++) {
            if ($digits[$i] !== ($digits[$i - 1] + 1) % 10) {
                $ascending = false;
            }
            if ($digits[$i] !== ($digits[$i - 1] + 9) % 10) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }
}
