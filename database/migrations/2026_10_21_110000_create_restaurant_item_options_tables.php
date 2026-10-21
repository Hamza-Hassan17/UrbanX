<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 3 Part 2 -- menu add-ons/variants. Option groups belong to an item
 * (e.g. "Size", "Extra toppings"); options belong to a group. Cart and
 * order item rows get the chosen options as a JSON snapshot (names and
 * prices at the time), so later menu edits never change past orders.
 * Additive only -- items without groups behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_item_option_groups')) {
            Schema::create('restaurant_item_option_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_item_id')->constrained('restaurant_items')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_required')->default(false);
                $table->enum('selection_type', ['single', 'multiple'])->default('single');
                $table->unsignedInteger('min_select')->default(0);
                $table->unsignedInteger('max_select')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('restaurant_item_options')) {
            Schema::create('restaurant_item_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('option_group_id')->constrained('restaurant_item_option_groups')->cascadeOnDelete();
                $table->string('name');
                $table->decimal('price_adjustment', 10, 2)->default(0);
                $table->boolean('is_available')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('restaurant_cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_cart_items', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('total_price');
            }
            // Sorted option IDs joined, so the same item with the same choices
            // merges into one cart line and different choices stay separate.
            if (!Schema::hasColumn('restaurant_cart_items', 'options_signature')) {
                $table->string('options_signature')->nullable()->after('selected_options');
            }
        });

        Schema::table('restaurant_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_order_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('restaurant_item_id');
            }
            if (!Schema::hasColumn('restaurant_order_items', 'base_price')) {
                $table->decimal('base_price', 10, 2)->nullable()->after('price_per_item');
            }
            if (!Schema::hasColumn('restaurant_order_items', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('total_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            foreach (['item_name', 'base_price', 'selected_options'] as $column) {
                if (Schema::hasColumn('restaurant_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('restaurant_cart_items', function (Blueprint $table) {
            foreach (['selected_options', 'options_signature'] as $column) {
                if (Schema::hasColumn('restaurant_cart_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('restaurant_item_options');
        Schema::dropIfExists('restaurant_item_option_groups');
    }
};
