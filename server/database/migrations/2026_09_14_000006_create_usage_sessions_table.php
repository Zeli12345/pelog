<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan: tabel domain memakai nama "usage_sessions" karena nama "sessions"
        // sudah dipakai Laravel untuk driver session database.
        Schema::create('usage_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->foreignId('device_id')->constrained('devices')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('user_type', ['student', 'staff'])->default('student');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->text('usage_purpose');

            $table->dateTime('started_at_client')->nullable();
            $table->dateTime('started_at_server')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->enum('close_reason', ['normal', 'recovery', 'shutdown', 'admin'])->nullable();

            $table->text('student_feedback')->nullable();
            $table->enum('comprehension_level', ['sangat_paham', 'paham', 'cukup', 'kurang'])->nullable();

            $table->unsignedInteger('duration_minutes')->default(0);
            $table->enum('sync_source', ['online', 'offline'])->default('online');
            $table->timestamps();

            $table->index(['device_id', 'closed_at']);
            $table->index('started_at_client');
            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_sessions');
    }
};
