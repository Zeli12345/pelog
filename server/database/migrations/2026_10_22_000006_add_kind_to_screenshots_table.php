<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->enum('kind', ['screen', 'webcam'])->default('screen')->after('content_hash');
            $table->index(['usage_session_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropIndex(['usage_session_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
