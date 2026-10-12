<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 review follow-up -- cash amount integrity. The rider-entered
 * cash_collected_amount is compared against the amount actually due
 * (computed server-side); the ledger always records the amount due, never
 * the rider's entry, so the ledger can't be gamed by under-reporting. A
 * mismatch doesn't block the delivery -- it completes as normal, but gets
 * flagged here for admin review, with both numbers kept for comparison.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_orders') && !Schema::hasColumn('restaurant_orders', 'cash_discrepancy_flagged')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->boolean('cash_discrepancy_flagged')->default(false)->after('payment_status');
                $table->decimal('cash_entered_amount', 10, 2)->nullable()->after('cash_discrepancy_flagged');
                $table->decimal('cash_due_amount', 10, 2)->nullable()->after('cash_entered_amount');
            });
        }

        if (Schema::hasTable('rides') && !Schema::hasColumn('rides', 'cash_discrepancy_flagged')) {
            Schema::table('rides', function (Blueprint $table) {
                $table->boolean('cash_discrepancy_flagged')->default(false)->after('payment_status');
                $table->decimal('cash_entered_amount', 10, 2)->nullable()->after('cash_discrepancy_flagged');
                $table->decimal('cash_due_amount', 10, 2)->nullable()->after('cash_entered_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_orders', 'cash_discrepancy_flagged')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropColumn(['cash_discrepancy_flagged', 'cash_entered_amount', 'cash_due_amount']);
            });
        }

        if (Schema::hasColumn('rides', 'cash_discrepancy_flagged')) {
            Schema::table('rides', function (Blueprint $table) {
                $table->dropColumn(['cash_discrepancy_flagged', 'cash_entered_amount', 'cash_due_amount']);
            });
        }
    }
};
