<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRfqDraftRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'scopeSummary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'responseDueAt' => ['sometimes', 'nullable', 'date'],
            'responseInstructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'requiredDocuments' => ['sometimes', 'nullable', 'array', 'max:20'],
            'requiredDocuments.*.key' => ['required_with:requiredDocuments', 'string', 'max:80'],
            'requiredDocuments.*.label' => ['required_with:requiredDocuments', 'string', 'max:160'],
            'requiredDocuments.*.required' => ['required_with:requiredDocuments', 'boolean'],
            'lineItems' => ['sometimes', 'nullable', 'array', 'max:100'],
            'lineItems.*.description' => ['required_with:lineItems', 'string', 'max:255'],
            'lineItems.*.quantity' => ['required_with:lineItems', 'numeric', 'min:0.01'],
            'lineItems.*.unit' => ['required_with:lineItems', 'string', 'max:40'],
            'lineItems.*.notes' => ['nullable', 'string', 'max:1000'],
            'evaluationNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'internalNotes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
