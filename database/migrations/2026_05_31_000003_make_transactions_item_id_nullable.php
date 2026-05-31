<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Cannot safely revert if any item has been deleted via the cancel flow.
        // Guard: fail fast with a clear message rather than silently corrupt data.
        if (DB::table('transactions')->whereNull('item_id')->exists()) {
            throw new \RuntimeException(
                'Cannot roll back: transactions.item_id has NULL values from cancelled receives. ' .
                'Restore deleted items before rolling back this migration.'
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable(false)->change();
        });
    }
};
