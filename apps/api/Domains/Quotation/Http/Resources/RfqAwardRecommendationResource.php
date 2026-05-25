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
            'rationale' => $recommendation->rationale,
            'tradeoffSummary' => $recommendation->tradeoff_summary,
            'riskSummary' => $recommendation->risk_summary,
            'exceptionSummary' => $recommendation->exception_summary,
            'withdrawalReason' => $recommendation->withdrawal_reason,
            'submittedByUserId' => $recommendation->submitted_by_user_id !== null ? (string) $recommendation->submitted_by_user_id : null,
            'submittedAt' => $recommendation->submitted_at?->toISOString(),
            'withdrawnByUserId' => $recommendation->withdrawn_by_user_id !== null ? (string) $recommendation->withdrawn_by_user_id : null,
            'withdrawnAt' => $recommendation->withdrawn_at?->toISOString(),
            'updatedAt' => $recommendation->updated_at?->toISOString(),
        ];
    }
}
