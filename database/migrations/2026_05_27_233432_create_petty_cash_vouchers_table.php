<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->string('or_number');
            $table->string('store_name');
            $table->string('releasing_officer');
            $table->decimal('requested_amount', 8, 2);
            $table->decimal('transport_fee', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2);
            $table->decimal('change_amount', 8, 2);
            $table->date('date_purchased');
            $table->enum('status', ['submitted', 'acknowledged', 'settled'])->default('submitted');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('change_returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('change_returned_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_vouchers');
    }
};
