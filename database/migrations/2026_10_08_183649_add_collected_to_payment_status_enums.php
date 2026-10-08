<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 8 -- 'collected' means the rider has confirmed taking the
 * COD cash (separate from 'paid', which already meant "non-cash payment
 * succeeded"). Raw ALTER since Laravel's schema builder has no native
 * "add enum value" operation -- same approach the two earlier
 * restaurant_orders.status enum-widening migrations already used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('restaurant_orders', 'payment_status')) {
            DB::statement("ALTER TABLE restaurant_orders MODIFY payment_status ENUM('unpaid','paid','failed','collected') DEFAULT 'unpaid'");
        }
        if (Schema::hasColumn('rides', 'payment_status')) {
            DB::statement("ALTER TABLE rides MODIFY payment_status ENUM('unpaid','paid','failed','collected') DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_orders', 'payment_status')) {
            DB::statement("ALTER TABLE restaurant_orders MODIFY payment_status ENUM('unpaid','paid','failed') DEFAULT 'unpaid'");
        }
        if (Schema::hasColumn('rides', 'payment_status')) {
            DB::statement("ALTER TABLE rides MODIFY payment_status ENUM('unpaid','paid','failed') DEFAULT NULL");
        }
    }
};
