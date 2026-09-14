<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('hostname', 100)->unique();
            $table->string('label', 50)->nullable();               // contoh: LAB-BL-01
            $table->string('device_token_hash', 64)->nullable();   // sha256 hex dari device_token
            $table->json('mac_list')->nullable();
            $table->enum('device_type', ['pc', 'laptop'])->default('laptop');
            $table->string('location_label', 100)->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'offline'])->default('available');
            $table->unsignedInteger('storage_total_gb')->default(0);
            $table->unsignedInteger('storage_used_gb')->default(0);
            $table->string('agent_version', 30)->nullable();
            $table->string('windows_version', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
