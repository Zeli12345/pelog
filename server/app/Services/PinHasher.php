<?php

namespace App\Services;

/**
 * Hashing PIN lintas-platform (PHP <-> C#).
 * Algoritma: PBKDF2-HMAC-SHA256, salt acak per siswa, output base64.
 * Implementasi C# memakai Rfc2898DeriveBytes(HashAlgorithmName.SHA256).
 */
class PinHasher
{
    public const ALGO = 'pbkdf2-sha256';

    public const DEFAULT_ITERATIONS = 100000;

    public const SALT_BYTES = 16;

    public const KEY_BYTES = 32;

    /**
     * @return array{algo: string, salt: string, iterations: int, hash: string}
     */
    public static function make(string $pin, ?string $salt = null, ?int $iterations = null): array
    {
        $salt ??= random_bytes(self::SALT_BYTES);
        $iterations ??= self::DEFAULT_ITERATIONS;

        $hash = hash_pbkdf2('sha256', $pin, $salt, $iterations, self::KEY_BYTES, true);

        return [
            'algo' => self::ALGO,
            'salt' => base64_encode($salt),
            'iterations' => $iterations,
            'hash' => base64_encode($hash),
        ];
    }

    public static function verify(string $pin, string $saltBase64, int $iterations, string $hashBase64): bool
    {
        $salt = base64_decode($saltBase64, true);
        $expected = base64_decode($hashBase64, true);

        if ($salt === false || $expected === false) {
            return false;
        }

        $actual = hash_pbkdf2('sha256', $pin, $salt, $iterations, self::KEY_BYTES, true);

        return hash_equals($expected, $actual);
    }
}
