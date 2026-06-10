<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignIdFor(User::class, 'issued_by_user_id')->nullable()->after('changes_requested_fields')->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable()->after('issued_by_user_id');
            $table->string('issue_method')->nullable()->after('issued_at');
            $table->string('supplier_contact_name')->nullable()->after('issue_method');
            $table->string('supplier_contact_email')->nullable()->after('supplier_contact_name');
            $table->text('issue_message')->nullable()->after('supplier_contact_email');
            $table->json('supplier_version')->nullable()->after('issue_message');
            $table->unsignedInteger('supplier_version_number')->default(0)->after('supplier_version');
            $table->foreignIdFor(User::class, 'last_supplier_exported_by_user_id')->nullable()->after('supplier_version_number')->constrained('users')->nullOnDelete();
            $table->timestamp('last_supplier_exported_at')->nullable()->after('last_supplier_exported_by_user_id');
            $table->string('last_supplier_export_format')->nullable()->after('last_supplier_exported_at');
            $table->foreignIdFor(User::class, 'acknowledged_by_user_id')->nullable()->after('last_supplier_export_format')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
            $table->string('acknowledged_contact_name')->nullable()->after('acknowledged_at');
            $table->string('acknowledgement_reference')->nullable()->after('acknowledged_contact_name');
            $table->text('acknowledgement_note')->nullable()->after('acknowledgement_reference');

            $table->index(['tenant_id', 'status', 'issued_at'], 'purchase_orders_tenant_status_issued_idx');
            $table->index(['tenant_id', 'vendor_id', 'issued_at'], 'purchase_orders_tenant_vendor_issued_idx');
            $table->index(['tenant_id', 'acknowledged_at'], 'purchase_orders_tenant_acknowledged_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_tenant_status_issued_idx');
            $table->dropIndex('purchase_orders_tenant_vendor_issued_idx');
            $table->dropIndex('purchase_orders_tenant_acknowledged_idx');
            $table->dropConstrainedForeignId('issued_by_user_id');
            $table->dropColumn(['issued_at', 'issue_method', 'supplier_contact_name', 'supplier_contact_email', 'issue_message', 'supplier_version', 'supplier_version_number']);
            $table->dropConstrainedForeignId('last_supplier_exported_by_user_id');
            $table->dropColumn(['last_supplier_exported_at', 'last_supplier_export_format']);
            $table->dropConstrainedForeignId('acknowledged_by_user_id');
            $table->dropColumn(['acknowledged_at', 'acknowledged_contact_name', 'acknowledgement_reference', 'acknowledgement_note']);
        });
    }
};
