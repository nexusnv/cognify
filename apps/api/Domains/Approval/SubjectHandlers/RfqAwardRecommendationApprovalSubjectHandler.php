<?php

namespace Domains\Approval\SubjectHandlers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\Quotation\Actions\MarkRfqAwardRecommendationApprovalRouted;
use Domains\Quotation\Actions\MarkRfqAwardRecommendationApproved;
use Domains\Quotation\Actions\MarkRfqAwardRecommendationRejected;
use Domains\Quotation\Actions\RequestRfqAwardRecommendationChanges;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Support\RfqScorecardCalculator;
use Illuminate\Database\Eloquent\Model;

final class RfqAwardRecommendationApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly MarkRfqAwardRecommendationApprovalRouted $markRouted,
        private readonly MarkRfqAwardRecommendationApproved $markApproved,
        private readonly MarkRfqAwardRecommendationRejected $markRejected,
        private readonly RequestRfqAwardRecommendationChanges $requestChanges,
        private readonly RfqScorecardCalculator $scorecardCalculator,
    ) {}

    public function subjectType(): string
    {
        return 'rfq_award_recommendation';
    }

    public function modelClass(): string
    {
        return RfqAwardRecommendation::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof RfqAwardRecommendation);

        $subject->loadMissing([
            'rfq',
            'recommendedVendor',
            'recommendedQuotation',
            'recommendedQuotationVersion',
            'scorecard.criteria',
            'scorecard.entries',
            'evidenceReferences',
        ]);

        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'rfq_award_recommendation',
            requisitionId: null,
            requesterId: null,
            amount: (float) ($subject->recommendedQuotationVersion?->total_amount ?? 0),
            currency: $subject->recommendedQuotationVersion?->currency,
            department: null,
            costCenter: null,
            projectId: null,
            lineItemCategories: [],
            riskClassification: null,
            vendorId: $subject->recommended_vendor_id !== null ? (string) $subject->recommended_vendor_id : null,
            awardRecommendationId: (string) $subject->id,
            rfqId: (string) $subject->rfq_id,
            rfqNumber: $subject->rfq?->number,
            recommendedVendorId: $subject->recommended_vendor_id !== null ? (string) $subject->recommended_vendor_id : null,
            recommendedVendorName: $subject->recommendedVendor?->name,
            recommendedQuotationId: $subject->recommended_quotation_id !== null ? (string) $subject->recommended_quotation_id : null,
            recommendedQuotationVersionId: $subject->recommended_quotation_version_id !== null ? (string) $subject->recommended_quotation_version_id : null,
            recommendedAmount: (float) ($subject->recommendedQuotationVersion?->total_amount ?? 0),
            recommendedCurrency: $subject->recommendedQuotationVersion?->currency,
            scorecardId: $subject->scorecard_id !== null ? (string) $subject->scorecard_id : null,
            scorecardWeightedTotal: $this->scorecardWeightedTotal($subject),
            riskSummaryPresent: filled($subject->risk_summary),
            exceptionSummaryPresent: filled($subject->exception_summary),
        );
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof RfqAwardRecommendation);

        $subject->loadMissing([
            'rfq',
            'recommendedVendor',
            'recommendedQuotationVersion',
            'scorecard.criteria',
            'scorecard.entries',
            'evidenceReferences',
        ]);

        $amount = $subject->recommendedQuotationVersion?->total_amount !== null
            ? (float) $subject->recommendedQuotationVersion->total_amount
            : null;

        return new ApprovalSubjectSummary(
            type: 'rfq_award_recommendation',
            id: (string) $subject->id,
            number: $subject->rfq?->number,
            title: 'Award recommendation for '.($subject->rfq?->title ?? 'RFQ'),
            status: $subject->statusState()->value,
            primaryParty: $subject->recommendedVendor?->name,
            amount: $amount,
            currency: $subject->recommendedQuotationVersion?->currency,
            href: "/quotations/awards/{$subject->rfq_id}",
            metadata: [
                'rfqId' => (string) $subject->rfq_id,
                'rfqNumber' => $subject->rfq?->number,
                'recommendedVendorId' => $subject->recommended_vendor_id !== null ? (string) $subject->recommended_vendor_id : null,
                'recommendedVendorName' => $subject->recommendedVendor?->name,
                'recommendedQuotationId' => $subject->recommended_quotation_id !== null ? (string) $subject->recommended_quotation_id : null,
                'recommendedQuotationVersionId' => $subject->recommended_quotation_version_id !== null ? (string) $subject->recommended_quotation_version_id : null,
                'rationale' => $subject->rationale,
                'tradeoffSummary' => $subject->tradeoff_summary,
                'riskSummary' => $subject->risk_summary,
                'exceptionSummary' => $subject->exception_summary,
                'scorecardId' => $subject->scorecard_id !== null ? (string) $subject->scorecard_id : null,
                'scorecardWeightedTotal' => $this->scorecardWeightedTotal($subject),
                'evidenceReferenceCount' => $subject->evidenceReferences->count(),
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof RfqAwardRecommendation);
        $subject->loadMissing('rfq');

        return 'Approve award recommendation for '.($subject->rfq?->number ?? 'RFQ');
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof RfqAwardRecommendation);
        $subject->loadMissing('rfq');

        return $subject->rfq?->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof RfqAwardRecommendation);
        $subject->loadMissing(['rfq', 'recommendedVendor']);

        return 'Award recommendation for '.($subject->recommendedVendor?->name ?? $subject->rfq?->number ?? 'RFQ');
    }

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof RfqAwardRecommendation);

        $this->markRouted->handle($subject, $instance, $actor);
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof RfqAwardRecommendation);

        $this->markApproved->handle($subject, $instance, $actor);
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof RfqAwardRecommendation);

        $this->markRejected->handle($subject, $instance, $actor, $reason);
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof RfqAwardRecommendation);

        $this->requestChanges->handle($subject, $instance, $actor, $reason, $requestedFields);
    }

    private function scorecardWeightedTotal(RfqAwardRecommendation $recommendation): ?float
    {
        if ($recommendation->scorecard === null || $recommendation->recommended_quotation_id === null) {
            return null;
        }

        $total = collect($this->scorecardCalculator->vendorTotals($recommendation->scorecard))
            ->firstWhere('quotationId', (string) $recommendation->recommended_quotation_id);

        return is_array($total) ? (float) $total['weightedTotal'] : null;
    }
}
