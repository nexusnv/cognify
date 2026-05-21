<?php

use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalization_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalization::class, 'normalization_id')->constrained('quotation_normalizations')->cascadeOnDelete();
            $table->string('quotation_version_attachment_id')->nullable();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum_sha256')->nullable();
            $table->boolean('available')->default(true);
            $table->string('source')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('evidence_role')->nullable();
            $table->text('issue_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_attachments');
    }
};
