<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intermediate waypoints between pickup and final dropoff ("Add Stop"),
     * taxi rides only, added at booking time (fixed upfront pricing means
     * the whole route -- pickup -> stop 1 -> stop 2 -> dropoff -- has to be
     * known before the ride is requested, not changed mid-ride). The final
     * destination stays on rides.dropoff_latitude/longitude as before --
     * this table is only the stops in between, so existing code that reads
     * a ride's pickup/dropoff (admin panel, anomaly detection, live
     * tracking, etc.) keeps working unchanged.
     */
    public function up(): void
    {
        Schema::create('ride_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('latitude');
            $table->string('longitude');
            $table->string('address')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();

            $table->unique(['ride_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_stops');
    }
};
