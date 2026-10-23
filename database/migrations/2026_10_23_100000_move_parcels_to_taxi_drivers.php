<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parcels move from delivery riders (Delivery app) to taxi drivers (Driver
 * app). New parcels get ride_type 'parcel' and the admin-chosen taxi
 * vehicle type (system_settings.parcel_vehicle_type_id). Parcels already
 * in the system keep ride_type 'delivery' and are left as they are.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rides MODIFY ride_type ENUM('ride','delivery','parcel') NOT NULL DEFAULT 'ride'");

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'parcel_vehicle_type_id')) {
                $table->foreignId('parcel_vehicle_type_id')->nullable()->constrained('vehicle_types')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'parcel_vehicle_type_id')) {
                $table->dropConstrainedForeignId('parcel_vehicle_type_id');
            }
        });

        // The 'parcel' enum value is left in place: removing it would fail
        // (or lose data) once any parcel has been booked.
    }
};
