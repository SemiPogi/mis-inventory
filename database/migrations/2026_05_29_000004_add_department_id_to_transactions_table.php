<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('id');
        });

        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('transactions')->update(['department_id' => $misId]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
