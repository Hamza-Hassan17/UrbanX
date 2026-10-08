<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic admin change-log -- no equivalent existed anywhere in the app
 * before this (confirmed: no spatie/laravel-activitylog, no bespoke audit
 * table). Built for Batch 1 Part 1 (Pricing & Fees), but deliberately
 * generic (subject_type/subject_id + old/new value JSON) so later parts
 * needing an audit trail (e.g. order cancellations, cash settlements) can
 * reuse it instead of growing their own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
