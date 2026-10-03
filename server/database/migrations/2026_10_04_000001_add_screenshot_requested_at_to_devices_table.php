<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Diisi saat admin meminta screenshot langsung dari dashboard;
            // kiosk mengunggah pada heartbeat berikutnya lalu kolom ini dikosongkan.
            $table->timestamp('screenshot_requested_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('screenshot_requested_at');
        });
    }
};
