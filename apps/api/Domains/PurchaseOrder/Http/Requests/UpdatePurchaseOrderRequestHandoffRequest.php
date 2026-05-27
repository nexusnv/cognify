<?php

namespace Domains\PurchaseOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequestHandoffRequest extends FormRequest
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
            'lockVersion' => ['required', 'integer', 'min:1'],
            'requestedPoDate' => ['nullable', 'date'],
            'deliveryAttention' => ['nullable', 'string', 'max:255'],
            'financeNote' => ['nullable', 'string', 'max:5000'],
            'exportMemo' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
