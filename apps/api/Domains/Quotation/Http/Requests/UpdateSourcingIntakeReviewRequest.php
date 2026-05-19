<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSourcingIntakeReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'urgency' => ['sometimes', 'nullable', Rule::in(['low', 'standard', 'urgent'])],
            'complexity' => ['sometimes', 'nullable', Rule::in(['low', 'medium', 'high'])],
            'targetDecisionDate' => ['sometimes', 'nullable', 'date'],
            'checklist' => ['sometimes', 'array'],
            'checklist.*.key' => ['required_with:checklist', 'string', 'max:100'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.complete' => ['required_with:checklist', 'boolean'],
            'internalNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
