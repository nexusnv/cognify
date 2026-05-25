<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRfqAwardRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recommendedVendorId' => ['nullable', 'integer'],
            'recommendedQuotationId' => ['nullable', 'integer'],
            'recommendedQuotationVersionId' => ['nullable', 'integer'],
            'scorecardId' => ['nullable', 'uuid'],
            'rationale' => ['nullable', 'string', 'max:4000'],
            'tradeoffSummary' => ['nullable', 'string', 'max:4000'],
            'riskSummary' => ['nullable', 'string', 'max:4000'],
            'exceptionSummary' => ['nullable', 'string', 'max:4000'],
            'evidenceReferences' => ['sometimes', 'array'],
            'evidenceReferences.*.type' => ['required_with:evidenceReferences', Rule::enum(RfqAwardRecommendationEvidenceType::class)],
            'evidenceReferences.*.id' => ['required_with:evidenceReferences', 'string', 'max:80'],
            'evidenceReferences.*.label' => ['nullable', 'string', 'max:160'],
        ];
    }
}
