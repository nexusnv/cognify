<?php

use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('number');
            $table->string('status');
            $table->date('receipt_date');
            $table->string('receipt_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignIdFor(User::class, 'requester_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requester_confirmed_at')->nullable();
            $table->foreignIdFor(User::class, 'buyer_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_order_id'], 'goods_receipts_tenant_po_idx');
            $table->index(['tenant_id', 'status', 'recorded_at'], 'goods_receipts_tenant_status_recorded_idx');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_accepted', 18, 4);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'goods_receipt_id', 'purchase_order_line_id'], 'goods_receipt_line_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'goods_receipt_lines_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
