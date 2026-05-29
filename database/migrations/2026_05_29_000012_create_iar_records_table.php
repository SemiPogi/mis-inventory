<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iar_records', function (Blueprint $table) {
            $table->id();
            $table->string('iar_number', 25)->unique();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete(); // supply hub dept
            $table->string('supplier');
            $table->string('purchase_order_no')->nullable();
            $table->date('date_of_delivery')->nullable();
            $table->date('date_of_inspection')->nullable();
            $table->enum('status', ['draft', 'accepted', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iar_records');
    }
};
