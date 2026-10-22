<?php

namespace Tests\Feature\Dashboard;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menambah_rename_dan_menghapus_kategori(): void
    {
        $admin = User::factory()->adminUtama()->create();

        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'Sparepart'])->assertRedirect();
        $category = InventoryCategory::query()->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($admin)->put("/inventory/categories/{$category->id}", ['name' => 'Sparepart Komputer'])->assertRedirect();
        $this->assertSame('Sparepart Komputer', $category->refresh()->name);

        $this->actingAs($admin)->delete("/inventory/categories/{$category->id}")->assertRedirect();
        $this->assertDatabaseMissing('inventory_categories', ['id' => $category->id]);
    }

    public function test_kategori_yang_masih_dipakai_diarsipkan_bukan_dihapus(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $category = InventoryCategory::query()->create(['name' => 'Elektronik', 'is_active' => true]);
        InventoryItem::query()->create([
            'name' => 'Laptop cadangan',
            'category_id' => $category->id,
            'unit' => 'unit',
            'quantity' => 1,
            'condition' => 'good',
        ]);

        $this->actingAs($admin)->delete("/inventory/categories/{$category->id}")->assertRedirect();

        $this->assertDatabaseHas('inventory_categories', ['id' => $category->id, 'is_active' => false]);
    }

    public function test_nama_kategori_duplikat_ditolak(): void
    {
        $admin = User::factory()->adminUtama()->create();
        InventoryCategory::query()->create(['name' => 'Kabel', 'is_active' => true]);

        $this->actingAs($admin)->post('/inventory/categories', ['name' => 'kabel'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, InventoryCategory::query()->count());
    }

    public function test_viewer_tidak_bisa_mengelola_kategori(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->post('/inventory/categories', ['name' => 'Baru'])->assertForbidden();
    }
}
