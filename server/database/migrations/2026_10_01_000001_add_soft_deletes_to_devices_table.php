<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Soft delete dipakai saat admin menghapus perangkat dari dashboard:
            // baris tetap ada agar sesi historis tidak ikut hilang dan kiosk
            // bisa diberi sinyal "device_revoked" (HTTP 410) saat boot berikutnya.
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
