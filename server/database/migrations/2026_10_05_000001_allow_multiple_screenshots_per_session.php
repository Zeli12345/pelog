<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: index unique dipakai oleh foreign key usage_session_id, jadi
        // index pengganti harus dibuat lebih dulu sebelum unique-nya dihapus.
        Schema::table('screenshots', function (Blueprint $table) {
            $table->index('usage_session_id');
        });

        Schema::table('screenshots', function (Blueprint $table) {
            // Satu sesi kini boleh punya banyak screenshot (berkala + manual);
            // idempotensi dijaga oleh unique screenshot_uuid.
            $table->dropUnique('screenshots_usage_session_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->unique('usage_session_id');
        });

        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropIndex(['usage_session_id']);
        });
    }
};
