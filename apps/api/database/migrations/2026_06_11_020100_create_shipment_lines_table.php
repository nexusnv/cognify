<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity_shipped', 18, 4);
            $table->decimal('quantity_delivered', 18, 4)->default(0);
            $table->decimal('backorder_quantity', 18, 4)->default(0);
            $table->date('backorder_expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'shipment_id', 'purchase_order_line_id'], 'shipment_lines_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'shipment_lines_tenant_po_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
    }
};
