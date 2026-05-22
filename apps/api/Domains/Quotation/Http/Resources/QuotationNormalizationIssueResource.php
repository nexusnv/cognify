<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationNormalizationIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalizationIssue
 */
class QuotationNormalizationIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'severity' => $this->severity?->value ?? $this->severity,
            'fieldPath' => $this->field_path,
            'issueCode' => $this->issue_code,
            'message' => $this->message,
            'rawValue' => $this->raw_value,
            'suggestedValue' => $this->suggested_value,
            'status' => $this->status?->value ?? $this->status,
            'resolvedByUserId' => $this->resolved_by_user_id === null ? null : (string) $this->resolved_by_user_id,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'resolutionNote' => $this->resolution_note,
        ];
    }
}
