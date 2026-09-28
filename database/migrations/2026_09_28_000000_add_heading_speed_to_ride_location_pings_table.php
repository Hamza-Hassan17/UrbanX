<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_location_pings', function (Blueprint $table) {
            $table->decimal('heading', 5, 1)->nullable()->after('longitude');
            $table->decimal('speed_kmh', 5, 1)->nullable()->after('heading');
        });
    }

    public function down(): void
    {
        Schema::table('ride_location_pings', function (Blueprint $table) {
            $table->dropColumn(['heading', 'speed_kmh']);
        });
    }
};
