<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 9 -- "Mark as paid" record for a driver/rider payout.
 * Doesn't replace PayrollController's earnings calculation (that stays
 * live, from the Part 2 snapshot) -- this is purely a paid/unpaid audit
 * trail for a given period, visible to Finance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_payouts')) {
            return;
        }

        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 10, 2);
            $table->string('method');
            $table->string('reference')->nullable();
            $table->foreignId('paid_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['driver_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};
