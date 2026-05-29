<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ris_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ris_request_id')->constrained()->cascadeOnDelete();
            $table->string('stock_no')->nullable();
            $table->string('item_name');
            $table->string('unit', 50);
            $table->unsignedInteger('requested_qty');
            $table->unsignedInteger('issued_qty')->nullable();  // set by Supply when issuing
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ris_items');
    }
};
