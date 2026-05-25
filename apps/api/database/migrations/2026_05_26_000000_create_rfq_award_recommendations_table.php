<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_award_recommendations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->cascadeOnDelete();
            $table->foreignIdFor(Vendor::class, 'recommended_vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->foreignIdFor(Quotation::class, 'recommended_quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->foreignIdFor(QuotationVersion::class, 'recommended_quotation_version_id')->nullable()->constrained('quotation_versions')->restrictOnDelete();
            $table->foreignUuid('scorecard_id')->nullable()->constrained('rfq_scorecards')->nullOnDelete();
            $table->string('status');
            $table->text('rationale')->nullable();
            $table->text('tradeoff_summary')->nullable();
            $table->text('risk_summary')->nullable();
            $table->text('exception_summary')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'withdrawn_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'rfq_id', 'status']);
        });

        Schema::create('rfq_award_recommendation_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('recommendation_id')->constrained('rfq_award_recommendations')->cascadeOnDelete();
            $table->string('evidence_type');
            $table->string('evidence_id');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'evidence_type', 'evidence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_award_recommendation_evidence');
        Schema::dropIfExists('rfq_award_recommendations');
    }
};
