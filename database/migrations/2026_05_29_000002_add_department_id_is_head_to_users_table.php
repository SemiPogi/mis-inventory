<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('is_active');
            $table->boolean('is_head')->default(false)->after('department_id');
        });

        // Assign all existing staff users to MIS department
        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('users')->where('role', 'staff')->update(['department_id' => $misId]);
        // admin and accounting stay null (hospital-wide access)
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'is_head']);
        });
    }
};
