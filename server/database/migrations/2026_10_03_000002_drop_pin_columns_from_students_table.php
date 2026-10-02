<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'pin_algo',
                'pin_salt',
                'pin_iterations',
                'pin_hash',
                'pin_set_at',
                'pin_failed_attempts',
                'pin_locked_until',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('pin_algo', 30)->nullable();
            $table->string('pin_salt', 64)->nullable();
            $table->unsignedInteger('pin_iterations')->nullable();
            $table->string('pin_hash', 128)->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->unsignedSmallInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
        });
    }
};
