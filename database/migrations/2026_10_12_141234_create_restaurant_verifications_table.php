<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Part 4 -- restaurant registration & approval. Mirrors
 * driver_verifications' status/rejection/review lifecycle (overwritten
 * each submit/review cycle rather than kept as separate history rows --
 * same "no separate audit-log table" precedent that table's own
 * controller comment documents; AdminActivityLog covers the audit trail
 * instead, same as everywhere else in this app). Unlike the driver side
 * (separate driver_cnics/driver_licenses tables), every document lives on
 * this one row -- fewer, more varied document types, no reason to split.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_verifications')) {
            return;
        }

        Schema::create('restaurant_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained('restaurants')->cascadeOnDelete();
            $table->string('owner_cnic_front')->nullable();
            $table->string('owner_cnic_back')->nullable();
            $table->string('ntn_number')->nullable();
            $table->string('food_authority_license')->nullable();
            $table->string('bank_account_title')->nullable();
            $table->string('bank_iban')->nullable();
            $table->string('logo')->nullable();
            $table->string('storefront_photo')->nullable();
            $table->enum('status', ['not_submitted', 'submitted', 'approved', 'rejected'])->default('not_submitted');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_verifications');
    }
};
