<?php

use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_version_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationVersion::class)->constrained()->cascadeOnDelete();
            $table->string('rfq_line_item_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 14, 4);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->boolean('alternate_offered')->default(false);
            $table->string('compliance_status', 32)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'quotation_version_id'], 'quotation_version_line_items_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_version_line_items');
    }
};
