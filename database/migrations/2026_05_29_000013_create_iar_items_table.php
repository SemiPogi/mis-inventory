<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iar_record_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->string('unit', 50);
            $table->unsignedInteger('qty_delivered');
            $table->unsignedInteger('qty_accepted');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iar_items');
    }
};
