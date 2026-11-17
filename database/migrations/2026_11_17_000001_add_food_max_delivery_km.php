<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far from the restaurant food can be delivered (separate from the
     * rider pickup radius). 0 = no limit.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedInteger('food_max_delivery_km')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $t) => $t->dropColumn('food_max_delivery_km'));
    }
};
