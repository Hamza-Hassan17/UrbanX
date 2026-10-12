<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 review follow-up -- the SST-on-ride-fare split has always been
 * calculated tax-inclusive (FareBreakdownService::calculate() backs the
 * SST out of the driver's remaining share by dividing by 1+rate). The
 * client hasn't confirmed this is the right method, so it's made a
 * setting with the current behaviour as the default ('inclusive'), and a
 * second option ('exclusive' -- SST added on top of the share instead of
 * backed out of it) ready to switch to once confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'sst_ride_fare_method')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->enum('sst_ride_fare_method', ['inclusive', 'exclusive'])->default('inclusive')->after('sst_ride_fare_percent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'sst_ride_fare_method')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('sst_ride_fare_method');
            });
        }
    }
};
