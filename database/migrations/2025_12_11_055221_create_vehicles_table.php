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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_brand_id')->constrained('car_brands')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('car_model');
            $table->string('car_fuel_type');
            $table->string('year')->nullable();
            $table->decimal('price_per_hour', 12, 2);
            $table->decimal('price_per_day', 12, 2);
            $table->decimal('price_per_week', 12, 2);
            $table->decimal('price_per_month', 12, 2);
            $table->enum('transmission', ['automatic', 'manual', 'semi-automatic', 'cvt'])->default('automatic');
            $table->string('color')->nullable();
            $table->integer('seats')->unsigned()->nullable();
            $table->string('main_image')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->enum('is_featured', ['0', '1'])->default('0');
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
