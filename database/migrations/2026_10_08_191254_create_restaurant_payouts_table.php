<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 9 -- restaurant payable report + "Mark as paid", per
 * restaurant per period. sales_full_price/restaurant_funded_discounts/
 * commission/payable_amount are a snapshot of the report numbers at the
 * time this payout was recorded (same "freeze the numbers" principle as
 * the Part 2 fare_breakdown snapshot), not re-derived later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_payouts')) {
            return;
        }

        Schema::create('restaurant_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('sales_full_price', 10, 2);
            $table->decimal('restaurant_funded_discounts', 10, 2)->default(0);
            $table->decimal('commission', 10, 2);
            $table->decimal('payable_amount', 10, 2);
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_payouts');
    }
};
