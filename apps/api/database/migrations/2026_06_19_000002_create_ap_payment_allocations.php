<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_allocations', function (Blueprint $table): void {
            // NOTE: Cognify's HasUuids convention stores primary keys as native PG `uuid` (not
            // char(36)). The parent tables ap_payment_handoffs.id and supplier_invoices.id are
            // both uuid, so the FK columns must match. The closest analog
            // (2026_06_18_000003_create_ap_payment_handoff_invoice_table.php) uses ->uuid() as well.
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('ap_payment_handoff_id');
            $table->foreign('ap_payment_handoff_id')->references('id')->on('ap_payment_handoffs')->onDelete('cascade');
            $table->uuid('supplier_invoice_id');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->onDelete('restrict');
            $table->decimal('allocated_amount', 20, 4);
            $table->date('allocation_date');
            $table->string('payment_reference', 255)->nullable();
            $table->decimal('settlement_amount', 20, 4)->nullable();
            $table->string('settlement_currency', 3)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_invoice_id'], 'ap_alloc_tenant_invoice_idx');
            $table->index(['tenant_id', 'ap_payment_handoff_id'], 'ap_alloc_tenant_handoff_idx');
        });

        // NULLS NOT DISTINCT prevents duplicate rows when payment_reference is omitted by bank files.
        // PostgreSQL 15+ supports this directly. SQLite (used in test DB) does not, so the
        // migration branches on the connection driver. The COALESCE fallback folds NULL into an
        // empty string, making NULLs equal in the unique index for the SQLite test environment.
        // In production (PostgreSQL 15+), the NULLS NOT DISTINCT form is used and the COALESCE
        // fallback is skipped.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX ap_alloc_unique_handoff_invoice_date_ref
                ON ap_payment_allocations (ap_payment_handoff_id, supplier_invoice_id, allocation_date, payment_reference)
                NULLS NOT DISTINCT
                WHERE voided_at IS NULL
            SQL);
        } else {
            // SQLite + PostgreSQL < 15 fallback. COALESCE folds NULL to '' so duplicate rows
            // with NULL payment_reference are still rejected. Scoped to non-voided rows via
            // partial index WHERE clause.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX ap_alloc_unique_handoff_invoice_date_ref
                ON ap_payment_allocations (
                    ap_payment_handoff_id,
                    supplier_invoice_id,
                    allocation_date,
                    COALESCE(payment_reference, '')
                )
                WHERE voided_at IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_allocations');
    }
};
