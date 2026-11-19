<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers dashboard: a frequent customer with no order for this many
 * days is "At risk" (admin can win them back with a personal offer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'customer_at_risk_days')) {
                $table->unsignedSmallInteger('customer_at_risk_days')->default(14);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'customer_at_risk_days')) {
                $table->dropColumn('customer_at_risk_days');
            }
        });
    }
};
