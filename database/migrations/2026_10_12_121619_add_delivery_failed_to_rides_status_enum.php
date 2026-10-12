<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 review follow-up -- a parcel flagged receiver-unreachable
 * (Driver\RideController::markParcelReceiverUnreachable) needs a status of
 * its own. Before this it stayed on whatever status the rider was at when
 * they flagged it (usually 'started'), which left the ride permanently
 * "in progress" and the rider stuck unable to take new jobs until an admin
 * happened to notice and intervene. Raw ALTER, same approach the earlier
 * status/payment_status enum-widening migrations in this batch used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rides', 'status')) {
            DB::statement("ALTER TABLE rides MODIFY status ENUM('requested','accepted','en_route','arrived','started','completed','cancelled','delivery_failed') DEFAULT 'requested'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rides', 'status')) {
            DB::statement("ALTER TABLE rides MODIFY status ENUM('requested','accepted','en_route','arrived','started','completed','cancelled') DEFAULT 'requested'");
        }
    }
};
