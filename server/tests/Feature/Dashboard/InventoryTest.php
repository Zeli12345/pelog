<?php

namespace Tests\Feature\Dashboard;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stok_awal_dan_mutasi_masuk_keluar_tercatat(): void
    {
        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)->post('/inventory', [
            'name' => 'RAM DDR4 8GB',
            'unit' => 'pcs',
            'initial_quantity' => 10,
            'min_quantity' => 2,
            'condition' => 'good',
            'attributes_text' => "Merk: Lenovo\nUkuran: 8 GB",
        ])->assertRedirect();

        $item = InventoryItem::query()->firstOrFail();
        $this->assertSame(10, $item->quantity);
        $this->assertSame(1, $item->movements()->count());

        $this->actingAs($admin)->post("/inventory/{$item->id}/movements", [
            'type' => 'out',
            'quantity' => 3,
            'reference' => 'Dipasang di PC-04',
        ])->assertRedirect();

        $this->assertSame(7, $item->refresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity_before' => 10,
            'quantity_after' => 7,
        ]);
    }

    public function test_stok_tidak_boleh_negatif(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $item = InventoryItem::query()->create([
            'name' => 'Kabel HDMI',
            'unit' => 'pcs',
            'quantity' => 2,
            'min_quantity' => 0,
            'condition' => 'good',
        ]);

        $this->actingAs($admin)->post("/inventory/{$item->id}/movements", [
            'type' => 'out',
            'quantity' => 5,
        ])->assertRedirect()->assertSessionHasErrors('quantity');

        $this->assertSame(2, $item->refresh()->quantity);
    }

    public function test_transfer_mengubah_lokasi_dan_arsip_tidak_menghapus(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $from = Location::query()->create(['name' => 'Gudang', 'normalized_name' => 'gudang']);
        $to = Location::query()->create(['name' => 'Lab RPL', 'normalized_name' => 'lab rpl']);
        $item = InventoryItem::query()->create([
            'name' => 'SSD 512GB',
            'unit' => 'pcs',
            'quantity' => 3,
            'min_quantity' => 1,
            'condition' => 'good',
            'location_id' => $from->id,
        ]);

        $this->actingAs($admin)->post("/inventory/{$item->id}/movements", [
            'type' => 'transfer',
            'quantity' => 3,
            'location_to_id' => $to->id,
        ])->assertRedirect();

        $this->assertSame($to->id, $item->refresh()->location_id);

        $this->actingAs($admin)->post("/inventory/{$item->id}/archive")->assertRedirect();
        $this->assertFalse($item->refresh()->is_active);
    }

    public function test_viewer_read_only(): void
    {
        $viewer = User::factory()->viewer()->create();
        $item = InventoryItem::query()->create([
            'name' => 'Barang',
            'unit' => 'pcs',
            'quantity' => 1,
            'condition' => 'good',
        ]);

        $this->actingAs($viewer)->get('/inventory')->assertOk();
        $this->actingAs($viewer)->get("/inventory/{$item->id}")->assertOk();
        $this->actingAs($viewer)->post("/inventory/{$item->id}/movements", [
            'type' => 'out', 'quantity' => 1,
        ])->assertForbidden();
    }
}
