<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 Part 7 -- same proof-of-delivery concept as restaurant_orders,
 * for parcel rides (ride_type='delivery' with no matching restaurant
 * order). receiver_unreachable_photo/flagged_for_review are parcel-only:
 * a rider may upload a photo instead of the code ONLY when marking
 * "receiver unreachable", which flags the job for admin review rather
 * than completing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            if (!Schema::hasColumn('rides', 'delivery_code')) {
                $table->string('delivery_code', 4)->nullable()->after('ride_type');
            }
            if (!Schema::hasColumn('rides', 'delivery_code_attempts')) {
                $table->unsignedTinyInteger('delivery_code_attempts')->default(0)->after('delivery_code');
            }
            if (!Schema::hasColumn('rides', 'delivery_code_locked')) {
                $table->boolean('delivery_code_locked')->default(false)->after('delivery_code_attempts');
            }
            if (!Schema::hasColumn('rides', 'delivery_override_by')) {
                $table->foreignId('delivery_override_by')->nullable()->after('delivery_code_locked')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('rides', 'delivery_override_reason')) {
                $table->string('delivery_override_reason', 500)->nullable()->after('delivery_override_by');
            }
            if (!Schema::hasColumn('rides', 'delivery_override_at')) {
                $table->timestamp('delivery_override_at')->nullable()->after('delivery_override_reason');
            }
            if (!Schema::hasColumn('rides', 'receiver_unreachable_photo')) {
                $table->string('receiver_unreachable_photo')->nullable()->after('delivery_override_at');
            }
            if (!Schema::hasColumn('rides', 'flagged_for_review')) {
                $table->boolean('flagged_for_review')->default(false)->after('receiver_unreachable_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_code',
                'delivery_code_attempts',
                'delivery_code_locked',
                'delivery_override_by',
                'delivery_override_reason',
                'delivery_override_at',
                'receiver_unreachable_photo',
                'flagged_for_review',
            ]);
        });
    }
};
