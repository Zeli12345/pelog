<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->char('nisn', 10)->unique();
            $table->string('name', 150);
            $table->string('class', 50);

            // PIN (PBKDF2-HMAC-SHA256, kompatibel PHP <-> C#)
            $table->string('pin_algo', 30)->nullable();
            $table->string('pin_salt', 64)->nullable();
            $table->unsignedInteger('pin_iterations')->nullable();
            $table->string('pin_hash', 128)->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->unsignedSmallInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
