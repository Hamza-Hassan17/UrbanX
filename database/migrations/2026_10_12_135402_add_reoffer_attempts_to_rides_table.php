<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Part 2 -- counts how many times a job has been (re-)offered to a
 * driver/rider. Used by Part 3's stale-job alert (hits max_reoffer_attempts
 * with no acceptance) and surfaced on the ride detail page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rides') && !Schema::hasColumn('rides', 'reoffer_attempts')) {
            Schema::table('rides', function (Blueprint $table) {
                $table->unsignedInteger('reoffer_attempts')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rides', 'reoffer_attempts')) {
            Schema::table('rides', function (Blueprint $table) {
                $table->dropColumn('reoffer_attempts');
            });
        }
    }
};
