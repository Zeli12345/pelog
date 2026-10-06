<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_default_adalah_viewer(): void
    {
        $default = User::factory()->create();

        $this->assertSame(UserRole::Viewer, $default->role);
        $this->assertSame('Viewer', $default->role->label());
        $this->assertTrue($default->isViewer());
        $this->assertFalse($default->isAdmin());
    }

    public function test_kedua_tipe_admin_berlabel_sama_admin(): void
    {
        $utama = User::factory()->adminUtama()->create();
        $sub = User::factory()->subAdmin()->create();

        $this->assertSame('admin_utama', $utama->role->value);
        $this->assertSame('sub_admin', $sub->role->value);
        $this->assertSame('Admin', $utama->role->label());
        $this->assertSame('Admin', $sub->role->label());
    }

    public function test_model_mengenali_kewenangan_setiap_peran(): void
    {
        $utama = User::factory()->adminUtama()->make();
        $sub = User::factory()->subAdmin()->make();
        $viewer = User::factory()->viewer()->make();

        $this->assertTrue($utama->isAdminUtama());
        $this->assertFalse($utama->isSubAdmin());
        $this->assertTrue($utama->isAdmin());
        $this->assertFalse($utama->isViewer());

        $this->assertFalse($sub->isAdminUtama());
        $this->assertTrue($sub->isSubAdmin());
        $this->assertTrue($sub->isAdmin());
        $this->assertFalse($sub->isViewer());

        $this->assertFalse($viewer->isAdminUtama());
        $this->assertFalse($viewer->isSubAdmin());
        $this->assertFalse($viewer->isAdmin());
        $this->assertTrue($viewer->isViewer());
    }
}
