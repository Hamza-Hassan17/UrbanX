<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urban Eats home screen carousel, managed from the admin panel.
     * Additive only.
     */
    public function up(): void
    {
        if (!Schema::hasTable('home_banners')) {
            Schema::create('home_banners', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('image');
                $table->enum('link_type', ['none', 'restaurant', 'category'])->default('none');
                $table->unsignedBigInteger('link_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
