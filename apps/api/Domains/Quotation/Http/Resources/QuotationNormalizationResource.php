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
class QuotationNormalizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $normalization = $this->resource;

        return [
            'id' => (string) $normalization->id,
            'status' => $normalization->status?->value ?? $normalization->status,
            'normalizationRevision' => $normalization->normalization_revision,
            'algorithmVersion' => $normalization->algorithm_version,
            'updatedAt' => $normalization->updated_at?->toISOString(),
            'lastJobError' => $normalization->last_job_error,
            'source' => $this->sourceSummary($normalization),
            'summary' => $this->summary($normalization),
            'fields' => $normalization->relationLoaded('fields')
                ? QuotationNormalizationFieldResource::collection($normalization->fields)
                : [],
            'lineGroups' => $normalization->relationLoaded('lineGroups')
                ? QuotationNormalizationLineGroupResource::collection($normalization->lineGroups)
                : [],
            'attachments' => $normalization->relationLoaded('attachments')
                ? QuotationNormalizationAttachmentResource::collection($normalization->attachments)
                : [],
            'issues' => $normalization->relationLoaded('issues')
                ? QuotationNormalizationIssueResource::collection($normalization->issues)
                : [],
            'permissions' => $this->permissions($request, $normalization),
        ];
    }

    private function sourceSummary(QuotationNormalization $normalization): array
    {
        $quotation = $normalization->relationLoaded('quotation') ? $normalization->quotation : null;
        $version = $normalization->relationLoaded('quotationVersion') ? $normalization->quotationVersion : null;
        $rfq = $version?->relationLoaded('quotation') ? $version->quotation?->rfq : $quotation?->rfq;

        return [
            'quotationId' => (string) $normalization->quotation_id,
            'quotationVersionId' => (string) $normalization->quotation_version_id,
            'quotationNumber' => $quotation?->number,
            'versionNumber' => $version?->version_number,
            'rfqId' => $quotation?->rfq_id !== null ? (string) $quotation->rfq_id : null,
            'rfqNumber' => $rfq?->number,
            'vendorId' => $quotation?->vendor_id !== null ? (string) $quotation->vendor_id : null,
            'vendorName' => $quotation?->vendor?->name,
        ];
    }

    private function summary(QuotationNormalization $normalization): array
    {
        $issues = $normalization->relationLoaded('issues') ? $normalization->issues : collect();

        return [
            'blockingIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Blocking->value)->count(),
            'warningIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Warning->value)->count(),
            'infoIssueCount' => $issues->filter(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Info->value)->count(),
        ];
    }

    private function permissions(Request $request, QuotationNormalization $normalization): array
    {
        $tenant = app(CurrentTenant::class)->nullable();
        $user = $request->user();
        $role = $tenant?->roleFor($user);
        $issues = $normalization->relationLoaded('issues') ? $normalization->issues : collect();
        $hasBlockingIssues = $issues->contains(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Blocking->value && $this->issueStatus($issue) !== QuotationNormalizationIssueStatus::Resolved->value);
        $hasUnresolvedWarnings = $issues->contains(fn ($issue) => $this->issueSeverity($issue) === QuotationNormalizationIssueSeverity::Warning->value && $this->issueStatus($issue) !== QuotationNormalizationIssueStatus::Resolved->value);
        $isReviewReady = in_array($normalization->status, [
            QuotationNormalizationStatus::NeedsReview,
            QuotationNormalizationStatus::ReadyForApproval,
        ], true);
        $canReview = $tenant !== null && in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true) && (int) $normalization->tenant_id === (int) $tenant->id;

        return [
            'canEdit' => $canReview && $normalization->isMutable(),
            'canApprove' => $canReview && $isReviewReady && ! $hasBlockingIssues,
            'canApproveWithWarnings' => $canReview && $isReviewReady && ! $hasBlockingIssues && $hasUnresolvedWarnings,
            'canRetry' => $canReview && $normalization->status === QuotationNormalizationStatus::Failed,
            'canCreateRevision' => $canReview && in_array($normalization->status, [
                QuotationNormalizationStatus::Approved,
                QuotationNormalizationStatus::ApprovedWithWarnings,
            ], true),
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
