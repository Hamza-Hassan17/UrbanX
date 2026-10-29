<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether drivers of this vehicle type must upload a vehicle video for
     * KYC (cars yes, bikes no). Additive only.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('vehicle_types', 'requires_vehicle_video')) {
            Schema::table('vehicle_types', function (Blueprint $table) {
                $table->boolean('requires_vehicle_video')->default(false)->after('is_delivery');
            });

            // Existing types: on for everything that isn't a bike.
            DB::table('vehicle_types')
                ->where('name', 'not like', '%bike%')
                ->update(['requires_vehicle_video' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicle_types', 'requires_vehicle_video')) {
            Schema::table('vehicle_types', function (Blueprint $table) {
                $table->dropColumn('requires_vehicle_video');
            });
        }
    }
};
