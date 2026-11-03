<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urban Eats "Contact Support" screen content, edited from the admin
     * panel. Single row. Additive only.
     */
    public function up(): void
    {
        if (!Schema::hasTable('eats_support_info')) {
            Schema::create('eats_support_info', function (Blueprint $table) {
                $table->id();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->text('about')->nullable();
                $table->string('facebook_url')->nullable();
                $table->string('instagram_url')->nullable();
                $table->string('twitter_url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eats_support_info');
    }
};
