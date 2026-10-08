<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 2 -- snapshots the full money breakdown on the order at
 * creation time, so later changes to commission%/platform fee%/SST% in
 * Pricing & Fees never alter a historical order's numbers. See
 * FareBreakdownService::buildOrderSnapshot() for the shape stored here.
 * discount_funded_by is also captured here (copied from the voucher used,
 * see Part 3) since it's part of the same point-in-time snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_orders', 'fare_breakdown')) {
                $table->json('fare_breakdown')->nullable()->after('total_price');
            }
            if (!Schema::hasColumn('restaurant_orders', 'discount_funded_by')) {
                $table->enum('discount_funded_by', ['platform', 'restaurant'])->nullable()->after('discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['fare_breakdown', 'discount_funded_by']);
        });
    }
};
