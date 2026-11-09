<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-restaurant rates. Empty commission = the Pricing & Fees default;
     * empty SST / tax = 0.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('minimum_order_amount');
            $table->decimal('sst_percent', 5, 2)->nullable()->after('commission_percent');
            $table->decimal('tax_percent', 5, 2)->nullable()->after('sst_percent');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'sst_percent', 'tax_percent']);
        });
    }
};
