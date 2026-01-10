<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boost_hours', function (Blueprint $table) {
            $table->id();
            $table->time('start');       // start time of busy slot
            $table->time('end');         // end time of busy slot
            $table->decimal('multiplier', 3, 1)->default(1.0);
            $table->timestamps();
        });

        // Optional: Insert default boost hours
        DB::table('boost_hours')->insert([
            ['start' => '07:00', 'end' => '09:00', 'multiplier' => 1.2, 'created_at' => now(), 'updated_at' => now()],
            ['start' => '18:00', 'end' => '20:00', 'multiplier' => 1.4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boost_hours');
    }
};
