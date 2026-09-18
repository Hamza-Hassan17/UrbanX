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
        Schema::create('vehicle_type_icons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });

        // Backfill the icons that were previously hardcoded in the
        // create/edit blade views, so existing vehicle types' icon choices
        // don't disappear from the picker once it switches to reading from
        // this table instead.
        $existingIcons = [
            'icons/ev-car.svg' => 'EV Car',
            'icons/limousine.svg' => 'Limousine',
            'icons/luxury-car.svg' => 'Luxury Car',
            'icons/motorcycle.svg' => 'Motorcycle',
            'icons/taxi-4.svg' => 'Taxi 4',
            'icons/taxi-7.svg' => 'Taxi 7',
        ];

        foreach ($existingIcons as $path => $name) {
            \App\Models\VehicleTypeIcon::firstOrCreate(['path' => $path], ['name' => $name]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_icons');
    }
};
