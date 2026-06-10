<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_change_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->string('change_type');
            $table->string('from_purchase_order_status');
            $table->string('to_purchase_order_status')->nullable();
            $table->text('reason');
            $table->boolean('material_change')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->foreignIdFor(User::class, 'requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable();
            $table->text('changes_requested_reason')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->json('delta_snapshot');
            $table->unsignedInteger('supplier_version_number')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_order_id', 'status'], 'po_change_orders_tenant_po_status_idx');
            $table->index(['tenant_id', 'status', 'updated_at'], 'po_change_orders_tenant_status_updated_idx');
        });

        Schema::create('purchase_order_change_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_change_order_id')->constrained('purchase_order_change_orders')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('change_action');
            $table->decimal('quantity_before', 18, 4)->nullable();
            $table->decimal('quantity_after', 18, 4)->nullable();
            $table->decimal('unit_price_before', 18, 4)->nullable();
            $table->decimal('unit_price_after', 18, 4)->nullable();
            $table->decimal('subtotal_amount_before', 18, 2)->nullable();
            $table->decimal('subtotal_amount_after', 18, 2)->nullable();
            $table->decimal('tax_amount_before', 18, 2)->nullable();
            $table->decimal('tax_amount_after', 18, 2)->nullable();
            $table->decimal('freight_amount_before', 18, 2)->nullable();
            $table->decimal('freight_amount_after', 18, 2)->nullable();
            $table->decimal('discount_amount_before', 18, 2)->nullable();
            $table->decimal('discount_amount_after', 18, 2)->nullable();
            $table->decimal('total_amount_before', 18, 2)->nullable();
            $table->decimal('total_amount_after', 18, 2)->nullable();
            $table->date('expected_delivery_date_before')->nullable();
            $table->date('expected_delivery_date_after')->nullable();
            $table->string('delivery_location_before')->nullable();
            $table->string('delivery_location_after')->nullable();
            $table->text('notes_before')->nullable();
            $table->text('notes_after')->nullable();
            $table->json('delta_snapshot');
            $table->timestamps();

            $table->unique(['purchase_order_change_order_id', 'purchase_order_line_id'], 'po_change_order_line_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'po_change_order_lines_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_change_order_lines');
        Schema::dropIfExists('purchase_order_change_orders');
    }
};
