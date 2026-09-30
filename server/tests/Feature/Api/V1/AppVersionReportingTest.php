<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithDevice;
use Tests\TestCase;

class AppVersionReportingTest extends TestCase
{
    use InteractsWithDevice, RefreshDatabase;

    public function test_authenticated_request_records_app_version_from_header(): void
    {
        [$device, $token] = $this->enrolledDevice(['agent_version' => null]);

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token) + ['X-App-Version' => '1.2.3'])
            ->assertOk();

        $device->refresh();

        $this->assertSame('1.2.3', $device->agent_version);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_invalid_app_version_header_is_ignored(): void
    {
        [$device, $token] = $this->enrolledDevice(['agent_version' => '1.0.0']);

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token) + ['X-App-Version' => 'bukan-versi'])
            ->assertOk();

        $this->assertSame('1.0.0', $device->refresh()->agent_version);
    }

    public function test_app_version_unchanged_without_header(): void
    {
        [$device, $token] = $this->enrolledDevice(['agent_version' => '1.0.0']);

        $this->getJson('/api/v1/bootstrap', $this->deviceHeaders($token))
            ->assertOk();

        $this->assertSame('1.0.0', $device->refresh()->agent_version);
    }
}
