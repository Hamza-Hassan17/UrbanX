<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Batch 2 Part 4 -- denormalized approval flag, kept in sync with
 * restaurant_verifications.status whenever that changes (same pattern
 * restaurants.is_active already uses for its own simple on/off check) so
 * customer listing/search/order-placement can filter with a plain
 * where() instead of joining restaurant_verifications every time.
 *
 * Backfill: every restaurant that already existed before this migration
 * is marked approved, regardless of is_active -- "so nothing disappears"
 * per the brief. New restaurants default to false and need an actual
 * approval to go live.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurants') && !Schema::hasColumn('restaurants', 'is_approved')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->boolean('is_approved')->default(false)->after('is_active');
            });

            DB::table('restaurants')->update(['is_approved' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurants', 'is_approved')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropColumn('is_approved');
            });
        }
    }
};
