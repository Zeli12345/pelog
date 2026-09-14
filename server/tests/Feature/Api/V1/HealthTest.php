<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonStructure(['ok', 'data', 'error', 'server_time']);
    }

    public function test_version_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['app_version', 'min_client_version']]);
    }
}
