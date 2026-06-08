<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_request_handoff_id')
                ->constrained('purchase_order_request_handoffs')
                ->restrictOnDelete();
            $table->foreignUuid('rfq_award_recommendation_id')
                ->constrained('rfq_award_recommendations')
                ->restrictOnDelete();
            $table->foreignIdFor(ApprovalInstance::class, 'approval_instance_id')
                ->nullable()
                ->constrained('approval_instances')
                ->nullOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->cascadeOnDelete();
            $table->foreignIdFor(Requisition::class)->nullable()->constrained('requisitions')->nullOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('procurement_projects')
                ->nullOnDelete();
            $table->foreignIdFor(Vendor::class)->constrained('vendors')->restrictOnDelete();
            $table->foreignIdFor(Quotation::class)->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignIdFor(QuotationVersion::class, 'quotation_version_id')
                ->nullable()
                ->constrained('quotation_versions')
                ->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->string('currency', 3);
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->date('requested_po_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('billing_name')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('shipping_name')->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('delivery_attention')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->text('buyer_note')->nullable();
            $table->text('finance_note')->nullable();
            $table->json('source_snapshot');
            $table->json('approval_snapshot');
            $table->json('evidence_snapshot');
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'ready_for_review_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_for_review_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'purchase_order_request_handoff_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'updated_at']);
            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['tenant_id', 'rfq_id']);
            $table->index(['tenant_id', 'requisition_id']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->uuid('purchase_order_id');
            $table->string('source_line_id')->nullable();
            $table->unsignedInteger('line_number');
            $table->text('description');
            $table->string('category')->nullable();
            $table->string('sku')->nullable();
            $table->string('unit');
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('subtotal_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3);
            $table->date('needed_by_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('notes')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->index(['tenant_id', 'purchase_order_id']);
            $table->unique(['purchase_order_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
