<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the old Firebase RTDB `restaurant_orders/{id}/rider_location`
     * node, which the admin dashboard's live map (CustomRideController::
     * liveTrackingData()) polled via getValue() for the rider's current
     * position. A WebSocket broadcast is fire-and-forget -- it has no
     * queryable "current value" the way Firebase RTDB did, so the dashboard's
     * poll needs an actual persisted last-known location to read from instead.
     */
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->decimal('rider_latitude', 10, 7)->nullable()->after('delivery_lang');
            $table->decimal('rider_longitude', 10, 7)->nullable()->after('rider_latitude');
            $table->timestamp('rider_location_updated_at')->nullable()->after('rider_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropColumn(['rider_latitude', 'rider_longitude', 'rider_location_updated_at']);
        });
    }
};
