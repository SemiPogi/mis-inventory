<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 25)->unique();
            $table->foreignId('from_dept_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('to_dept_id')->constrained('departments')->cascadeOnDelete();
            $table->enum('status', ['pending_head', 'approved', 'rejected', 'completed'])->default('pending_head');
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('head_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('head_approved_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_transfers');
    }
};
