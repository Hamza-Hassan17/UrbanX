<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('restaurant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('restaurant_menu_id')->nullable()->constrained('restaurant_menus')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('discount_percentage')->default(0);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->enum('is_available', ['0', '1'])->default('1');
            $table->enum('is_featured', ['0', '1'])->default('0');
            $table->integer('preparation_time')->nullable();
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_items');
    }
};
