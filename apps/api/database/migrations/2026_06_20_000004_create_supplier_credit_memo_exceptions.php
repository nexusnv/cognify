<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credit_memo_exceptions', function (Blueprint $table): void {
            // Drift from design spec: resolved_by_user_id, acknowledged_by_user_id,
            // escalated_by_user_id are foreignId() (bigint) to match users.id.
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('supplier_credit_memo_id');
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->string('exception_type', 100);
            $table->string('severity', 50)->default('warning');
            $table->text('description');
            $table->string('resolution_type', 50)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('escalated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->decimal('expected_value', 20, 4)->nullable();
            $table->decimal('adjusted_value', 20, 4)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id'], 'scme_tenant_memo_idx');
            $table->index(['tenant_id', 'exception_type'], 'scme_tenant_type_idx');
            $table->index(['tenant_id', 'severity', 'resolved_at'], 'scme_tenant_severity_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memo_exceptions');
    }
};
