<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal offers: a promo code with user_id set can only be used by that
 * customer (Customer Insights -> Give offer). Null = anyone, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('promo_codes', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('service')->index();
            }
            if (!Schema::hasColumn('promo_codes', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            foreach (['created_by', 'user_id'] as $col) {
                if (Schema::hasColumn('promo_codes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
