<?php

namespace App\Http\Requests\Requisition;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequisitionRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'businessJustification' => ['sometimes', 'nullable', 'string'],
            'neededByDate' => ['sometimes', 'nullable', 'date'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'projectId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'costCenter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'deliveryLocation' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'lineItems' => ['sometimes', 'array'],
            'lineItems.*.name' => ['required_with:lineItems', 'string', 'max:255'],
            'lineItems.*.description' => ['nullable', 'string'],
            'lineItems.*.quantity' => ['required_with:lineItems', 'numeric', 'gt:0'],
            'lineItems.*.unit' => ['required_with:lineItems', 'string', 'max:50'],
            'lineItems.*.estimatedUnitPrice' => ['required_with:lineItems', 'numeric', 'gte:0'],
            'lineItems.*.currency' => ['required_with:lineItems', 'string', 'size:3'],
        ];
    }
}
