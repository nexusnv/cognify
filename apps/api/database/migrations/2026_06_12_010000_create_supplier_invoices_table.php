<?php

use App\Models\User;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignIdFor(Vendor::class)->constrained('vendors')->cascadeOnDelete();
            $table->string('number', 100);
            $table->string('invoice_number', 100);
            $table->string('invoice_number_normalized', 100);
            $table->string('status');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3);
            $table->decimal('subtotal_amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('freight_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'captured_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'supplier_invoices_tenant_number_unique');
            $table->unique(['tenant_id', 'purchase_order_id', 'invoice_number_normalized'], 'supplier_invoices_tenant_po_number_unique');
            $table->index(['tenant_id', 'purchase_order_id', 'invoice_date'], 'supplier_invoices_tenant_po_date_idx');
            $table->index(['tenant_id', 'vendor_id', 'invoice_number_normalized'], 'supplier_invoices_tenant_vendor_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
