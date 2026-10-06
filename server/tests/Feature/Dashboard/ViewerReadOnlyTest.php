<?php

namespace Tests\Feature\Dashboard;

use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        return User::factory()->viewer()->create();
    }

    private function admin(): User
    {
        return User::factory()->adminUtama()->create();
    }

    public function test_viewer_bisa_membaca_semua_master_data(): void
    {
        Student::factory()->create(['name' => 'Siswa Terlihat']);
        StaffMember::factory()->create(['name' => 'Pegawai Terlihat']);
        Subject::factory()->create(['code' => 'MELIHAT', 'name' => 'Mapel Terlihat']);

        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/students')->assertOk()->assertSee('Siswa Terlihat');
        $this->actingAs($viewer)->get('/staff')->assertOk()->assertSee('Pegawai Terlihat');
        $this->actingAs($viewer)->get('/subjects')->assertOk()->assertSee('Mapel Terlihat');
    }

    public function test_viewer_tidak_melihat_tombol_aksi_di_master_data(): void
    {
        Student::factory()->create();
        StaffMember::factory()->create();
        Subject::factory()->create();

        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/students')
            ->assertOk()
            ->assertDontSee('students/create')
            ->assertDontSee('students/import')
            ->assertDontSee('Hapus terpilih');

        $this->actingAs($viewer)->get('/staff')
            ->assertOk()
            ->assertDontSee('staff/create')
            ->assertDontSee('staff/import')
            ->assertDontSee('Hapus terpilih');

        $this->actingAs($viewer)->get('/subjects')
            ->assertOk()
            ->assertDontSee('Tambah Mata Pelajaran');
    }

    public function test_admin_tetap_melihat_tombol_aksi_di_master_data(): void
    {
        Student::factory()->create();

        $this->actingAs($this->admin())->get('/students')
            ->assertOk()
            ->assertSee('Tambah Siswa')
            ->assertSee('Hapus terpilih');
    }

    public function test_viewer_tidak_bisa_melakukan_aksi_tulis(): void
    {
        $viewer = $this->viewer();
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($viewer)->post('/students', [
            'nisn' => '1234567890',
            'name' => 'Selundupan',
            'class' => 'X',
            'birth_date' => '2009-01-01',
        ])->assertForbidden();

        $this->actingAs($viewer)->post('/students/bulk-delete', ['ids' => [$student->id]])->assertForbidden();
        $this->actingAs($viewer)->post('/staff', ['nip_id' => '12345678', 'name' => 'Selundupan', 'role' => 'teacher'])->assertForbidden();
        $this->actingAs($viewer)->post('/subjects', ['code' => 'X', 'name' => 'Selundupan'])->assertForbidden();
        $this->actingAs($viewer)->put("/subjects/{$subject->id}", ['code' => 'X', 'name' => 'Selundupan'])->assertForbidden();
    }

    public function test_viewer_tidak_bisa_membuka_halaman_khusus_admin(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/users')->assertForbidden();
        $this->actingAs($viewer)->get('/settings')->assertForbidden();
        $this->actingAs($viewer)->get('/audit')->assertForbidden();
    }

    public function test_viewer_tetap_bisa_melihat_pemantauan_dan_ekspor(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/dashboard')->assertOk();
        $this->actingAs($viewer)->get('/devices')->assertOk();
        $this->actingAs($viewer)->get('/sessions')->assertOk();
        $this->actingAs($viewer)->get('/reports')->assertOk();
        $this->actingAs($viewer)->get('/screenshots')->assertOk();
        $this->actingAs($viewer)->get('/reports/export?format=csv')->assertOk();
    }
}
