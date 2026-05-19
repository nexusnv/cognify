<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\SourcingPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSourcingIntakeDecisionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sourcingPath' => ['required', Rule::enum(SourcingPath::class)],
            'decisionReason' => ['required', 'string', 'min:10', 'max:5000'],
            'clarificationMessage' => ['required_if:sourcingPath,needs_clarification', 'nullable', 'string', 'max:5000'],
            'clarificationFields' => ['sometimes', 'array'],
            'clarificationFields.*' => ['string', 'max:100'],
        ];
    }
}
