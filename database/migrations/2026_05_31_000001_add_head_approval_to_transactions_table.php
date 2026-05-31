<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('head_approval_status', ['pending', 'approved', 'rejected', 'cancelled'])
                  ->nullable()
                  ->after('department_id');

            $table->foreignId('head_approved_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->after('head_approval_status');

            $table->timestamp('head_approved_at')
                  ->nullable()
                  ->after('head_approved_by_id');

            $table->text('head_rejection_notes')
                  ->nullable()
                  ->after('head_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['head_approved_by_id']);
            $table->dropColumn([
                'head_approval_status',
                'head_approved_by_id',
                'head_approved_at',
                'head_rejection_notes',
            ]);
        });
    }
};
