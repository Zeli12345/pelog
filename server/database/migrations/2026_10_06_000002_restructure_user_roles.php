<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Longgarkan enum dulu (superset) agar baris lama
        // (admin_it/guru) tetap valid saat kolom diubah — MySQL strict
        // menolak UPDATE ke nilai di luar enum.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_it', 'guru', 'admin_utama', 'sub_admin', 'viewer'])
                ->default('guru')
                ->change();
        });

        // Konversi data: admin_it -> admin_utama, guru -> viewer.
        DB::table('users')->where('role', 'admin_it')->update(['role' => 'admin_utama']);
        DB::table('users')->where('role', 'guru')->update(['role' => 'viewer']);

        // Sempitkan ke daftar peran final.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_utama', 'sub_admin', 'viewer'])
                ->default('viewer')
                ->change();
        });
    }

    public function down(): void
    {
        // Longgarkan enum dulu (superset) sebelum data dikembalikan.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_it', 'guru', 'admin_utama', 'sub_admin', 'viewer'])
                ->default('viewer')
                ->change();
        });

        DB::table('users')->whereIn('role', ['admin_utama', 'sub_admin'])->update(['role' => 'admin_it']);
        DB::table('users')->where('role', 'viewer')->update(['role' => 'guru']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin_it', 'guru'])
                ->default('guru')
                ->change();
        });
    }
};
