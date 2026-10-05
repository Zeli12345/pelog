<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fitur auto-shutdown saat idle menutup sesi dengan alasan "idle"
        // (lihat App\Enums\CloseReason::Idle), tetapi enum kolomnya belum
        // memuat nilai tersebut. MySQL/MariaDB strict mode menolak UPDATE
        // dengan error 1265 "Data truncated for column 'close_reason'",
        // sehingga sesi idle gagal ditutup di server (end & sync offline).
        Schema::table('usage_sessions', function (Blueprint $table) {
            $table->enum('close_reason', ['normal', 'recovery', 'shutdown', 'admin', 'idle'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('usage_sessions', function (Blueprint $table) {
            $table->enum('close_reason', ['normal', 'recovery', 'shutdown', 'admin'])
                ->nullable()
                ->change();
        });
    }
};
