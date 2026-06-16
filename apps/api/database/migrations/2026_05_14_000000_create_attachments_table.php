<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('checksum_sha256', 64);
            $table->boolean('previewable')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'attachable_type', 'attachable_id']);
            $table->unique(['storage_disk', 'storage_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
