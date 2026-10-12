<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 Part 3 -- stale job alerts. subject_type/subject_id is the same
 * generic polymorphic-by-hand shape admin_activity_logs (Batch 1 Part 1)
 * already uses, kept short (100 chars) for the same key-length reason that
 * table's migration documents.
 *
 * No duplicate-open-alert DB constraint (MySQL has no partial/conditional
 * unique index) -- enforced at the application layer in
 * StaleJobAlertService::raise(), which checks for an existing open alert
 * before creating one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stale_job_alerts')) {
            return;
        }

        Schema::create('stale_job_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->enum('workspace', ['rides', 'delivery']);
            $table->string('message', 500);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'subject_type', 'subject_id']);
            $table->index(['workspace', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stale_job_alerts');
    }
};
