<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each row is a published version of the Terms & Conditions. Publishing a
 * new version is an INSERT, never an UPDATE to an old row -- that's what
 * makes "re-prompt everyone when terms change" work for free: a user's
 * accepted version_id just stops matching the current (latest) row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_and_conditions', function (Blueprint $table) {
            $table->id();
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_and_conditions');
    }
};
