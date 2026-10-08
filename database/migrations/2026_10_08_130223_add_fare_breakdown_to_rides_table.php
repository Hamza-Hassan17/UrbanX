<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 2 -- same snapshot concept as restaurant_orders, for taxi
 * rides and parcel/delivery rides. Written once, at the point a ride's
 * status flips to 'completed' (Driver/RideController::updateRideStatus),
 * since total_fare can still change before then (wait penalty). Also adds
 * distance_charged_km -- the rounded-up-to-next-km distance actually
 * billed, kept separate from the existing distance_km (actual distance)
 * per the brief's "store both actual and charged km" requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'fare_breakdown')) {
                $table->json('fare_breakdown')->nullable()->after('total_fare');
            }
            if (!Schema::hasColumn('rides', 'distance_charged_km')) {
                $table->unsignedInteger('distance_charged_km')->nullable()->after('distance_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['fare_breakdown', 'distance_charged_km']);
        });
    }
};
