<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairToolTest extends TestCase
{
    use RefreshDatabase;

    private const TOOL_PATH = 'tools/PELOG-Revert.exe';

    protected function setUp(): void
    {
        parent::setUp();

        // Disk 'local' nyata tidak di-reset RefreshDatabase — pastikan mulai bersih.
        Storage::disk('local')->delete(self::TOOL_PATH);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->delete(self::TOOL_PATH);

        parent::tearDown();
    }

    /** PELOG-Revert.exe hanya untuk Admin IT — tamu & viewer tidak boleh. */
    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/downloads/repair-tool')->assertRedirect('/login');
    }

    public function test_viewer_dilarang_mengunduh_alat_perbaikan(): void
    {
        Storage::disk('local')->put(self::TOOL_PATH, 'PELOG-REVERT-CONTENTS');

        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/downloads/repair-tool')->assertForbidden();
    }

    public function test_admin_bisa_mengunduh_alat_perbaikan(): void
    {
        Storage::disk('local')->put(self::TOOL_PATH, 'PELOG-REVERT-CONTENTS');

        $this->actingAs(User::factory()->adminUtama()->create())
            ->get('/downloads/repair-tool')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment; filename="PELOG-Revert.exe"');
    }

    public function test_alat_hilang_maka_unduhan_404(): void
    {
        $this->actingAs(User::factory()->adminUtama()->create())
            ->get('/downloads/repair-tool')
            ->assertNotFound();
    }

    public function test_halaman_pengaturan_menampilkan_alat_hanya_saat_tersedia(): void
    {
        $admin = User::factory()->adminUtama()->create();

        // Berkas belum ada: tautan disembunyikan — Pengaturan tetap rapi.
        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk()
            ->assertDontSee('Alat perbaikan laptop');

        // Setelah berkas diunggah: tautan tampil kecil di kartu Installer.
        Storage::disk('local')->put(self::TOOL_PATH, 'PELOG-REVERT-CONTENTS');

        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Alat perbaikan laptop');
    }
}
