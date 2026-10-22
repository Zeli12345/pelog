<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menyimpan_layout_dan_versi_bertambah(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $location = Location::query()->create(['name' => 'Lab RPL', 'normalized_name' => 'lab rpl']);
        $device = Device::factory()->create(['location_id' => $location->id]);

        $payload = [
            'version' => 1,
            'objects' => [
                ['type' => 'device', 'device_id' => $device->id, 'x' => 10, 'y' => 10, 'w' => 12, 'h' => 8, 'rotation' => 0, 'z_index' => 20],
                ['type' => 'table_circle', 'label' => 'Meja bulat', 'x' => 40, 'y' => 30, 'w' => 14, 'h' => 9, 'rotation' => 0, 'color' => '#DCEBE3'],
            ],
        ];

        $this->actingAs($admin)->putJson("/locations/{$location->id}/layout", $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(2, $location->refresh()->layout_version);
        $this->assertDatabaseHas('location_layout_objects', ['device_id' => $device->id, 'type' => 'device']);
        $this->assertDatabaseHas('location_layout_objects', ['type' => 'table_circle', 'label' => 'Meja bulat']);

        $this->actingAs($admin)->putJson("/locations/{$location->id}/layout", [
            'version' => 2,
            'objects' => [
                ['type' => 'device', 'device_id' => $device->id, 'x' => 10, 'y' => 10, 'w' => 12, 'h' => 8, 'rotation' => 0],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('location_layout_objects', ['device_id' => $device->id, 'type' => 'device']);
    }

    public function test_versi_lama_menghasilkan_konflik(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $location = Location::query()->create(['name' => 'Lab RPL', 'normalized_name' => 'lab rpl']);

        $payload = ['version' => 1, 'objects' => []];

        $this->actingAs($admin)->putJson("/locations/{$location->id}/layout", $payload)->assertOk();

        $this->actingAs($admin)->putJson("/locations/{$location->id}/layout", $payload)->assertStatus(409);
    }

    public function test_perangkat_ruangan_lain_ditolak(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $location = Location::query()->create(['name' => 'Lab RPL', 'normalized_name' => 'lab rpl']);
        $other = Location::query()->create(['name' => 'Lab TKJ', 'normalized_name' => 'lab tkj']);
        $device = Device::factory()->create(['location_id' => $other->id]);

        $this->actingAs($admin)->putJson("/locations/{$location->id}/layout", [
            'version' => 1,
            'objects' => [
                ['type' => 'device', 'device_id' => $device->id, 'x' => 10, 'y' => 10, 'w' => 12, 'h' => 8, 'rotation' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_viewer_tidak_bisa_menyimpan_layout(): void
    {
        $viewer = User::factory()->viewer()->create();
        $location = Location::query()->create(['name' => 'Lab RPL', 'normalized_name' => 'lab rpl']);

        $this->actingAs($viewer)->putJson("/locations/{$location->id}/layout", [
            'version' => 1,
            'objects' => [],
        ])->assertForbidden();
    }
}
