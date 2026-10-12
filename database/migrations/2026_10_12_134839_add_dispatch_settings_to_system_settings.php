<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Part 1 -- Dispatch settings (offer timeout, re-offer, stale
 * thresholds, taxi outstanding limit). Same system_settings single-row
 * pattern Batch 1 Part 1 used for Pricing & Fees.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'offer_acceptance_timeout_seconds')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->unsignedInteger('offer_acceptance_timeout_seconds')->default(30);
                $table->unsignedInteger('max_reoffer_attempts')->default(5);
                $table->unsignedInteger('stale_unassigned_minutes')->default(5);
                $table->unsignedInteger('stale_restaurant_accept_minutes')->default(5);
                $table->unsignedInteger('stale_ready_for_pickup_minutes')->default(10);
                $table->decimal('taxi_outstanding_limit', 10, 2)->default(5000.00);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'offer_acceptance_timeout_seconds')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn([
                    'offer_acceptance_timeout_seconds',
                    'max_reoffer_attempts',
                    'stale_unassigned_minutes',
                    'stale_restaurant_accept_minutes',
                    'stale_ready_for_pickup_minutes',
                    'taxi_outstanding_limit',
                ]);
            });
        }
    }
};
