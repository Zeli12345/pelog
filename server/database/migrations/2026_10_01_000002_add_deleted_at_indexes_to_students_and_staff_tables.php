<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom deleted_at untuk students & staff_members sudah dibuat oleh
     * migration awal (softDeletes). Migration ini hanya menambahkan index
     * agar query daftar normal (yang selalu memfilter deleted_at) tetap cepat.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });
    }
};
