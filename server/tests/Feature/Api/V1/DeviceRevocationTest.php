<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class DeviceRevocationTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_deleted_device_gets_device_revoked_signal(): void
    {
        [$device, $token] = $this->enrolledDevice();

        $device->delete();

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token))
            ->assertStatus(410)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'device_revoked');
    }

    public function test_inactive_device_still_gets_invalid_token(): void
    {
        [, $token] = $this->enrolledDevice(['is_active' => false]);

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'device_token_invalid');
    }
}
