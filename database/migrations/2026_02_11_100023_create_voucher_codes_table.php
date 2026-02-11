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
        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('discount_amount', 8, 2);
            $table->enum('discount_type', ['fixed', 'percentage']);
            $table->decimal('minimum_purchase', 8, 2)->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->dateTime('expires_at');
            $table->text('description')->nullable();
            $table->enum('is_active', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_codes');
    }
};
