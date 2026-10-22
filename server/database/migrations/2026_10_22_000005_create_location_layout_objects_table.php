<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_layout_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['device', 'table_rect', 'table_circle'])->default('device');
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete()->cascadeOnUpdate();
            $table->string('label', 60)->nullable();
            $table->decimal('x', 5, 2)->default(0);
            $table->decimal('y', 5, 2)->default(0);
            $table->decimal('w', 5, 2)->default(12);
            $table->decimal('h', 5, 2)->default(8);
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->string('color', 20)->nullable();
            $table->smallInteger('z_index')->default(0);
            $table->timestamps();

            $table->unique(['location_id', 'device_id']);
            $table->index(['location_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_layout_objects');
    }
};
