<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 3 -- who absorbs a voucher's discount. Defaults to
 * 'platform' (restaurant payable unaffected), matching current behavior
 * where nothing is deducted from the restaurant's payable today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('voucher_codes', 'funded_by')) {
                $table->enum('funded_by', ['platform', 'restaurant'])->default('platform')->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('voucher_codes', function (Blueprint $table) {
            $table->dropColumn('funded_by');
        });
    }
};
