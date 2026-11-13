<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ratings are tied to a delivered order: one restaurant rating and one
     * rider rating per order. Older reviews keep order_id NULL.
     */
    public function up(): void
    {
        Schema::table('restaurant_reviews', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('customer_id')->constrained('restaurant_orders')->nullOnDelete();
            $table->unique('order_id');
        });
        Schema::table('driver_reviews', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('review_by')->constrained('restaurant_orders')->nullOnDelete();
            $table->unique('order_id');
        });
        Schema::table('restaurant_orders', function (Blueprint $table) {
            // Customer closed the rating screen without rating: stop reminding.
            $table->timestamp('rating_skipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_reviews', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropConstrainedForeignId('order_id');
        });
        Schema::table('driver_reviews', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropConstrainedForeignId('order_id');
        });
        Schema::table('restaurant_orders', fn (Blueprint $t) => $t->dropColumn('rating_skipped_at'));
    }
};
