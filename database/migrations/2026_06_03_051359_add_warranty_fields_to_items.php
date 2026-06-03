<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->date('warranty_expiry_date')->nullable()->after('expiry_date');
            $table->string('warranty_provider', 255)->nullable()->after('warranty_expiry_date');
            $table->string('warranty_reference_no', 100)->nullable()->after('warranty_provider');
            $table->text('warranty_notes')->nullable()->after('warranty_reference_no');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'warranty_expiry_date',
                'warranty_provider',
                'warranty_reference_no',
                'warranty_notes',
            ]);
        });
    }
};
