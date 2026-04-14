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
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->decimal('base_fare', 8, 2)->nullable();
            $table->decimal('first_km_price', 8, 2)->nullable();
            $table->decimal('other_km_price', 8, 2)->nullable();
            $table->integer('seats')->nullable();
            $table->enum('is_delivery', ['0', '1'])->default('0');
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
