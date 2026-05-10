<?php

namespace App\Http\Requests\Requisition;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \Domains\Requisition\Models\Requisition::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'businessJustification' => ['nullable', 'string'],
            'neededByDate' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:255'],
            'projectId' => ['nullable', 'string', 'max:255'],
            'costCenter' => ['nullable', 'string', 'max:255'],
            'deliveryLocation' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
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
