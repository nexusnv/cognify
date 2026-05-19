<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sourcing_intake_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('assigned_buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('sourcing_path')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('urgency')->nullable();
            $table->string('complexity')->nullable();
            $table->date('target_decision_date')->nullable();
            $table->json('checklist')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('decision_reason')->nullable();
            $table->text('clarification_message')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'requisition_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_buyer_id', 'status']);
            $table->index(['tenant_id', 'target_decision_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sourcing_intake_reviews');
    }
};
