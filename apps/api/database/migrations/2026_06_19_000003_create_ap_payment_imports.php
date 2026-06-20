<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_imports', function (Blueprint $table): void {
            // NOTE: Cognify's HasUuids convention stores primary keys as native PG `uuid` (not
            // char(36)). The parent tables ap_payment_handoffs.id and supplier_invoices.id are
            // both uuid, so the FK columns must match.
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('batch_id');
            $table->integer('row_index');
            $table->string('handoff_number', 50)->nullable();
            $table->string('invoice_number', 255)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->decimal('allocated_amount', 20, 4)->nullable();
            $table->boolean('mark_full')->default(false);
            $table->decimal('settlement_amount', 20, 4)->nullable();
            $table->string('settlement_currency', 3)->nullable();
            $table->date('paid_at')->nullable();
            $table->string('settlement_method', 50)->nullable();
            $table->string('target_status', 50);
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('void_reason')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('match_error')->nullable();
            $table->uuid('matched_handoff_id')->nullable();
            $table->foreign('matched_handoff_id')->references('id')->on('ap_payment_handoffs')->nullOnDelete();
            $table->uuid('matched_invoice_id')->nullable();
            $table->foreign('matched_invoice_id')->references('id')->on('supplier_invoices')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imported_by_user_id')->constrained('users');
            $table->timestamp('imported_at');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'batch_id', 'row_index'], 'ap_imp_tenant_batch_row_idx');
            $table->index(['tenant_id', 'status'], 'ap_imp_tenant_status_idx');
            $table->index(['tenant_id', 'matched_handoff_id'], 'ap_imp_tenant_handoff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_imports');
    }
};
