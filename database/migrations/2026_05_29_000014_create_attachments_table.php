<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');     // attachable_type + attachable_id
            $table->string('filename');        // stored filename (hashed)
            $table->string('original_name');   // original upload name
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
