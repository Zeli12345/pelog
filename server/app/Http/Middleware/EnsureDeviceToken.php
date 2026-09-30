<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return ApiResponse::error('device_token_missing', 'Token perangkat tidak ditemukan.', 401);
        }

        $device = Device::query()
            ->where('device_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if ($device === null) {
            return ApiResponse::error('device_token_invalid', 'Token perangkat tidak valid.', 401);
        }

        $updates = ['last_seen_at' => now()];

        // Versi aplikasi kiosk dilaporkan lewat header X-App-Version pada setiap
        // request terautentikasi; dipakai dashboard untuk menandai perangkat
        // yang belum memakai rilis terbaru.
        $version = trim((string) $request->header('X-App-Version', ''));

        if (preg_match('/^\d+(\.\d+){1,3}$/', $version) === 1) {
            $updates['agent_version'] = $version;
        }

        $device->forceFill($updates)->saveQuietly();

        $request->attributes->set('balilog_device', $device);

        return $next($request);
    }
}
