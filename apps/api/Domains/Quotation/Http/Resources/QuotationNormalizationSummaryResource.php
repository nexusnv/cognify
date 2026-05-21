<?php

namespace Domains\Quotation\Http\Resources;

use App\Auth\TenantRole;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalization
 */
class QuotationNormalizationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $normalization = $this->resource;
        $issues = $normalization->relationLoaded('issues') ? $normalization->issues : collect();
        $hasBlockingIssues = $issues->contains(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Blocking->value && $this->issueStatus($issue) !== QuotationNormalizationIssueStatus::Resolved->value);
        $hasUnresolvedWarnings = $issues->contains(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Warning->value && $this->issueStatus($issue) !== QuotationNormalizationIssueStatus::Resolved->value);
        $isReviewReady = in_array($normalization->status, [
            QuotationNormalizationStatus::NeedsReview,
            QuotationNormalizationStatus::ReadyForApproval,
        ], true);

        return [
            'id' => (string) $normalization->id,
            'status' => $normalization->status?->value ?? $normalization->status,
            'normalizationRevision' => $normalization->normalization_revision,
            'algorithmVersion' => $normalization->algorithm_version,
            'source' => [
                'quotationId' => (string) $normalization->quotation_id,
                'quotationVersionId' => (string) $normalization->quotation_version_id,
                'quotationNumber' => $normalization->quotation?->number,
                'versionNumber' => $normalization->quotationVersion?->version_number,
                'rfqId' => $normalization->quotation?->rfq_id !== null ? (string) $normalization->quotation->rfq_id : null,
                'rfqNumber' => $normalization->quotationVersion?->quotation?->rfq?->number ?? $normalization->quotation?->rfq?->number,
                'vendorId' => $normalization->quotation?->vendor_id !== null ? (string) $normalization->quotation->vendor_id : null,
                'vendorName' => $normalization->quotation?->vendor?->name,
            ],
            'summary' => [
                'blockingIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Blocking->value)->count(),
                'warningIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Warning->value)->count(),
                'infoIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Info->value)->count(),
            ],
            'permissions' => [
                'canEdit' => $request->user()?->can('update', $normalization) ?? false,
                'canApprove' => $this->canReview($request, $normalization) && $isReviewReady && ! $hasBlockingIssues,
                'canApproveWithWarnings' => $this->canReview($request, $normalization) && $isReviewReady && ! $hasBlockingIssues && $hasUnresolvedWarnings,
                'canRetry' => $request->user()?->can('retry', $normalization) ?? false,
                'canCreateRevision' => $request->user()?->can('createRevision', $normalization) ?? false,
            ],
        ];
    }

    private function canReview(Request $request, QuotationNormalization $normalization): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();
        $role = $tenant?->roleFor($request->user());

        return $tenant !== null
            && in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true)
            && (int) $normalization->tenant_id === (int) $tenant->id;
    }

    private function issueSeverity(mixed $issue): string
    {
        return $issue->severity?->value ?? $issue->severity;
    }

    private function issueStatus(mixed $issue): string
    {
        return $issue->status?->value ?? $issue->status;
    }
}
