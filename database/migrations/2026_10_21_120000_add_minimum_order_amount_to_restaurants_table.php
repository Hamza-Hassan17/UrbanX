<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3 Part 3 -- per-restaurant minimum food subtotal. Null = no minimum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurants', 'minimum_order_amount')) {
                $table->decimal('minimum_order_amount', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'minimum_order_amount')) {
                $table->dropColumn('minimum_order_amount');
            }
        });
    }
};
