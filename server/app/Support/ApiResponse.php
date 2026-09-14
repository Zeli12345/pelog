<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
            'error' => null,
            'server_time' => now()->toIso8601String(),
        ], $status);
    }

    public static function error(string $code, string $message, int $status = 422, mixed $details = null): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'server_time' => now()->toIso8601String(),
        ], $status);
    }
}
