<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3 Part 4 -- online/offline for drivers and riders, separate from
 * the existing busy/available driver_status.
 *
 * users.online_status: 'online' | 'offline' | NULL. NULL means the driver
 * has never used the new toggle (an older app build) and is treated as
 * online, exactly as today. last_location_at feeds auto-offline.
 * driver_online_sessions records each online stretch for online hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'online_status')) {
                $table->string('online_status', 10)->nullable();
            }
            if (!Schema::hasColumn('users', 'online_status_changed_at')) {
                $table->timestamp('online_status_changed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable();
            }
        });

        if (!Schema::hasTable('driver_online_sessions')) {
            Schema::create('driver_online_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->string('ended_reason', 20)->nullable(); // manual | auto_offline
                $table->timestamps();
                $table->index(['driver_id', 'started_at']);
            });
        }

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'auto_offline_minutes')) {
                $table->unsignedInteger('auto_offline_minutes')->default(10);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'auto_offline_minutes')) {
                $table->dropColumn('auto_offline_minutes');
            }
        });

        Schema::dropIfExists('driver_online_sessions');

        Schema::table('users', function (Blueprint $table) {
            foreach (['online_status', 'online_status_changed_at', 'last_location_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
