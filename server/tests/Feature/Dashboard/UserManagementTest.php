<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminUtama(): User
    {
        return User::factory()->adminUtama()->create();
    }

    private function subAdmin(): User
    {
        return User::factory()->subAdmin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pengguna Baru',
            'email' => 'pengguna.baru@pelog.local',
            'password' => 'Rahasia!123',
            'password_confirmation' => 'Rahasia!123',
            'role' => 'viewer',
        ], $overrides);
    }

    public function test_viewer_tidak_bisa_membuka_atau_mengubah_pengguna(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/users')->assertForbidden();
        $this->actingAs($viewer)->post('/users', $this->payload())->assertForbidden();
    }

    public function test_admin_dan_sub_admin_bisa_membuka_halaman_pengguna(): void
    {
        $this->actingAs($this->adminUtama())
            ->get('/users')
            ->assertOk()
            ->assertSee('Pengguna Dashboard');

        $this->actingAs($this->subAdmin())->get('/users')->assertOk();
    }

    public function test_sub_admin_hanya_bisa_membuat_akun_viewer(): void
    {
        $sub = $this->subAdmin();

        $this->actingAs($sub)
            ->post('/users', $this->payload())
            ->assertRedirect(route('users.index'));

        $this->assertTrue(
            User::query()->where('email', 'pengguna.baru@pelog.local')->firstOrFail()->isViewer()
        );

        $this->actingAs($sub)
            ->post('/users', $this->payload([
                'name' => 'Admin Selundupan',
                'email' => 'admin.selundupan@pelog.local',
                'role' => 'sub_admin',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertNull(User::query()->where('email', 'admin.selundupan@pelog.local')->first());
    }

    public function test_admin_utama_bisa_membuat_akun_admin_dan_viewer(): void
    {
        $admin = $this->adminUtama();

        $this->actingAs($admin)
            ->post('/users', $this->payload([
                'name' => 'Admin Kedua',
                'email' => 'admin.kedua@pelog.local',
                'role' => 'sub_admin',
            ]))
            ->assertRedirect(route('users.index'));

        $this->assertTrue(
            User::query()->where('email', 'admin.kedua@pelog.local')->firstOrFail()->isSubAdmin()
        );

        $this->actingAs($admin)
            ->post('/users', $this->payload([
                'name' => 'Admin Utama Selundupan',
                'email' => 'utama.selundupan@pelog.local',
                'role' => 'admin_utama',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertNull(User::query()->where('email', 'utama.selundupan@pelog.local')->first());
    }

    public function test_sub_admin_tidak_bisa_mengelola_akun_admin(): void
    {
        $sub = $this->subAdmin();
        $otherSub = $this->subAdmin();
        $utama = $this->adminUtama();

        $this->actingAs($sub)
            ->put("/users/{$otherSub->id}", ['name' => 'Diubah', 'email' => $otherSub->email, 'role' => 'sub_admin'])
            ->assertForbidden();

        $this->actingAs($sub)->delete("/users/{$otherSub->id}")->assertForbidden();
        $this->actingAs($sub)->post("/users/{$otherSub->id}/toggle")->assertForbidden();

        $this->actingAs($sub)
            ->put("/users/{$utama->id}", ['name' => 'Diubah', 'email' => $utama->email, 'role' => 'viewer'])
            ->assertForbidden();

        $this->assertSame($otherSub->name, $otherSub->refresh()->name);
    }

    public function test_sub_admin_bisa_mengelola_akun_viewer(): void
    {
        $sub = $this->subAdmin();
        $viewer = User::factory()->viewer()->create(['name' => 'Viewer Lama']);

        $this->actingAs($sub)
            ->put("/users/{$viewer->id}", ['name' => 'Viewer Diubah', 'email' => $viewer->email, 'role' => 'viewer'])
            ->assertRedirect(route('users.index'));

        $this->assertSame('Viewer Diubah', $viewer->refresh()->name);

        $this->actingAs($sub)->post("/users/{$viewer->id}/toggle")->assertRedirect();
        $this->assertFalse($viewer->refresh()->is_active);

        $this->actingAs($sub)->delete("/users/{$viewer->id}")->assertRedirect(route('users.index'));
        $this->assertNull(User::query()->find($viewer->id));
    }

    public function test_admin_utama_bisa_mengelola_akun_admin_dan_viewer(): void
    {
        $admin = $this->adminUtama();
        $sub = $this->subAdmin();
        $viewer = User::factory()->viewer()->create(['name' => 'Viewer Biasa']);

        $this->actingAs($admin)
            ->put("/users/{$sub->id}", ['name' => 'Admin Diubah', 'email' => $sub->email, 'role' => 'sub_admin'])
            ->assertRedirect(route('users.index'));

        $this->assertSame('Admin Diubah', $sub->refresh()->name);

        // Viewer boleh dinaikkan menjadi admin.
        $this->actingAs($admin)
            ->put("/users/{$viewer->id}", ['name' => $viewer->name, 'email' => $viewer->email, 'role' => 'sub_admin'])
            ->assertRedirect(route('users.index'));

        $this->assertTrue($viewer->refresh()->isSubAdmin());

        $this->actingAs($admin)->delete("/users/{$viewer->id}")->assertRedirect(route('users.index'));
        $this->assertNull(User::query()->find($viewer->id));
    }

    public function test_akun_sendiri_dan_akun_admin_utama_terkunci_dari_halaman_pengguna(): void
    {
        $admin = $this->adminUtama();
        $otherUtama = $this->adminUtama();
        $sub = $this->subAdmin();

        // Akun admin utama lain (dan karenanya juga akun sendiri) terkunci.
        $this->actingAs($admin)
            ->put("/users/{$otherUtama->id}", ['name' => 'X', 'email' => $otherUtama->email, 'role' => 'sub_admin'])
            ->assertForbidden();

        $this->actingAs($admin)->delete("/users/{$otherUtama->id}")->assertForbidden();
        $this->actingAs($admin)->put("/users/{$admin->id}", ['name' => 'X', 'email' => $admin->email, 'role' => 'sub_admin'])->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

        // Sub admin tidak mengelola akunnya sendiri dari halaman ini.
        $this->actingAs($sub)
            ->put("/users/{$sub->id}", ['name' => 'X', 'email' => $sub->email, 'role' => 'viewer'])
            ->assertForbidden();
    }

    public function test_validasi_email_unik_dan_password_minimal(): void
    {
        $admin = $this->adminUtama();
        User::factory()->viewer()->create(['email' => 'sudah.ada@pelog.local']);

        $this->actingAs($admin)
            ->post('/users', $this->payload([
                'email' => 'sudah.ada@pelog.local',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ]))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_password_disimpan_sebagai_hash(): void
    {
        $this->actingAs($this->adminUtama())
            ->post('/users', $this->payload())
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'pengguna.baru@pelog.local')->firstOrFail();

        $this->assertNotSame('Rahasia!123', $created->password);
        $this->assertTrue(Hash::check('Rahasia!123', $created->password));
    }

    public function test_perubahan_akun_dicatat_di_audit(): void
    {
        $admin = $this->adminUtama();

        $this->actingAs($admin)->post('/users', $this->payload());
        $viewer = User::query()->where('email', 'pengguna.baru@pelog.local')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_created']);

        $this->actingAs($admin)->put("/users/{$viewer->id}", [
            'name' => 'Nama Diubah',
            'email' => $viewer->email,
            'role' => 'viewer',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_updated']);

        $this->actingAs($admin)->post("/users/{$viewer->id}/toggle");
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_deactivated']);

        $this->actingAs($admin)->post("/users/{$viewer->id}/toggle");
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_activated']);

        $this->actingAs($admin)->delete("/users/{$viewer->id}");
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_deleted']);
    }

    public function test_akun_nonaktif_langsung_di_logout_dari_dashboard(): void
    {
        $viewer = User::factory()->viewer()->create(['is_active' => false]);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_sub_admin_tidak_bisa_menaikkan_viewer_menjadi_admin(): void
    {
        $sub = $this->subAdmin();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($sub)
            ->put("/users/{$viewer->id}", [
                'name' => $viewer->name,
                'email' => $viewer->email,
                'role' => 'sub_admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($viewer->refresh()->isViewer());
    }

    public function test_halaman_tambah_pengguna_tidak_menawarkan_peran_admin_utama(): void
    {
        $this->actingAs($this->adminUtama())
            ->get('/users/create')
            ->assertOk()
            ->assertSee('value="sub_admin"', false)
            ->assertSee('value="viewer"', false)
            ->assertDontSee('value="admin_utama"', false);

        $this->actingAs($this->subAdmin())
            ->get('/users/create')
            ->assertOk()
            ->assertSee('value="viewer"', false)
            ->assertDontSee('value="sub_admin"', false);
    }

    public function test_email_boleh_tetap_saat_edit_tapi_tidak_boleh_duplikat(): void
    {
        $admin = $this->adminUtama();
        $viewer = User::factory()->viewer()->create(['email' => 'edit.email@pelog.local']);
        User::factory()->viewer()->create(['email' => 'dipakai.lain@pelog.local']);

        // Tetap memakai email sendiri: boleh.
        $this->actingAs($admin)
            ->put("/users/{$viewer->id}", ['name' => 'Tetap', 'email' => 'edit.email@pelog.local', 'role' => 'viewer'])
            ->assertSessionHasNoErrors();

        // Memakai email akun lain: ditolak.
        $this->actingAs($admin)
            ->put("/users/{$viewer->id}", ['name' => 'Tetap', 'email' => 'dipakai.lain@pelog.local', 'role' => 'viewer'])
            ->assertSessionHasErrors('email');
    }

    public function test_form_pengguna_tanpa_toggle_aktif_dan_kartu_peran_reaktif(): void
    {
        $this->actingAs($this->adminUtama())
            ->get('/users/create')
            ->assertOk()
            ->assertDontSee('Akun aktif')
            ->assertDontSee('Sampaikan kata sandi')
            ->assertSee('peer-checked:border-navy-500', false)
            ->assertSee('value="sub_admin"', false)
            ->assertSee('value="viewer"', false);
    }

    public function test_akun_baru_selalu_aktif(): void
    {
        $this->actingAs($this->adminUtama())
            ->post('/users', $this->payload())
            ->assertRedirect(route('users.index'));

        $this->assertTrue(
            User::query()->where('email', 'pengguna.baru@pelog.local')->firstOrFail()->is_active
        );
    }
}
