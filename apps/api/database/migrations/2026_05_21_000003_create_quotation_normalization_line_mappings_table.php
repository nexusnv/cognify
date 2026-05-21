<?php

use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalization_line_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalizationLineGroup::class, 'quotation_normalization_line_group_id')->constrained('quotation_normalization_line_groups')->cascadeOnDelete();
            $table->string('rfq_line_item_id')->nullable();
            $table->foreignIdFor(QuotationVersionLineItem::class)->nullable()->constrained()->nullOnDelete();
            $table->string('mapping_type', 32);
            $table->decimal('quantity', 14, 4)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2)->nullable();
            $table->text('buyer_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'quotation_normalization_line_group_id'], 'quotation_normalization_line_mappings_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_line_mappings');
    }
};
