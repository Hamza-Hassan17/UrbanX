<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3 Part 6 -- "No driver found" handling. SWITCHED OFF until the
 * client confirms (system_settings.no_driver_handling defaults to false);
 * with it off nothing in the app ever sets the new status.
 *
 * - rides.status gains 'no_driver_found' (additive enum change).
 * - rides.no_driver_found_at: when it was marked.
 * - rides.dispatch_restarted_at + ride_offers.superseded_at: a customer
 *   retry marks the earlier offers superseded (never deleted) so drivers
 *   who were already offered the job can be offered it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rides MODIFY status ENUM('requested','accepted','en_route','arrived','started','completed','cancelled','delivery_failed','no_driver_found') DEFAULT 'requested'");

        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'no_driver_found_at')) {
                $table->timestamp('no_driver_found_at')->nullable();
            }
            if (!Schema::hasColumn('rides', 'dispatch_restarted_at')) {
                $table->timestamp('dispatch_restarted_at')->nullable();
            }
        });

        Schema::table('ride_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('ride_offers', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable();
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'no_driver_handling')) {
                $table->boolean('no_driver_handling')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'no_driver_handling')) {
                $table->dropColumn('no_driver_handling');
            }
        });

        Schema::table('ride_offers', function (Blueprint $table) {
            if (Schema::hasColumn('ride_offers', 'superseded_at')) {
                $table->dropColumn('superseded_at');
            }
        });

        Schema::table('rides', function (Blueprint $table) {
            foreach (['no_driver_found_at', 'dispatch_restarted_at'] as $column) {
                if (Schema::hasColumn('rides', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // The enum value is left in place: removing it would fail (or lose
        // data) if any ride already has that status.
    }
};
