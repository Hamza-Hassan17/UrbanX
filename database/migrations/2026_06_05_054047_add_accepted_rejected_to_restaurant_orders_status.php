<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE restaurant_orders MODIFY COLUMN status ENUM('pending','accepted','rejected','preparing','rider_assigned','picked_up','on_the_way','delivered','completed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE restaurant_orders MODIFY COLUMN status ENUM('pending','preparing','delivered','completed','cancelled') DEFAULT 'pending'");
    }
};
