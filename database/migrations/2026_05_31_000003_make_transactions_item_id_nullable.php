<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow transactions.item_id to be NULL so that item records can be
     * deleted (e.g. when cancelling a premature receive) without losing
     * the transaction history. The item_name_snapshot column preserves
     * the item name for display purposes.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable(false)->change();
        });
    }
};
