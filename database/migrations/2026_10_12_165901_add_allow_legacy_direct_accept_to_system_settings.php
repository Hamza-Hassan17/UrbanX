<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 review follow-up -- lets Super Admin require a valid,
 * unexpired pending offer before accept-ride succeeds, once updated app
 * builds (which actually poll and see the offer) are rolled out.
 * Default true (on) so nothing changes until this is deliberately
 * switched off -- a driver tapping Accept straight from a push, with no
 * prior poll, still works exactly as it always has.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_settings') && !Schema::hasColumn('system_settings', 'allow_legacy_direct_accept')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->boolean('allow_legacy_direct_accept')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'allow_legacy_direct_accept')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('allow_legacy_direct_accept');
            });
        }
    }
};
