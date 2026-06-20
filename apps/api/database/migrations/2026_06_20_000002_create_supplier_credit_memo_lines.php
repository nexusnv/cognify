<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credit_memo_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('supplier_credit_memo_id');
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->restrictOnDelete();
            $table->uuid('original_invoice_line_id')->nullable();
            $table->foreign('original_invoice_line_id')->references('id')->on('supplier_invoice_lines')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('description_snapshot');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->decimal('line_subtotal', 20, 4)->default(0);
            $table->string('tax_code', 50)->nullable();
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id', 'line_number'], 'scml_tenant_memo_line_idx');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'scml_tenant_po_line_idx');
            $table->index(['tenant_id', 'original_invoice_line_id'], 'scml_tenant_invoice_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memo_lines');
    }
};
