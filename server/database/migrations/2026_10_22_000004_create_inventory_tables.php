<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete()->cascadeOnUpdate();
            $table->string('unit', 20)->default('pcs');
            $table->integer('quantity')->default(0);
            $table->integer('min_quantity')->default(0);
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('position_detail', 120)->nullable();
            $table->enum('condition', ['new', 'good', 'damaged', 'repair'])->default('good');
            $table->json('attributes')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['is_active', 'name']);
            $table->index(['location_id', 'is_active']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['in', 'out', 'adjust', 'transfer', 'repair', 'return', 'write_off']);
            $table->unsignedInteger('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->foreignId('location_from_id')->nullable()->constrained('locations')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('location_to_id')->nullable()->constrained('locations')->nullOnDelete()->cascadeOnUpdate();
            $table->string('reference', 160)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['inventory_item_id', 'occurred_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
    }
};
