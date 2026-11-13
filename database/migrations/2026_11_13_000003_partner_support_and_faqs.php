<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support requests from any app (driver, rider, restaurant), optionally about one order.
        Schema::table('support_requests', function (Blueprint $table) {
            $table->string('source', 20)->default('driver')->after('driver_id');
            $table->foreignId('order_id')->nullable()->after('subject')->constrained('restaurant_orders')->nullOnDelete();
        });
        DB::statement("ALTER TABLE support_messages MODIFY sender_role ENUM('driver','rider','restaurant','admin') NOT NULL");

        // Help screen FAQs, managed from the admin panel, per app.
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->enum('audience', ['all', 'customer', 'restaurant', 'rider', 'driver'])->default('all');
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        DB::statement("ALTER TABLE support_messages MODIFY sender_role ENUM('driver','admin') NOT NULL");
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('source');
        });
    }
};
