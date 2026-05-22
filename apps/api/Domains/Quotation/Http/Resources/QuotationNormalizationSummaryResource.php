<?php

namespace Domains\Quotation\Http\Resources;

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
        $user = $request->user();
        $canUpdate = $user?->can('update', $normalization) ?? false;
        $canApprove = $user?->can('approve', $normalization) ?? false;
        $canApproveWithWarnings = $user?->can('approveWithWarnings', $normalization) ?? false;
        $canRetry = $user?->can('retry', $normalization) ?? false;
        $canCreateRevision = $user?->can('createRevision', $normalization) ?? false;

        return [
            'id' => (string) $normalization->id,
            'status' => $normalization->status?->value ?? $normalization->status,
            'normalizationRevision' => $normalization->normalization_revision,
            'algorithmVersion' => $normalization->algorithm_version,
            'updatedAt' => $normalization->updated_at?->toISOString(),
            'lastJobError' => $normalization->last_job_error,
            'source' => $this->sourceSummary($normalization),
            'summary' => [
                'blockingIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Blocking->value)->count(),
                'warningIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Warning->value)->count(),
                'infoIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Info->value)->count(),
            ],
            'permissions' => [
                'canEdit' => $canUpdate && $normalization->isMutable(),
                'canApprove' => $canApprove && $isReviewReady && ! $hasBlockingIssues && ! $hasUnresolvedWarnings,
                'canApproveWithWarnings' => $canApproveWithWarnings && $isReviewReady && ! $hasBlockingIssues && $hasUnresolvedWarnings,
                'canRetry' => $canRetry && $normalization->status === QuotationNormalizationStatus::Failed,
                'canCreateRevision' => $canCreateRevision && in_array($normalization->status, [
                    QuotationNormalizationStatus::Approved,
                    QuotationNormalizationStatus::ApprovedWithWarnings,
                ], true),
            ],
        ];
    }

    private function sourceSummary(QuotationNormalization $normalization): array
    {
        $quotation = $normalization->relationLoaded('quotation') ? $normalization->quotation : null;
        $version = $normalization->relationLoaded('quotationVersion') ? $normalization->quotationVersion : null;
        $versionQuotation = $version?->relationLoaded('quotation') ? $version->quotation : null;
        $rfq = $versionQuotation?->relationLoaded('rfq') ? $versionQuotation->rfq : null;
        $rfq ??= $quotation?->relationLoaded('rfq') ? $quotation->rfq : null;
        $vendor = $quotation?->relationLoaded('vendor') ? $quotation->vendor : null;

        return [
            'quotationId' => (string) $normalization->quotation_id,
            'quotationVersionId' => (string) $normalization->quotation_version_id,
            'quotationNumber' => $quotation?->number,
            'versionNumber' => $version?->version_number,
            'rfqId' => $quotation?->rfq_id !== null ? (string) $quotation->rfq_id : null,
            'rfqNumber' => $rfq?->number,
            'vendorId' => $quotation?->vendor_id !== null ? (string) $quotation->vendor_id : null,
            'vendorName' => $vendor?->name,
        ];
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
