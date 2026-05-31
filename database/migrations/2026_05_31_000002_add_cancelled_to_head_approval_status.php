<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected','cancelled') NULL"
            );
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN. Must recreate the column.
            // This approach creates a temporary column, copies data, drops the old one, and renames.
            DB::statement('ALTER TABLE transactions RENAME COLUMN head_approval_status TO head_approval_status_old');

            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('head_approval_status', ['pending', 'approved', 'rejected', 'cancelled'])
                      ->nullable();
            });

            DB::statement('UPDATE transactions SET head_approval_status = head_approval_status_old');
            DB::statement('ALTER TABLE transactions DROP COLUMN head_approval_status_old');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected') NULL"
            );
        } elseif ($driver === 'sqlite') {
            DB::statement('ALTER TABLE transactions RENAME COLUMN head_approval_status TO head_approval_status_old');

            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('head_approval_status', ['pending', 'approved', 'rejected'])
                      ->nullable();
            });

            DB::statement('UPDATE transactions SET head_approval_status = head_approval_status_old WHERE head_approval_status != "cancelled"');
            DB::statement('ALTER TABLE transactions DROP COLUMN head_approval_status_old');
        }
    }
};
