<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 6 -- who cancelled an order and why. 'cancelled' already
 * exists in restaurant_orders.status's enum (base migration), so only the
 * attribution/audit columns are new here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_orders', 'cancelled_by')) {
                $table->enum('cancelled_by', ['customer', 'restaurant', 'admin'])->nullable()->after('status');
            }
            if (!Schema::hasColumn('restaurant_orders', 'cancel_reason')) {
                $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('restaurant_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by', 'cancel_reason', 'cancelled_at']);
        });
    }
};
