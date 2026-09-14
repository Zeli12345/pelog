<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('screenshot_uuid')->unique();
            $table->foreignId('usage_session_id')->constrained('usage_sessions')->cascadeOnDelete();
            $table->enum('format', ['webp', 'jpeg']);
            $table->string('path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->dateTime('captured_at_client')->nullable();
            $table->timestamps();

            // Satu sesi hanya boleh memiliki satu screenshot.
            $table->unique('usage_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
