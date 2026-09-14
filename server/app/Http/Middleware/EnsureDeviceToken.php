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

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('balilog_device', $device);

        return $next($request);
    }
}
