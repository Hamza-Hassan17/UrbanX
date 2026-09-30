<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A passenger browsing the "choose a trip" screen before any ride
     * exists -- there's no ride_id yet to hang a nearby.drivers broadcast
     * off of (unlike the searching-ride case), so this tracks who's
     * currently watching, where, and for which vehicle type, so idle
     * driver pings know which riders to push updates to.
     */
    public function up(): void
    {
        Schema::create('pre_ride_driver_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_ride_driver_watches');
    }
};
