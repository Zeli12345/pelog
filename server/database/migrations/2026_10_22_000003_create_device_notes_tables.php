<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('usage_session_id')->nullable()->constrained('usage_sessions')->nullOnDelete()->cascadeOnUpdate();
            $table->string('category', 30)->default('lainnya');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->dateTime('occurred_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('vendor', 120)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['device_id', 'status']);
            $table->index(['device_id', 'created_at']);
            $table->index('category');
        });

        Schema::create('note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_note_id')->constrained('device_notes')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->string('original_name', 160);
            $table->string('mime', 80);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_attachments');
        Schema::dropIfExists('device_notes');
    }
};
