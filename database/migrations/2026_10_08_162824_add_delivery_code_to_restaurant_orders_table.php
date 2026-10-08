<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 7 -- 4-digit proof-of-delivery code for food orders,
 * generated at placeOrder() time, shown to the customer, and required
 * from the rider to mark delivered. delivery_code_attempts counts failed
 * entries (capped at 5, see DeliveryController::updateDeliveryStatus);
 * delivery_override_* records an admin's manual override when the rider
 * can't get the code (lost phone, customer unreachable, etc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_orders', 'delivery_code')) {
                $table->string('delivery_code', 4)->nullable()->after('status');
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_code_attempts')) {
                $table->unsignedTinyInteger('delivery_code_attempts')->default(0)->after('delivery_code');
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_code_locked')) {
                $table->boolean('delivery_code_locked')->default(false)->after('delivery_code_attempts');
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_override_by')) {
                $table->foreignId('delivery_override_by')->nullable()->after('delivery_code_locked')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_override_reason')) {
                $table->string('delivery_override_reason', 500)->nullable()->after('delivery_override_by');
            }
            if (!Schema::hasColumn('restaurant_orders', 'delivery_override_at')) {
                $table->timestamp('delivery_override_at')->nullable()->after('delivery_override_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_code',
                'delivery_code_attempts',
                'delivery_code_locked',
                'delivery_override_by',
                'delivery_override_reason',
                'delivery_override_at',
            ]);
        });
    }
};
