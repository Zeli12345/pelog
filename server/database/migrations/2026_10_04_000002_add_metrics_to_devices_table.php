<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Metrik ringan dari kiosk (dikirim saat mulai sesi & tiap heartbeat).
            $table->unsignedTinyInteger('cpu_usage_percent')->nullable()->after('screenshot_requested_at');
            $table->unsignedTinyInteger('ram_usage_percent')->nullable()->after('cpu_usage_percent');
            $table->unsignedSmallInteger('ram_total_gb')->nullable()->after('ram_usage_percent');
            $table->string('gpu_name', 120)->nullable()->after('ram_total_gb');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['cpu_usage_percent', 'ram_usage_percent', 'ram_total_gb', 'gpu_name']);
        });
    }
};
