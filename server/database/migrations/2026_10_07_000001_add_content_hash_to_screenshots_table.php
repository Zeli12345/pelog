<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sidik isi (sha256) untuk mendeteksi unggahan screenshot yang benar-
        // benar identik dalam jendela singkat (mis. capture interval dan
        // permintaan manual bertabrakan di detik yang sama).
        Schema::table('screenshots', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('screenshot_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
