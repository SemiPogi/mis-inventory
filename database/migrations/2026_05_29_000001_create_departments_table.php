<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();            // e.g. MIS, NURS, PHARM
            $table->string('responsibility_center_code')->nullable();
            $table->boolean('is_supply_hub')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default MIS department so existing data can be assigned to it
        DB::table('departments')->insert([
            'name'       => 'MIS Office',
            'code'       => 'MIS',
            'is_supply_hub' => false,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
