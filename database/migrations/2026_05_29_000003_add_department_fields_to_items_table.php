<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: items table already has 'category' column — only adding department_id, expiry_date, min_stock_qty
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('id');
            $table->date('expiry_date')->nullable()->after('category');
            $table->unsignedInteger('min_stock_qty')->default(0)->after('expiry_date');
        });

        // Assign all existing items to MIS department
        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('items')->update(['department_id' => $misId]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'expiry_date', 'min_stock_qty']);
        });
    }
};
