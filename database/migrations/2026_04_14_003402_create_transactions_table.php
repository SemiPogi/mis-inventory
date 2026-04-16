<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['received', 'released']);
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_name_snapshot');
            $table->integer('qty');
            $table->string('unit')->default('pcs');
            $table->string('received_from')->nullable();
            $table->string('ris_iar_number')->nullable();
            $table->date('date_received')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users');
            $table->string('released_to_office')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_designation')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users');
            $table->string('purpose')->nullable();
            $table->date('date_released')->nullable();
            $table->enum('acknowledgment_status', ['pending', 'acknowledged'])->default('pending');
            $table->string('acknowledged_by_name')->nullable();
            $table->date('acknowledged_date')->nullable();
            $table->text('acknowledgment_remarks')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};