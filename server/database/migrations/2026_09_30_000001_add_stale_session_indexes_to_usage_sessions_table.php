<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_sessions', function (Blueprint $table) {
            // Mempercepat pencarian sesi menggantung: closed_at IS NULL + last_heartbeat_at.
            $table->index(['closed_at', 'last_heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_sessions', function (Blueprint $table) {
            $table->dropIndex(['closed_at', 'last_heartbeat_at']);
        });
    }
};
