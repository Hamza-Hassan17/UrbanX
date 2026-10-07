<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the admin workspace split -- a nullable "service" tag
 * (ride / rental / food / parcel) on the tables that have no existing
 * column to tell which service a row belongs to. Null stays null where
 * it genuinely can't be determined (see the backfill command and the
 * Phase 4 report for exactly what could/couldn't be backfilled and why).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->string('service', 20)->nullable()->after('user_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('service', 20)->nullable()->after('booking_id');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('service', 20)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->dropColumn('service');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('service');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn('service');
        });
    }
};
