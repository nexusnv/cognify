<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\RfqAwardRecommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqAwardRecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RfqAwardRecommendation $recommendation */
        $recommendation = $this->resource;

        return [
            'id' => (string) $recommendation->id,
            'status' => $recommendation->statusState()->value,
            'recommendedVendorId' => $recommendation->recommended_vendor_id !== null ? (string) $recommendation->recommended_vendor_id : null,
            'recommendedQuotationId' => $recommendation->recommended_quotation_id !== null ? (string) $recommendation->recommended_quotation_id : null,
            'recommendedQuotationVersionId' => $recommendation->recommended_quotation_version_id !== null ? (string) $recommendation->recommended_quotation_version_id : null,
            'scorecardId' => $recommendation->scorecard_id !== null ? (string) $recommendation->scorecard_id : null,
            'approvalInstanceId' => $recommendation->approval_instance_id !== null ? (string) $recommendation->approval_instance_id : null,
            'rationale' => $recommendation->rationale,
            'tradeoffSummary' => $recommendation->tradeoff_summary,
            'riskSummary' => $recommendation->risk_summary,
            'exceptionSummary' => $recommendation->exception_summary,
            'withdrawalReason' => $recommendation->withdrawal_reason,
            'submittedByUserId' => $recommendation->submitted_by_user_id !== null ? (string) $recommendation->submitted_by_user_id : null,
            'submittedAt' => $recommendation->submitted_at?->toISOString(),
            'withdrawnByUserId' => $recommendation->withdrawn_by_user_id !== null ? (string) $recommendation->withdrawn_by_user_id : null,
            'withdrawnAt' => $recommendation->withdrawn_at?->toISOString(),
            'approvedByUserId' => $recommendation->approved_by_user_id !== null ? (string) $recommendation->approved_by_user_id : null,
            'approvedAt' => $recommendation->approved_at?->toISOString(),
            'rejectedByUserId' => $recommendation->rejected_by_user_id !== null ? (string) $recommendation->rejected_by_user_id : null,
            'rejectedAt' => $recommendation->rejected_at?->toISOString(),
            'decisionReason' => $recommendation->decision_reason,
            'changesRequestedByUserId' => $recommendation->changes_requested_by_user_id !== null ? (string) $recommendation->changes_requested_by_user_id : null,
            'changesRequestedAt' => $recommendation->changes_requested_at?->toISOString(),
            'changesRequestedReason' => $recommendation->changes_requested_reason,
            'changesRequestedFields' => $recommendation->changes_requested_fields ?? [],
            'updatedAt' => $recommendation->updated_at?->toISOString(),
        ];
    }
}
