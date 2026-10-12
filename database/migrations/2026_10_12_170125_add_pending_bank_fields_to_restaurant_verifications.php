<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 review follow-up -- an already-approved restaurant changing its
 * bank account title/IBAN shouldn't be able to redirect its own payouts
 * on the spot. The new details sit here as pending until an admin
 * approves the change; payouts and the Restaurant Payable report
 * (Batch 1 Part 9) keep reading bank_account_title/bank_iban (the
 * approved ones) until then. The restaurant stays live/approved the
 * whole time -- this only gates the bank fields, not the restaurant's
 * overall approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_verifications') && !Schema::hasColumn('restaurant_verifications', 'pending_bank_account_title')) {
            Schema::table('restaurant_verifications', function (Blueprint $table) {
                $table->string('pending_bank_account_title')->nullable()->after('bank_account_title');
                $table->string('pending_bank_iban')->nullable()->after('bank_iban');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_verifications', 'pending_bank_account_title')) {
            Schema::table('restaurant_verifications', function (Blueprint $table) {
                $table->dropColumn(['pending_bank_account_title', 'pending_bank_iban']);
            });
        }
    }
};
