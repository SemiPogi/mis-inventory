<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assembly_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('item_name_snapshot');
            $table->string('unit', 50);
            $table->unsignedInteger('qty_used');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_components');
    }
};
