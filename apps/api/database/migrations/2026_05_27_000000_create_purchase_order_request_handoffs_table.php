<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_request_handoffs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('rfq_award_recommendation_id')
                ->constrained('rfq_award_recommendations')
                ->cascadeOnDelete();
            $table->foreignIdFor(ApprovalInstance::class, 'approval_instance_id')
                ->nullable()
                ->constrained('approval_instances')
                ->nullOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->restrictOnDelete();
            $table->foreignIdFor(Requisition::class)->nullable()->constrained('requisitions')->nullOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('procurement_projects')
                ->nullOnDelete();
            $table->foreignIdFor(Vendor::class)->constrained('vendors')->restrictOnDelete();
            $table->foreignIdFor(Quotation::class)->constrained('quotations')->restrictOnDelete();
            $table->foreignIdFor(QuotationVersion::class)->constrained('quotation_versions')->restrictOnDelete();
            $table->string('number');
            $table->string('status');
            $table->string('currency', 3)->nullable();
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->date('requested_po_date')->nullable();
            $table->string('delivery_attention')->nullable();
            $table->text('finance_note')->nullable();
            $table->text('export_memo')->nullable();
            $table->foreignIdFor(User::class, 'requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'ready_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->foreignIdFor(User::class, 'last_exported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_exported_at')->nullable();
            $table->string('last_export_format')->nullable();
            $table->json('source_snapshot');
            $table->json('line_snapshot');
            $table->json('approval_snapshot');
            $table->json('evidence_snapshot');
            $table->json('readiness_warnings')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'rfq_award_recommendation_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'updated_at']);
            $table->index(['tenant_id', 'rfq_id']);
            $table->index(['tenant_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_request_handoffs');
    }
};
