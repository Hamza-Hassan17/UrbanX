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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('restaurant_category_id')->nullable()->constrained('restaurant_categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('address');
            $table->string('latitude', 10, 8)->nullable();
            $table->string('longitude', 11, 8)->nullable();
            $table->json('weekly_schedule')->nullable();
            $table->json('special_opening_hours')->nullable();
            $table->enum('is_open', ['open', 'closed'])->default('closed');
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
