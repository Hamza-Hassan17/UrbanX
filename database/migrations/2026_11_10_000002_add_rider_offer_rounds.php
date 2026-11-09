<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Food delivery jobs go to one rider at a time, nearest first, with a
     * time limit each; after every nearby rider has skipped, a new round
     * starts (up to the configured number of rounds).
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->unsignedInteger('rider_offer_timeout_seconds')->default(60);
            $table->unsignedTinyInteger('rider_offer_rounds')->default(3);
        });
        Schema::table('rides', function (Blueprint $table) {
            $table->unsignedTinyInteger('offer_round')->default(1);
        });
        Schema::table('ride_driver_logs', function (Blueprint $table) {
            $table->unsignedTinyInteger('round')->default(1)->after('action');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_job_poll_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $t) => $t->dropColumn(['rider_offer_timeout_seconds', 'rider_offer_rounds']));
        Schema::table('rides', fn (Blueprint $t) => $t->dropColumn('offer_round'));
        Schema::table('ride_driver_logs', fn (Blueprint $t) => $t->dropColumn('round'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_job_poll_at'));
    }
};
