<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_match_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_line_id')->nullable()->constrained('supplier_invoice_lines')->nullOnDelete();
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->string('match_type');
            $table->string('match_level');
            $table->string('dimension');
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('tolerance_percent_applied', 6, 4)->nullable();
            $table->decimal('tolerance_floor_applied', 18, 4)->nullable();
            $table->decimal('tolerance_cap_applied', 18, 4)->nullable();
            $table->string('result');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_invoice_id', 'dimension']);
            $table->index(['supplier_invoice_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_match_results');
    }
};
