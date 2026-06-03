<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('action', [
                'received',
                'released',
                'approved_receive',
                'approved_release',
                'rejected',
                'cancelled',
            ]);
            $table->integer('qty_change');
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — logs are immutable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_logs');
    }
};
