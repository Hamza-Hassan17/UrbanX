<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Share of Delivery Fee switches from a percentage cut to a flat
 * PKR amount taken off every delivery fee -- see FareBreakdownService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'platform_share_percent')) {
                $table->dropColumn('platform_share_percent');
            }
            if (!Schema::hasColumn('system_settings', 'platform_share_amount')) {
                $table->decimal('platform_share_amount', 8, 2)->default(50.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'platform_share_amount')) {
                $table->dropColumn('platform_share_amount');
            }
            if (!Schema::hasColumn('system_settings', 'platform_share_percent')) {
                $table->decimal('platform_share_percent', 5, 2)->default(20.00);
            }
        });
    }
};
