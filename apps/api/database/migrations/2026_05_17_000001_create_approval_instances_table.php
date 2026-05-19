<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->foreignId('approval_policy_version_id')->nullable()->constrained('approval_policy_versions')->nullOnDelete();
            $table->string('status');
            $table->unsignedInteger('current_stage_sequence')->nullable();
            $table->json('matched_context')->nullable();
            $table->json('matched_explanation')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'subject_type', 'subject_id', 'status'], 'approval_instances_tenant_subject_status_idx');
        });

        Schema::create('approval_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->string('completion_rule');
            $table->string('status');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_instance_id', 'sequence']);
            $table->index('tenant_id');
            $table->index('status');
            $table->index('due_at');
            $table->index(['tenant_id', 'status']);
            $table->index(['approval_instance_id', 'status']);
        });

        Schema::table('approval_tasks', function (Blueprint $table): void {
            $table->foreignId('approval_instance_id')->nullable()->after('tenant_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->foreignId('approval_stage_id')->nullable()->after('approval_instance_id')->constrained('approval_stages')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->after('subject_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('original_assignee_id')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            $table->string('decision')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('requested_fields')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_from_task_id')->nullable()->constrained('approval_tasks')->nullOnDelete();
            $table->foreignId('escalated_from_task_id')->nullable()->constrained('approval_tasks')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);

            $table->index('tenant_id');
            $table->index('status');
            $table->index('assignee_id');
            $table->index('due_at');
            $table->index(['approval_instance_id', 'status']);
            $table->index(['approval_stage_id', 'status']);
            $table->index(['tenant_id', 'assignee_id', 'status']);
            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'approval_tasks_tenant_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::table('approval_tasks', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['assignee_id']);
            $table->dropIndex(['due_at']);
            $table->dropIndex(['approval_instance_id', 'status']);
            $table->dropIndex(['approval_stage_id', 'status']);
            $table->dropIndex(['tenant_id', 'assignee_id', 'status']);
            $table->dropIndex('approval_tasks_tenant_subject_idx');
            $table->dropConstrainedForeignId('escalated_from_task_id');
            $table->dropConstrainedForeignId('delegated_from_task_id');
            $table->dropConstrainedForeignId('decided_by_id');
            $table->dropConstrainedForeignId('original_assignee_id');
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropConstrainedForeignId('approval_stage_id');
            $table->dropConstrainedForeignId('approval_instance_id');
            $table->dropColumn([
                'decision',
                'decision_reason',
                'requested_fields',
                'assigned_at',
                'viewed_at',
                'decided_at',
                'lock_version',
            ]);
        });
        Schema::dropIfExists('approval_stages');
        Schema::dropIfExists('approval_instances');
    }
};
