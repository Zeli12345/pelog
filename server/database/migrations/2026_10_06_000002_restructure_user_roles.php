<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restrukturisasi peran akun dashboard:
        //   admin_it  -> admin_utama  (Admin Utama: pemegang akun awal)
        //   guru      -> viewer       (Viewer: akun baca-saja)
        // Nilai data lama diperbarui DULU sebelum enum diubah agar baris
        // yang ada tetap valid ketika kolom diubah (MySQL strict mode).
        DB::table('users')->where('role', 'admin_it')->update(['role' => 'admin_utama']);
        DB::table('users')->where('role', 'guru')->update(['role' => 'viewer']);

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
