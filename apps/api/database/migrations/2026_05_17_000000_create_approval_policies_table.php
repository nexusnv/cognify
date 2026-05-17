<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject_type');
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('subject_type');
            $table->index('status');
            $table->index(['tenant_id', 'subject_type', 'status']);
        });

        Schema::create('approval_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_policy_id')->constrained('approval_policies')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedInteger('version_number');
            $table->string('status');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->json('rules');
            $table->json('route_template');
            $table->json('sla_rules')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_policy_id', 'version_number']);
            $table->index('tenant_id');
            $table->index('subject_type');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'approval_policy_id', 'status']);
            $table->index(['tenant_id', 'subject_type', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_policy_versions');
        Schema::dropIfExists('approval_policies');
    }
};
