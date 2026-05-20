<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
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
            $table->string('compliance_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'quotation_id'], 'quotation_line_items_tenant_quotation_index');
            $table->index(['tenant_id', 'rfq_line_item_id'], 'quotation_line_items_tenant_rfq_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_line_items');
    }
};
