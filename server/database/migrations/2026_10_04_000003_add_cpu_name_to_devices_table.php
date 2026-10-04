<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Nama/model prosesor dari kiosk (mis. "Intel(R) Core(TM) i5-10400 CPU @ 2.90GHz").
            $table->string('cpu_name', 120)->nullable()->after('cpu_usage_percent');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('cpu_name');
        });
    }
};
