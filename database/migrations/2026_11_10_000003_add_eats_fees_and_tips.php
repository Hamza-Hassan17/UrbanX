<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->decimal('eats_platform_fee', 8, 2)->default(0);
            $table->decimal('small_order_threshold', 8, 2)->default(0);
            $table->decimal('small_order_fee', 8, 2)->default(0);
        });
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->decimal('small_order_fee', 8, 2)->default(0)->after('platform_fee');
            $table->decimal('tip_amount', 8, 2)->default(0)->after('small_order_fee');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $t) => $t->dropColumn(['eats_platform_fee', 'small_order_threshold', 'small_order_fee']));
        Schema::table('restaurant_orders', fn (Blueprint $t) => $t->dropColumn(['small_order_fee', 'tip_amount']));
    }
};
