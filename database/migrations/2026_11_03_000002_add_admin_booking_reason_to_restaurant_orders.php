<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why an admin booked this food order by hand (Manual Delivery
     * Assignment). Null on orders placed from the app. Additive only.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('restaurant_orders', 'admin_booking_reason')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->text('admin_booking_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_orders', 'admin_booking_reason')) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropColumn('admin_booking_reason');
            });
        }
    }
};
