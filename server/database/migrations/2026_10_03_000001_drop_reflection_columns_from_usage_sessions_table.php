<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_sessions', function (Blueprint $table) {
            $table->dropColumn(['student_feedback', 'comprehension_level']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_sessions', function (Blueprint $table) {
            $table->text('student_feedback')->nullable();
            $table->enum('comprehension_level', ['sangat_paham', 'paham', 'cukup', 'kurang'])->nullable();
        });
    }
};
