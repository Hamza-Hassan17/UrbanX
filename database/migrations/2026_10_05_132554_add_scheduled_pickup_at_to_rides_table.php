<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->timestamp('scheduled_pickup_at')->nullable()->after('requested_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['scheduled_pickup_at']);
            $table->dropColumn('scheduled_pickup_at');
        });
    }
};
