<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description_snapshot')->nullable();
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_invoiced', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_subtotal', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_invoice_id', 'purchase_order_line_id'], 'supplier_invoice_line_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'supplier_invoice_lines_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
    }
};
