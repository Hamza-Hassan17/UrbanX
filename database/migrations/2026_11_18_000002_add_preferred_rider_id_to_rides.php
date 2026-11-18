<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin food bookings now go to the restaurant first. When the admin picked a
 * rider, he is remembered here and given the job once the restaurant accepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'preferred_rider_id')) {
                $table->unsignedBigInteger('preferred_rider_id')->nullable()->after('driver_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (Schema::hasColumn('rides', 'preferred_rider_id')) {
                $table->dropColumn('preferred_rider_id');
            }
        });
    }
};
