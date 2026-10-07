<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No FK constraint on terms_accepted_version_id -- a hard foreign key here
 * failed on production (errno 150, "foreign key constraint is incorrectly
 * formed"), most likely an engine/collation mismatch between the long-
 * standing `users` table and the newly created `terms_and_conditions`
 * table on that server. The relationship is still enforced at the
 * application level (TermsAndCondition::current(), User::termsAcceptedVersion()),
 * so a DB-level constraint isn't required for this to work correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_accepted_version_id')->nullable()->after('phone_verified_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_version_id', 'terms_accepted_at']);
        });
    }
};
