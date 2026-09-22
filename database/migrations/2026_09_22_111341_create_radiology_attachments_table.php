<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images and report documents attached to an imaging request. Files are kept
     * on a private disk and streamed through the controller, never served
     * directly, because they are patient-identifiable.
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
            $table->string('kind')->default('image');  // image, report
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_attachments');
    }
};
