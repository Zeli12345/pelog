<?php

namespace Tests\Concerns;

use App\Models\Device;

trait InteractsWithDevice
{
    /**
     * @return array{0: Device, 1: string}
     */
    protected function enrolledDevice(array $attributes = []): array
    {
        $token = bin2hex(random_bytes(32));

        $device = Device::factory()->create(array_merge([
            'device_token_hash' => hash('sha256', $token),
        ], $attributes));

        return [$device->refresh(), $token];
    }

    /**
     * @return array<string, string>
     */
    protected function deviceHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
