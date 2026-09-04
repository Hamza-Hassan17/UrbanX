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
            // Which admin/operator manually created this ride from the dashboard
            // (Custom Rides "New Ride" panel). Null for rides customers requested
            // themselves through the app -- only admin-created rides have an owner here.
            $table->foreignId('created_by')->nullable()->after('status_updated_by_role')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
