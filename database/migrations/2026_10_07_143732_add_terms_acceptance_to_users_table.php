<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('terms_accepted_version_id')->nullable()->after('phone_verified_at')
                ->constrained('terms_and_conditions')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['terms_accepted_version_id']);
            $table->dropColumn(['terms_accepted_version_id', 'terms_accepted_at']);
        });
    }
};
