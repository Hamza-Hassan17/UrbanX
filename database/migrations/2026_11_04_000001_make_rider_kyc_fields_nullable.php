<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delivery riders' shorter KYC doesn't ask for the CNIC issue date or
     * the licence address. Taxi drivers still must send both (validated in
     * DriverDetailsController). Existing data is untouched.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE driver_cnics MODIFY issue_date DATE NULL');
        DB::statement('ALTER TABLE driver_licenses MODIFY address VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Not reverted to NOT NULL: rider rows may legitimately hold NULLs by then.
    }
};
