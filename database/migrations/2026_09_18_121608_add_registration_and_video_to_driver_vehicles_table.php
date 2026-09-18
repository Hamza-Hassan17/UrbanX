<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->string('registration_paper')->nullable()->after('vehicle_images');
            $table->string('vehicle_video')->nullable()->after('registration_paper');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_vehicles', function (Blueprint $table) {
            $table->dropColumn(['registration_paper', 'vehicle_video']);
        });
    }
};
