<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Two orders per rider from the same restaurant (off by default). */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('batching_enabled')->default(false);
            $table->unsignedTinyInteger('batch_max_orders')->default(2);
            $table->decimal('batch_customer_radius_km', 5, 2)->default(2);
            $table->unsignedInteger('batch_max_delay_minutes')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $t) => $t->dropColumn(['batching_enabled', 'batch_max_orders', 'batch_customer_radius_km', 'batch_max_delay_minutes']));
    }
};
