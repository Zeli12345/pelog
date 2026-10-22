<?php

namespace Tests\Feature\Dashboard;

use App\Models\Device;
use App\Models\DeviceNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeviceNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bisa_menambah_catatan_dan_lampiran(): void
    {
        Storage::fake('local');
        $admin = User::factory()->adminUtama()->create();
        $device = Device::factory()->create();

        $this->actingAs($admin)->post("/devices/{$device->id}/notes", [
            'category' => 'perbaikan',
            'title' => 'LCD bergaris',
            'body' => 'Menunggu sparepart dari vendor.',
            'status' => 'open',
            'priority' => 'high',
            'vendor' => 'Computer Center',
            'cost' => '850000',
            'tags' => 'lcd, vendor',
            'attachments' => [UploadedFile::fake()->image('lcd.jpg', 640, 480)],
        ])->assertRedirect();

        $note = DeviceNote::query()->firstOrFail();

        $this->assertSame('perbaikan', $note->category);
        $this->assertSame('850000.00', $note->cost);
        $this->assertSame(['lcd', 'vendor'], $note->tags);
        $this->assertSame(1, $note->attachments()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_note_created']);
    }

    public function test_catatan_tampil_di_tab_dan_status_selesai_mengisi_resolved_at(): void
    {
        $admin = User::factory()->adminUtama()->create();
        $device = Device::factory()->create();
        $note = DeviceNote::query()->create([
            'device_id' => $device->id,
            'category' => 'perbaikan',
            'body' => 'Ganti baterai CMOS',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($admin)->get("/devices/{$device->id}?tab=notes")
            ->assertOk()
            ->assertSee('Ganti baterai CMOS');

        $this->actingAs($admin)->put("/devices/{$device->id}/notes/{$note->id}", [
            'category' => 'perbaikan',
            'body' => 'Ganti baterai CMOS',
            'status' => 'resolved',
            'priority' => 'normal',
        ])->assertRedirect();

        $this->assertNotNull($note->refresh()->resolved_at);
    }

    public function test_viewer_tidak_bisa_menulis_catatan(): void
    {
        $viewer = User::factory()->viewer()->create();
        $device = Device::factory()->create();

        $this->actingAs($viewer)->post("/devices/{$device->id}/notes", [
            'category' => 'lainnya',
            'body' => 'Tes',
            'status' => 'open',
            'priority' => 'normal',
        ])->assertForbidden();

        $this->actingAs($viewer)->get("/devices/{$device->id}?tab=notes")->assertOk();
    }
}
