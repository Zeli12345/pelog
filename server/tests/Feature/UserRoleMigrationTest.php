<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi: migrasi restrukturisasi peran harus aman dijalankan pada database
 * yang SUDAH berisi baris lama (admin_it/guru) — produksi memiliki baris ini.
 * Tes biasa (RefreshDatabase) tidak menangkap masalah ini karena migrasi
 * dijalankan pada database kosong.
 */
class UserRoleMigrationTest extends TestCase
{
    public function test_migrasi_restrukturisasi_peran_aman_untuk_data_lama(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pelog_role_test_');

        config([
            'database.connections.role_migration_test' => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        $connection = DB::connection('role_migration_test');
        $previousDefault = config('database.default');

        try {
            // Skema versi LAMA (sesuai migrasi users + add_role sebelum restrukturisasi).
            Schema::connection('role_migration_test')->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->enum('role', ['admin_it', 'guru'])->default('guru');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });

            $connection->table('users')->insert([
                ['name' => 'Admin Lama', 'email' => 'lama.admin@pelog.local', 'password' => 'x', 'role' => 'admin_it', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Guru Lama', 'email' => 'lama.guru@pelog.local', 'password' => 'x', 'role' => 'guru', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);

            // Jalankan migrasi ASLI terhadap skema yang berisi data lama.
            config(['database.default' => 'role_migration_test']);

            $migration = require base_path('database/migrations/2026_10_06_000002_restructure_user_roles.php');
            $migration->up();

            $this->assertSame('admin_utama', $connection->table('users')->where('email', 'lama.admin@pelog.local')->value('role'));
            $this->assertSame('viewer', $connection->table('users')->where('email', 'lama.guru@pelog.local')->value('role'));

            // down() juga harus aman dengan data yang sudah dikonversi.
            $migration->down();

            $this->assertSame('admin_it', $connection->table('users')->where('email', 'lama.admin@pelog.local')->value('role'));
            $this->assertSame('guru', $connection->table('users')->where('email', 'lama.guru@pelog.local')->value('role'));
        } finally {
            config(['database.default' => $previousDefault]);
            DB::purge('role_migration_test');
            @unlink($path);
        }
    }
}
