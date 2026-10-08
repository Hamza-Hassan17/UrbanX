<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 5 -- parcel-specific fields on rides (ride_type='delivery'
 * rows with no matching restaurant_orders row). All nullable since the
 * same rides table is shared with taxi rides and food-delivery rides,
 * neither of which use these. Goods COD (collecting item value for the
 * sender) is explicitly out of scope per the brief -- payment_method/
 * payment_status here only cover the delivery fee itself, same meaning
 * as restaurant_orders.payment_method/payment_status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('ride_type');
            }
            if (!Schema::hasColumn('rides', 'sender_phone')) {
                $table->string('sender_phone')->nullable()->after('sender_name');
            }
            if (!Schema::hasColumn('rides', 'receiver_name')) {
                $table->string('receiver_name')->nullable()->after('sender_phone');
            }
            if (!Schema::hasColumn('rides', 'receiver_phone')) {
                $table->string('receiver_phone')->nullable()->after('receiver_name');
            }
            if (!Schema::hasColumn('rides', 'package_type')) {
                $table->string('package_type')->nullable()->after('receiver_phone');
            }
            if (!Schema::hasColumn('rides', 'package_size')) {
                $table->enum('package_size', ['small', 'medium', 'large'])->nullable()->after('package_type');
            }
            if (!Schema::hasColumn('rides', 'parcel_notes')) {
                $table->string('parcel_notes', 500)->nullable()->after('package_size');
            }
            if (!Schema::hasColumn('rides', 'delivery_fee_paid_by')) {
                $table->enum('delivery_fee_paid_by', ['sender', 'receiver'])->nullable()->after('parcel_notes');
            }
            if (!Schema::hasColumn('rides', 'payment_method')) {
                $table->enum('payment_method', ['cod', 'card', 'cash', 'jazzcash', 'easypaisa'])->nullable()->after('delivery_fee_paid_by');
            }
            if (!Schema::hasColumn('rides', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'paid', 'failed'])->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name',
                'sender_phone',
                'receiver_name',
                'receiver_phone',
                'package_type',
                'package_size',
                'parcel_notes',
                'delivery_fee_paid_by',
                'payment_method',
                'payment_status',
            ]);
        });
    }
};
