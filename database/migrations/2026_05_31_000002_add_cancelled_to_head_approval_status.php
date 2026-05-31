<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enforces enum values; extend to include 'cancelled'.
        // SQLite has no enum constraints — any string is accepted without change.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected','cancelled') NULL"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected') NULL"
            );
        }
    }
};
