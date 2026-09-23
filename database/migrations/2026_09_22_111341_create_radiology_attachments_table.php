<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images and report documents. Files live on a private disk and are streamed
     * through the controller, never served directly: they are identifiable.
     */
    public function up(): void
    {
        Schema::create('radiology_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radiology_order_id')->constrained()->onDelete('cascade');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('kind')->default('image');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_attachments');
    }
};
