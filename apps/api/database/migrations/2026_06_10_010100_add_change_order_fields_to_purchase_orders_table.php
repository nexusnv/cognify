<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignUuid('current_change_order_id')->nullable()->after('acknowledgement_note')->constrained('purchase_order_change_orders')->nullOnDelete();
            $table->unsignedInteger('current_supplier_version_number')->default(1)->after('current_change_order_id');
            $table->unsignedInteger('change_order_count')->default(0)->after('current_supplier_version_number');
            $table->index(['tenant_id', 'current_change_order_id'], 'purchase_orders_tenant_current_change_order_idx');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->string('status')->default('open')->after('source_snapshot');
            $table->unsignedInteger('current_version_number')->default(1)->after('status');
            $table->foreignUuid('cancelled_by_change_order_id')->nullable()->after('current_version_number')->constrained('purchase_order_change_orders')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_change_order_id');
            $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            $table->index(['tenant_id', 'purchase_order_id', 'status'], 'purchase_order_lines_tenant_po_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_lines_tenant_po_status_idx');
            $table->dropConstrainedForeignId('cancelled_by_change_order_id');
            $table->dropColumn(['status', 'current_version_number', 'cancelled_at', 'cancelled_reason']);
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_tenant_current_change_order_idx');
            $table->dropConstrainedForeignId('current_change_order_id');
            $table->dropColumn(['current_supplier_version_number', 'change_order_count']);
        });
    }
};
