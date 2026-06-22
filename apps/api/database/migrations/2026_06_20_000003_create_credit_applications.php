<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table): void {
            // Drift from design spec: applied_by_user_id and voided_by_user_id
            // are foreignId() (bigint) because users.id is bigint, not uuid.
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('supplier_credit_memo_id');
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->uuid('supplier_invoice_id');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->restrictOnDelete();
            $table->decimal('applied_amount', 20, 4);
            $table->date('application_date');
            $table->foreignId('applied_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id'], 'ca_tenant_memo_idx');
            $table->index(['tenant_id', 'supplier_invoice_id'], 'ca_tenant_invoice_idx');
        });

        // Drift from design spec: the design spec calls for a single unique index
        // on (tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date)
        // with NULLS NOT DISTINCT. Cognify's P1-48 ApPaymentAllocations migration
        // established a precedent of partial unique indexes scoped to non-voided rows
        // with COALESCE-based fallback for SQLite test environment. The unique
        // constraint here excludes voided rows to allow re-application after void.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX ca_unique_memo_invoice_date_nulls_not_distinct
                ON credit_applications (tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date)
                NULLS NOT DISTINCT
                WHERE voided_at IS NULL
            SQL);
        } else {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX ca_unique_memo_invoice_date_nulls_not_distinct
                ON credit_applications (
                    tenant_id,
                    supplier_credit_memo_id,
                    supplier_invoice_id,
                    application_date
                )
                WHERE voided_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
