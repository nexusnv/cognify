<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credit_memos', function (Blueprint $table): void {
            // Drift from design spec: id uses uuid() (not char(36)) to match the
            // P1-48 ApPaymentHandoff / SupplierInvoice HasUuids convention used
            // for the invoice/PO lineage. FK columns follow each parent table's
            // actual ID type: vendors/users/approval_instances are bigint, while
            // supplier_invoices is uuid. Storing them mixed (uuid for memo id,
            // bigint for user/vendor) is consistent with the SupplierInvoice model.
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 50);
            $table->string('vendor_credit_memo_number', 255)->nullable();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->uuid('original_invoice_id')->nullable();
            $table->foreign('original_invoice_id')->references('id')->on('supplier_invoices')->restrictOnDelete();
            $table->string('status', 50)->default('draft');
            $table->string('currency', 3);
            $table->decimal('subtotal_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('freight_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->date('credit_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('approval_instance_id')->nullable();
            $table->foreign('approval_instance_id')->references('id')->on('approval_instances')->nullOnDelete();
            $table->boolean('stp_eligible')->default(false);
            $table->timestamp('stp_processed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'scm_tenant_number_unique');
            $table->index(['tenant_id', 'vendor_id', 'status'], 'scm_tenant_vendor_status_idx');
            $table->index(['tenant_id', 'original_invoice_id'], 'scm_tenant_invoice_idx');
            $table->index(['tenant_id', 'status', 'posted_at'], 'scm_tenant_status_posted_idx');
            $table->index(['tenant_id', 'vendor_credit_memo_number'], 'scm_tenant_vendor_cm_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memos');
    }
};
