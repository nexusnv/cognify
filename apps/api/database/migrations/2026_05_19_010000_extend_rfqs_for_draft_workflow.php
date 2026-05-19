<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            $table->foreignId('sourcing_intake_review_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('sourcing_intake_reviews')
                ->nullOnDelete();
            $table->text('scope_summary')->nullable()->after('status');
            $table->timestamp('response_due_at')->nullable()->after('scope_summary');
            $table->text('response_instructions')->nullable()->after('response_due_at');
            $table->json('required_documents')->nullable()->after('response_instructions');
            $table->json('line_items')->nullable()->after('required_documents');
            $table->text('evaluation_notes')->nullable()->after('line_items');
            $table->text('internal_notes')->nullable()->after('evaluation_notes');
            $table->text('cancel_reason')->nullable()->after('internal_notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->index(['tenant_id', 'sourcing_intake_review_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'sourcing_intake_review_id']);
            $table->dropConstrainedForeignId('sourcing_intake_review_id');
            $table->dropColumn([
                'scope_summary',
                'response_due_at',
                'response_instructions',
                'required_documents',
                'line_items',
                'evaluation_notes',
                'internal_notes',
                'cancel_reason',
                'cancelled_at',
            ]);
        });
    }
};
