<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3 Part 1 -- a complaint can point at the ride or restaurant order
 * it's about. Both nullable (general complaints stay valid); nullOnDelete
 * so removing a ride/order never deletes the complaint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            if (!Schema::hasColumn('complains', 'ride_id')) {
                $table->foreignId('ride_id')->nullable()->after('service')->constrained('rides')->nullOnDelete();
            }
            if (!Schema::hasColumn('complains', 'restaurant_order_id')) {
                $table->foreignId('restaurant_order_id')->nullable()->after('ride_id')->constrained('restaurant_orders')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('complains', function (Blueprint $table) {
            if (Schema::hasColumn('complains', 'restaurant_order_id')) {
                $table->dropConstrainedForeignId('restaurant_order_id');
            }
            if (Schema::hasColumn('complains', 'ride_id')) {
                $table->dropConstrainedForeignId('ride_id');
            }
        });
    }
};
