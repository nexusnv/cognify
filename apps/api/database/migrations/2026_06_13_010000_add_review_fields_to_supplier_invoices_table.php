<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->foreignIdFor(User::class, 'review_started_by_user_id')
                ->nullable()
                ->after('captured_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('review_started_at')->nullable()->after('review_started_by_user_id');
            $table->foreignIdFor(User::class, 'reviewed_by_user_id')
                ->nullable()
                ->after('review_started_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->json('review_checklist')->nullable()->after('review_notes');
            $table->json('review_blockers')->nullable()->after('review_checklist');

            $table->index(['tenant_id', 'status', 'due_date'], 'supplier_invoices_tenant_status_due_idx');
            $table->index(['tenant_id', 'reviewed_at'], 'supplier_invoices_tenant_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropIndex('supplier_invoices_tenant_status_due_idx');
            $table->dropIndex('supplier_invoices_tenant_reviewed_idx');
            $table->dropConstrainedForeignId('review_started_by_user_id');
            $table->dropColumn('review_started_at');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn('reviewed_at');
            $table->dropColumn('review_notes');
            $table->dropColumn('review_checklist');
            $table->dropColumn('review_blockers');
        });
    }
};
