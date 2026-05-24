<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Vendor\Models\Vendor;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_scorecards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->cascadeOnDelete();
            $table->foreignUuid('template_id')
                ->nullable()
                ->constrained('quotation_scoring_templates')
                ->nullOnDelete();
            $table->string('template_name');
            $table->text('template_description')->nullable();
            $table->string('status')->default(RfqScorecardStatus::InProgress->value);
            $table->foreignIdFor(User::class, 'applied_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->foreignIdFor(User::class, 'completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'rfq_id']);
            $table->unique('rfq_id');
        });

        Schema::create('rfq_scorecard_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('scorecard_id')->constrained('rfq_scorecards')->cascadeOnDelete();
            $table->foreignUuid('source_template_criterion_id')
                ->nullable()
                ->constrained('quotation_scoring_template_criteria')
                ->nullOnDelete();
            $table->string('category');
            $table->string('label');
            $table->text('guidance')->nullable();
            $table->decimal('weight', 8, 2);
            $table->unsignedInteger('max_score');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->unique(['scorecard_id', 'display_order']);
        });

        Schema::create('rfq_scorecard_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('scorecard_id')->constrained('rfq_scorecards')->cascadeOnDelete();
            $table->foreignUuid('scorecard_criterion_id')->constrained('rfq_scorecard_criteria')->cascadeOnDelete();
            $table->foreignIdFor(Vendor::class)->constrained('vendors')->cascadeOnDelete();
            $table->foreignIdFor(Quotation::class)->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignIdFor(QuotationVersion::class)->nullable()->constrained('quotation_versions')->nullOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignIdFor(User::class, 'scored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['scorecard_id', 'scorecard_criterion_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_scorecard_entries');
        Schema::dropIfExists('rfq_scorecard_criteria');
        Schema::dropIfExists('rfq_scorecards');
    }
};
