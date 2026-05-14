<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status');
            $table->string('category')->nullable();
            $table->string('risk_rating')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('procurement_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number');
            $table->string('name');
            $table->string('status');
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('rfqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->string('number');
            $table->string('title');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('approval_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('demo_seed_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('seeded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
        Schema::dropIfExists('approval_tasks');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('procurement_projects');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('demo_seed_runs');
    }
};
