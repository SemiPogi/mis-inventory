<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // MySQL: raw DDL is required to modify an existing enum column.
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected','cancelled') NULL"
            );
        } else {
            // SQLite (and others): Laravel's change() rewrites the CHECK constraint.
            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('head_approval_status', ['pending', 'approved', 'rejected', 'cancelled'])
                      ->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE transactions MODIFY COLUMN head_approval_status
                 ENUM('pending','approved','rejected') NULL"
            );
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->enum('head_approval_status', ['pending', 'approved', 'rejected'])
                      ->nullable()->change();
            });
        }
    }
};
