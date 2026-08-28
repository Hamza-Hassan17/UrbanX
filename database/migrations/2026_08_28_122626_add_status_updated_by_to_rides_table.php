<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // Who last changed the ride status (e.g. cancelled it) — the passenger/driver
            // themselves via the app, or an admin via the dashboard. Both are `users` rows.
            $table->foreignId('status_updated_by')->nullable()->after('cancel_reason')
                ->constrained('users')->nullOnDelete();
            $table->string('status_updated_by_role')->nullable()->after('status_updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_updated_by');
            $table->dropColumn('status_updated_by_role');
        });
    }
};
