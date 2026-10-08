<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 1 -- Pricing & Fees settings. Additive only, same single-row
 * system_settings table used by driver_commission_percent/sst_percent (see
 * FareBreakdownService). Food and parcel delivery fee tiers are stored
 * separately even though they share the same default values today, per the
 * brief ("may differ later"). restaurant_commission_percent is distinct
 * from the existing driver_commission_percent, which is taxi-ride-fare
 * specific -- restaurant commission is charged on the food price instead.
 * platform_share_percent is the delivery-rider equivalent of
 * driver_commission_percent (the platform's cut of the delivery fee).
 * SST on rider/driver income is intentionally NOT duplicated here -- the
 * brief asks for one shared rate for both taxi drivers and delivery riders,
 * and the existing sst_ride_fare_percent column already serves exactly that
 * role for taxi; Part 2 generalizes FareBreakdownService to reuse it for
 * delivery riders too instead of adding a second, redundant column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'food_first_km_fee')) {
                $table->decimal('food_first_km_fee', 8, 2)->default(150.00);
            }
            if (!Schema::hasColumn('system_settings', 'food_per_km_fee')) {
                $table->decimal('food_per_km_fee', 8, 2)->default(45.00);
            }
            if (!Schema::hasColumn('system_settings', 'food_max_distance_km')) {
                $table->unsignedTinyInteger('food_max_distance_km')->default(4);
            }
            if (!Schema::hasColumn('system_settings', 'parcel_first_km_fee')) {
                $table->decimal('parcel_first_km_fee', 8, 2)->default(150.00);
            }
            if (!Schema::hasColumn('system_settings', 'parcel_per_km_fee')) {
                $table->decimal('parcel_per_km_fee', 8, 2)->default(45.00);
            }
            if (!Schema::hasColumn('system_settings', 'parcel_max_distance_km')) {
                $table->unsignedTinyInteger('parcel_max_distance_km')->default(4);
            }
            if (!Schema::hasColumn('system_settings', 'restaurant_commission_percent')) {
                $table->decimal('restaurant_commission_percent', 5, 2)->default(15.00);
            }
            if (!Schema::hasColumn('system_settings', 'platform_share_percent')) {
                $table->decimal('platform_share_percent', 5, 2)->default(20.00);
            }
            if (!Schema::hasColumn('system_settings', 'rider_cash_limit')) {
                $table->decimal('rider_cash_limit', 10, 2)->default(5000.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'food_first_km_fee',
                'food_per_km_fee',
                'food_max_distance_km',
                'parcel_first_km_fee',
                'parcel_per_km_fee',
                'parcel_max_distance_km',
                'restaurant_commission_percent',
                'platform_share_percent',
                'rider_cash_limit',
            ]);
        });
    }
};
