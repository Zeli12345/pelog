<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menambah_lokasi_dan_menolak_duplikat_normalisasi(): void
    {
        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)->post('/locations', ['name' => 'Lab RPL 1'])->assertRedirect();
        $location = Location::query()->firstOrFail();

        $this->assertSame('lab rpl 1', $location->normalized_name);

        $this->actingAs($admin)
            ->post('/locations', ['name' => '  lab   RPL 1 '])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Location::query()->count());
    }

    public function test_detail_perangkat_bisa_pilih_dan_tambah_lokasi(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $location = Location::query()->create([
            'name' => 'Lab RPL 1',
            'normalized_name' => 'lab rpl 1',
        ]);
        $device = Device::factory()->create(['location_id' => null]);

        $this->actingAs($admin)->put("/devices/{$device->id}", [
            'label' => 'LAB-01',
            'location_id' => $location->id,
            'status' => 'available',
        ])->assertRedirect();

        $this->assertSame($location->id, $device->refresh()->location_id);

        $this->actingAs($admin)->put("/devices/{$device->id}", [
            'label' => 'LAB-01',
            'new_location' => 'rpl',
            'status' => 'available',
        ])->assertRedirect();

        $this->assertSame(1, Location::query()->count());
        $this->assertSame('Lab RPL 1', $device->refresh()->location->name);
    }

    public function test_lokasi_tidak_bisa_dihapus_saat_masih_dipakai(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $location = Location::query()->create([
            'name' => 'Lab RPL 1',
            'normalized_name' => 'lab rpl 1',
        ]);
        Device::factory()->create(['location_id' => $location->id]);

        $this->actingAs($admin)->delete("/locations/{$location->id}", [
            'confirm_name' => 'Lab RPL 1',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_merge_memindahkan_perangkat_dan_menghapus_sumber(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $source = Location::query()->create(['name' => 'RPL', 'normalized_name' => 'rpl']);
        $target = Location::query()->create(['name' => 'Lab RPL 1', 'normalized_name' => 'lab rpl 1']);
        $device = Device::factory()->create(['location_id' => $source->id]);

        $this->actingAs($admin)->post("/locations/{$source->id}/merge", [
            'target_id' => $target->id,
        ])->assertRedirect();

        $this->assertSame($target->id, $device->refresh()->location_id);
        $this->assertDatabaseMissing('locations', ['id' => $source->id]);
    }

    public function test_viewer_hanya_bisa_melihat(): void
    {
        $viewer = User::factory()->viewer()->create();
        $location = Location::query()->create(['name' => 'Lab RPL 1', 'normalized_name' => 'lab rpl 1']);

        $this->actingAs($viewer)->get('/locations')->assertOk();
        $this->actingAs($viewer)->get("/locations/{$location->id}")->assertOk();
        $this->actingAs($viewer)->post('/locations', ['name' => 'Baru'])->assertForbidden();
    }
}
