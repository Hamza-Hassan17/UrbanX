<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 8 -- cash-in-hand ledger per rider. 'collected' entries are
 * written when a rider confirms COD cash on a food order or parcel;
 * 'settled' entries are written by Admin/Finance recording a deposit.
 * balance_after is a running-balance snapshot computed at write time (see
 * RiderCashService), so the Rider Cash page doesn't need to re-aggregate
 * the whole table on every load.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rider_cash_ledgers')) {
            return;
        }

        Schema::create('rider_cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->enum('entry_type', ['collected', 'settled']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            // Capped at 100 (not Laravel's default 255) -- see
            // 2026_10_08_124634_create_admin_activity_logs_table for why a
            // *_type string paired with a numeric id in a composite index
            // needs this on strict MySQL configs.
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('method')->nullable(); // settlement method (cash/bank transfer/...)
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rider_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_cash_ledgers');
    }
};
