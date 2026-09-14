<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'hostname' => 'LAB-TEST-'.strtoupper(Str::random(6)),
            'label' => null,
            'device_token_hash' => hash('sha256', Str::random(32)),
            'mac_list' => ['AA:BB:CC:DD:EE:FF'],
            'device_type' => 'laptop',
            'location_label' => 'Lab Komputer 1',
            'status' => 'available',
            'storage_total_gb' => 256,
            'storage_used_gb' => 100,
            'agent_version' => '1.0.0',
            'windows_version' => 'Windows 10 Pro 22H2',
            'last_seen_at' => now(),
            'enrolled_at' => now(),
            'is_active' => true,
        ];
    }
}
