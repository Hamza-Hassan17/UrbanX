<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Part 5 -- taxi driver outstanding balance. Reuses the Batch 1
 * Part 8 rider_cash_ledgers table rather than a separate system: 'owed'
 * records what a taxi driver owes the platform (commission+SST kept out
 * of a cash fare), same balance_after running-total mechanics
 * RiderCashService::currentBalance()/recordSettlement() already have,
 * just a different entry_type and a different limit setting. Raw ALTER,
 * same approach the earlier enum-widening migrations in this app used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rider_cash_ledgers', 'entry_type')) {
            DB::statement("ALTER TABLE rider_cash_ledgers MODIFY entry_type ENUM('collected','settled','owed') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rider_cash_ledgers', 'entry_type')) {
            DB::statement("ALTER TABLE rider_cash_ledgers MODIFY entry_type ENUM('collected','settled') NOT NULL");
        }
    }
};
