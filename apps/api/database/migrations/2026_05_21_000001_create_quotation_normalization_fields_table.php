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
        Schema::create('quotation_normalization_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalization::class, 'normalization_id')->constrained('quotation_normalizations')->cascadeOnDelete();
            $table->string('field_path');
            $table->json('raw_value')->nullable();
            $table->json('normalized_value')->nullable();
            $table->string('data_type')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('source')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'normalization_id'], 'quotation_normalization_fields_normalization_index');
            $table->index(['tenant_id', 'field_path'], 'quotation_normalization_fields_path_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_fields');
    }
};
