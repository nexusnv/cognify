<?php

namespace App\Providers;

use App\Audit\AuditEvent;
use App\Audit\Policies\AuditEventPolicy;
use Domains\Attachment\Models\Attachment;
use Domains\Attachment\Policies\AttachmentPolicy;
use App\Tenancy\CurrentTenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\Policies\ProcurementProjectPolicy;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Policies\QuotationComparisonNotePolicy;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\Policies\QuotationNormalizationPolicy;
use Domains\Quotation\Policies\RfqAwardRecommendationPolicy;
use Domains\Quotation\Policies\RfqPolicy;
use Domains\Quotation\Policies\RfqInvitationPolicy;
use Domains\Quotation\Policies\SourcingIntakeReviewPolicy;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Policies\RequisitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(ProcurementProject::class, ProcurementProjectPolicy::class);
        Gate::policy(SourcingIntakeReview::class, SourcingIntakeReviewPolicy::class);
        Gate::policy(QuotationComparisonNote::class, QuotationComparisonNotePolicy::class);
        Gate::policy(QuotationNormalization::class, QuotationNormalizationPolicy::class);
        Gate::policy(Rfq::class, RfqPolicy::class);
        Gate::policy(RfqAwardRecommendation::class, RfqAwardRecommendationPolicy::class);
        Gate::policy(RfqInvitation::class, RfqInvitationPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
    }
}
