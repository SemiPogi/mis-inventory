<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ris_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ris_number', 20)->unique();   // RIS-YYYY-NNNN
            $table->foreignId('requesting_dept_id')->constrained('departments')->cascadeOnDelete();
            $table->enum('status', ['draft', 'pending_head', 'pending_supply', 'issued', 'completed', 'rejected'])
                  ->default('draft');
            $table->text('purpose');
            $table->foreignId('requested_by_id')->constrained('users');
            $table->foreignId('head_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('head_approved_at')->nullable();
            $table->foreignId('supply_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supply_approved_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ris_requests');
    }
};
