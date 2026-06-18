<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_handoff_invoice', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('ap_payment_handoff_id')->constrained('ap_payment_handoffs')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['ap_payment_handoff_id', 'supplier_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_handoff_invoice');
    }
};
