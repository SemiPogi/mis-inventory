<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('assembly_number', 25)->unique();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('output_item_name');       // name of the newly assembled item
            $table->string('output_unit', 50)->default('unit');
            $table->unsignedInteger('qty_produced')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('assembled_by_id')->constrained('users');
            $table->timestamp('assembled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assemblies');
    }
};
