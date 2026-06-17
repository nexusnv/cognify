<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->json('exception_summary')->nullable()->after('matching_status');
        });

        Schema::create('supplier_invoice_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->string('dimension');
            $table->string('match_type');
            $table->foreignUuid('supplier_invoice_line_id')->nullable()->constrained('supplier_invoice_lines')->nullOnDelete();
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->string('status');
            $table->string('resolution_type')->nullable();
            $table->json('resolution_data')->nullable();
            $table->foreignIdFor(User::class, 'resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignIdFor(User::class, 'escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'escalated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['supplier_invoice_id', 'status']);
            $table->index(['supplier_invoice_id', 'dimension']);
            $table->index(['escalated_to_user_id', 'status']);
        });

        // Composite unique: one exception per (invoice, dimension, match_type, line)
        // PostgreSQL 15+: use NULLS NOT DISTINCT so multiple NULL line IDs conflict
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX "supplier_invoice_exceptions_composite_unique" ON "supplier_invoice_exceptions" ("tenant_id", "supplier_invoice_id", "dimension", "match_type", "supplier_invoice_line_id") NULLS NOT DISTINCT');
        } else {
            Schema::table('supplier_invoice_exceptions', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'supplier_invoice_id', 'dimension', 'match_type', 'supplier_invoice_line_id'],
                    'supplier_invoice_exceptions_composite_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_exceptions');
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropColumn('exception_summary');
        });
    }
};
